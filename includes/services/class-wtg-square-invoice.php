<?php
/**
 * Square Invoice Service
 *
 * Handles Square invoice creation and management.
 * Uses Square SDK v36 which requires creating an Order first,
 * then creating an Invoice from that Order.
 *
 * @package WTG2
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Square Invoice service class.
 */
class WTG_Square_Invoice {

	/**
	 * Get Square API client.
	 *
	 * @return \Square\SquareClient|null
	 */
	private static function get_client() {
		// Check if Square SDK is installed.
		if ( ! class_exists( '\Square\SquareClient' ) ) {
			error_log( 'WTG2: Square SDK not installed. Run "composer install" to enable Square integration.' );
			return null;
		}

		$environment = get_option( 'wtg_square_environment', 'sandbox' );
		$access_token = get_option( "wtg_square_{$environment}_access_token", '' );

		if ( empty( $access_token ) ) {
			error_log( 'WTG2: Square access token not configured for ' . $environment );
			return null;
		}

		try {
			return new \Square\SquareClient([
				'accessToken' => $access_token,
				'environment' => $environment,
			]);
		} catch ( Exception $e ) {
			error_log( 'WTG2: Failed to create Square client: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Get the location ID for the current environment.
	 *
	 * @return string|null Location ID or null if not configured.
	 */
	private static function get_location_id() {
		$environment = get_option( 'wtg_square_environment', 'sandbox' );
		$location_id = get_option( "wtg_square_{$environment}_location_id", '' );

		if ( empty( $location_id ) ) {
			error_log( 'WTG2: Square location ID not configured for ' . $environment );
			return null;
		}

		return $location_id;
	}

	/**
	 * Create balance invoice as a DRAFT in Square.
	 *
	 * Called at booking time. The invoice is NOT published/sent yet.
	 * The cron job will publish it 72 hours before the tour.
	 *
	 * Flow: Create Customer → Create Order → Create Draft Invoice.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $booking    Booking data.
	 * @return array ['success' => bool, 'invoice_id' => string, 'error' => string]
	 */
	public static function create_balance_invoice( $booking_id, $booking ) {
		$client = self::get_client();

		if ( ! $client ) {
			return array(
				'success' => false,
				'error'   => 'Square client not configured',
			);
		}

		$location_id = self::get_location_id();

		if ( ! $location_id ) {
			return array(
				'success' => false,
				'error'   => 'Square location ID not configured',
			);
		}

		try {
			// Step 1: Get or create customer.
			$customer_id = self::get_or_create_customer( $booking, $client );

			if ( ! $customer_id ) {
				return array(
					'success' => false,
					'error'   => 'Failed to create/retrieve Square customer',
				);
			}

			// Step 2: Create an Order with line items.
			$order_result = self::create_order( $client, $location_id, $customer_id, $booking, 'balance' );

			if ( ! $order_result['success'] ) {
				return array(
					'success' => false,
					'error'   => 'Failed to create order: ' . $order_result['error'],
				);
			}

			$order_id = $order_result['order_id'];

			// Step 3: Create Invoice from the Order (DRAFT — not published yet).
			$invoice_api = $client->getInvoicesApi();

			$recipient = \Square\Models\Builders\InvoiceRecipientBuilder::init()
				->customerId( $customer_id )
				->build();

			// Square requires due date to be today or later.
			$due_date = $booking['tour_date'];
			if ( strtotime( $due_date ) < strtotime( 'today' ) ) {
				$due_date = date( 'Y-m-d' );
			}

			$payment_request = \Square\Models\Builders\InvoicePaymentRequestBuilder::init()
				->requestType( 'BALANCE' )
				->dueDate( $due_date )
				->build();

			// Accepted payment methods — allow card payments.
			$accepted_methods = new \Square\Models\InvoiceAcceptedPaymentMethods();
			$accepted_methods->setCard( true );

			$invoice = \Square\Models\Builders\InvoiceBuilder::init()
				->locationId( $location_id )
				->orderId( $order_id )
				->primaryRecipient( $recipient )
				->paymentRequests( array( $payment_request ) )
				->acceptedPaymentMethods( $accepted_methods )
				->deliveryMethod( 'EMAIL' )
				->invoiceNumber( $booking['booking_code'] ?? 'WTG-' . $booking_id )
				->title( 'Wine Tours Grapevine - Balance Due' )
				->description( sprintf(
					'Balance payment for tour on %s at %s',
					date( 'F j, Y', strtotime( $booking['tour_date'] ) ),
					self::get_time_slot_label( $booking['time_slot'] )
				) )
				->build();

			$create_request = new \Square\Models\CreateInvoiceRequest( $invoice );
			$create_request->setIdempotencyKey( 'wtg-balance-' . $booking_id . '-' . time() );

			$result = $invoice_api->createInvoice( $create_request );

			if ( ! $result->isSuccess() ) {
				$errors = $result->getErrors();
				$error_msg = ! empty( $errors ) ? $errors[0]->getDetail() : 'Unknown error';
				error_log( 'WTG2: Failed to create balance invoice: ' . $error_msg );
				return array(
					'success' => false,
					'error'   => 'Failed to create invoice: ' . $error_msg,
				);
			}

			$created_invoice = $result->getResult()->getInvoice();

			return array(
				'success'    => true,
				'invoice_id' => $created_invoice->getId(),
			);
		} catch ( Exception $e ) {
			error_log( 'WTG2: Exception in create_balance_invoice: ' . $e->getMessage() );
			return array(
				'success' => false,
				'error'   => 'Exception: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Publish (send) a draft invoice in Square.
	 *
	 * Called by the cron job 72 hours before the tour, or manually by admin.
	 *
	 * @param string $invoice_id Square invoice ID.
	 * @param int    $booking_id Booking ID (for logging).
	 * @return array ['success' => bool, 'invoice_url' => string, 'error' => string]
	 */
	public static function publish_invoice( $invoice_id, $booking_id = 0 ) {
		$client = self::get_client();

		if ( ! $client ) {
			return array(
				'success' => false,
				'error'   => 'Square client not configured',
			);
		}

		try {
			$invoice_api = $client->getInvoicesApi();

			// Fetch the draft invoice to get its current version.
			$get_result = $invoice_api->getInvoice( $invoice_id );

			if ( ! $get_result->isSuccess() ) {
				$errors = $get_result->getErrors();
				$error_msg = ! empty( $errors ) ? $errors[0]->getDetail() : 'Unknown error';
				return array(
					'success' => false,
					'error'   => 'Failed to retrieve invoice: ' . $error_msg,
				);
			}

			$invoice = $get_result->getResult()->getInvoice();

			// If already published, return success.
			if ( 'DRAFT' !== $invoice->getStatus() ) {
				return array(
					'success'     => true,
					'invoice_url' => $invoice->getPublicUrl() ?? '',
				);
			}

			// Publish the invoice — this sends the email to the customer.
			$publish_request = new \Square\Models\PublishInvoiceRequest( $invoice->getVersion() );
			$publish_request->setIdempotencyKey( 'wtg-publish-' . $booking_id . '-' . time() );

			$publish_result = $invoice_api->publishInvoice(
				$invoice_id,
				$publish_request
			);

			if ( $publish_result->isSuccess() ) {
				$published_invoice = $publish_result->getResult()->getInvoice();
				return array(
					'success'     => true,
					'invoice_url' => $published_invoice->getPublicUrl() ?? '',
				);
			} else {
				$errors = $publish_result->getErrors();
				$error_msg = ! empty( $errors ) ? $errors[0]->getDetail() : 'Unknown error';
				return array(
					'success' => false,
					'error'   => 'Failed to publish invoice: ' . $error_msg,
				);
			}
		} catch ( Exception $e ) {
			error_log( 'WTG2: Exception in publish_invoice: ' . $e->getMessage() );
			return array(
				'success' => false,
				'error'   => 'Exception: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Create a Square Order with line items.
	 *
	 * Square invoices require an associated Order that holds the line items.
	 *
	 * @param \Square\SquareClient $client      Square client.
	 * @param string               $location_id Location ID.
	 * @param string               $customer_id Customer ID.
	 * @param array                $booking     Booking data.
	 * @param string               $type        'deposit' or 'balance'.
	 * @return array ['success' => bool, 'order_id' => string, 'error' => string]
	 */
	private static function create_order( $client, $location_id, $customer_id, $booking, $type ) {
		$orders_api = $client->getOrdersApi();
		$tickets    = intval( $booking['tickets'] ?? 1 );
		$line_items = array();

		if ( 'balance' === $type ) {
			$balance_due   = floatval( $booking['balance_due'] ?? ( 130.00 * $tickets ) );
			$gross_balance = 130.00 * $tickets;
			$has_discount  = ( $balance_due < $gross_balance );

			// Balance line item — the $130/ticket balance (deposit already separate).
			$description = sprintf(
				'Tour Balance - %d ticket%s%s',
				$tickets,
				$tickets > 1 ? 's' : '',
				$has_discount ? ' (gift certificate applied)' : ''
			);

			$balance_item = \Square\Models\Builders\OrderLineItemBuilder::init( '1' )
				->name( $description )
				->basePriceMoney(
					\Square\Models\Builders\MoneyBuilder::init()
						->amount( intval( round( $balance_due * 100 ) ) )
						->currency( 'USD' )
						->build()
				)
				->build();

			$line_items[] = $balance_item;

			// Texas sales tax: 8.25% on the FULL ticket price ($165/ticket),
			// collected entirely on the balance invoice per business rules.
			$full_ticket_price = 165.00 * $tickets;
			$tax_amount        = round( $full_ticket_price * 0.0825, 2 );

			if ( $tax_amount > 0 ) {
				$tax_item = \Square\Models\Builders\OrderLineItemBuilder::init( '1' )
					->name( sprintf( 'Texas Sales Tax (8.25%% on $%s)', number_format( $full_ticket_price, 2 ) ) )
					->basePriceMoney(
						\Square\Models\Builders\MoneyBuilder::init()
							->amount( intval( round( $tax_amount * 100 ) ) )
							->currency( 'USD' )
							->build()
					)
					->build();

				$line_items[] = $tax_item;
			}
		} else {
			// Deposit order.
			$total_amount = floatval( $booking['deposit_amount'] ?? ( 35.00 * $tickets ) );

			$deposit_item = \Square\Models\Builders\OrderLineItemBuilder::init( '1' )
				->name( sprintf(
					'Tour Deposit - %d ticket%s',
					$tickets,
					$tickets > 1 ? 's' : ''
				) )
				->basePriceMoney(
					\Square\Models\Builders\MoneyBuilder::init()
						->amount( intval( round( $total_amount * 100 ) ) )
						->currency( 'USD' )
						->build()
				)
				->build();

			$line_items[] = $deposit_item;
		}

		try {
			$order = \Square\Models\Builders\OrderBuilder::init( $location_id )
				->customerId( $customer_id )
				->referenceId( 'wtg-booking-' . ( $booking['id'] ?? 0 ) )
				->lineItems( $line_items )
				->build();

			$create_request = new \Square\Models\CreateOrderRequest();
			$create_request->setOrder( $order );
			$create_request->setIdempotencyKey( 'wtg-order-' . $type . '-' . ( $booking['id'] ?? 0 ) . '-' . time() );

			$result = $orders_api->createOrder( $create_request );

			if ( $result->isSuccess() ) {
				$created_order = $result->getResult()->getOrder();
				return array(
					'success'  => true,
					'order_id' => $created_order->getId(),
				);
			} else {
				$errors = $result->getErrors();
				$error_parts = array();
				if ( ! empty( $errors ) ) {
					foreach ( $errors as $err ) {
						$detail = $err->getDetail() ?? 'Unknown';
						$field  = $err->getField() ?? '';
						$code   = $err->getCode() ?? '';
						$error_parts[] = "[{$code}] {$detail}" . ( $field ? " (field: {$field})" : '' );
					}
				}
				$error_msg = ! empty( $error_parts ) ? implode( '; ', $error_parts ) : 'Unknown error';
				error_log( 'WTG2: Failed to create Square order: ' . $error_msg );
				return array(
					'success' => false,
					'error'   => $error_msg,
				);
			}
		} catch ( Exception $e ) {
			error_log( 'WTG2: Exception creating Square order: ' . $e->getMessage() );
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Get or create Square customer.
	 *
	 * @param array                 $booking Booking data.
	 * @param \Square\SquareClient $client  Square client.
	 * @return string|null Customer ID.
	 */
	private static function get_or_create_customer( $booking, $client ) {
		$customers_api = $client->getCustomersApi();
		$email = $booking['email'] ?? '';

		if ( empty( $email ) ) {
			error_log( 'WTG2: Cannot create customer — no email in booking data.' );
			return null;
		}

		try {
			// Search for existing customer by email.
			$email_filter = new \Square\Models\CustomerTextFilter();
			$email_filter->setExact( $email );

			$customer_filter = new \Square\Models\CustomerFilter();
			$customer_filter->setEmailAddress( $email_filter );

			$query = new \Square\Models\CustomerQuery();
			$query->setFilter( $customer_filter );

			$search_request = new \Square\Models\SearchCustomersRequest();
			$search_request->setQuery( $query );

			$search_result = $customers_api->searchCustomers( $search_request );

			if ( $search_result->isSuccess() ) {
				$customers = $search_result->getResult()->getCustomers();
				if ( ! empty( $customers ) ) {
					return $customers[0]->getId();
				}
			}

			// Customer not found, create new one.
			$create_request = new \Square\Models\CreateCustomerRequest();
			$create_request->setGivenName( $booking['first_name'] ?? '' );
			$create_request->setFamilyName( $booking['last_name'] ?? '' );
			$create_request->setEmailAddress( $email );
			$create_request->setPhoneNumber( $booking['phone'] ?? '' );
			$create_request->setIdempotencyKey( 'wtg-customer-' . md5( $email ) );

			$create_result = $customers_api->createCustomer( $create_request );

			if ( $create_result->isSuccess() ) {
				return $create_result->getResult()->getCustomer()->getId();
			} else {
				$errors = $create_result->getErrors();
				$error_msg = ! empty( $errors ) ? $errors[0]->getDetail() : 'Unknown error';
				error_log( 'WTG2: Failed to create Square customer: ' . $error_msg );
				return null;
			}
		} catch ( Exception $e ) {
			error_log( 'WTG2: Exception in get_or_create_customer: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Get time slot label.
	 *
	 * @param string $slot Database slot value.
	 * @return string Human-readable label.
	 */
	private static function get_time_slot_label( $slot ) {
		$labels = array(
			'sat_am' => 'Saturday 11:00 AM - 4:00 PM',
			'sat_pm' => 'Saturday 5:00 PM - 10:00 PM',
			'fri_pm' => 'Friday 5:00 PM - 10:00 PM',
			'fri_am' => 'Friday 11:00 AM - 4:00 PM',
		);

		return isset( $labels[ $slot ] ) ? $labels[ $slot ] : $slot;
	}
}
