<?php
/**
 * Square Invoice Service
 *
 * Handles Square invoice creation and management.
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
				'environment' => 'production' === $environment ?
					\Square\Environment::PRODUCTION :
					\Square\Environment::SANDBOX,
			]);
		} catch ( Exception $e ) {
			error_log( 'WTG2: Failed to create Square client: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Create deposit invoice immediately after booking.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $booking    Booking data.
	 * @return array ['success' => bool, 'invoice_id' => string, 'error' => string]
	 */
	public static function create_deposit_invoice( $booking_id, $booking ) {
		$client = self::get_client();

		if ( ! $client ) {
			return array(
				'success' => false,
				'error'   => 'Square client not configured',
			);
		}

		try {
			// Get or create customer.
			$customer_id = self::get_or_create_customer( $booking, $client );

			if ( ! $customer_id ) {
				return array(
					'success' => false,
					'error'   => 'Failed to create/retrieve Square customer',
				);
			}

			// Build line items.
			$line_items = self::build_line_items( $booking, 'deposit' );

			// Get location ID.
			$environment = get_option( 'wtg_square_environment', 'sandbox' );
			$location_id = get_option( "wtg_square_{$environment}_location_id", '' );

			if ( empty( $location_id ) ) {
				return array(
					'success' => false,
					'error'   => 'Square location ID not configured',
				);
			}

			// Create invoice.
			$invoice_api = $client->getInvoicesApi();

			$body = new \Square\Models\CreateInvoiceRequest(
				new \Square\Models\Invoice([
					'locationId'      => $location_id,
					'customerId'      => $customer_id,
					'paymentRequests' => [
						new \Square\Models\InvoicePaymentRequest([
							'requestType'   => 'DEPOSIT',
							'dueDate'       => date( 'Y-m-d' ),
							'automaticPaymentSource' => 'NONE',
						]),
					],
					'deliveryMethod'  => 'EMAIL',
					'invoiceNumber'   => $booking['booking_code'] ?? 'WTG-' . $booking_id,
					'title'           => 'Wine Tours Grapevine - Deposit',
					'description'     => sprintf(
						'Deposit for tour on %s at %s',
						date( 'F j, Y', strtotime( $booking['tour_date'] ) ),
						self::get_time_slot_label( $booking['time_slot'] )
					),
					'primaryRecipient' => new \Square\Models\InvoiceRecipient([
						'customerId' => $customer_id,
					]),
				])
			);

			// Add line items.
			foreach ( $line_items as $line_item ) {
				$body->getInvoice()->setPrimaryRecipient(
					$body->getInvoice()->getPrimaryRecipient()
				);
			}

			$result = $invoice_api->createInvoice( $body );

			if ( $result->isSuccess() ) {
				$invoice = $result->getBody()->getInvoice();

				// Publish the invoice.
				$publish_result = $invoice_api->publishInvoice(
					$invoice->getId(),
					new \Square\Models\PublishInvoiceRequest(
						$invoice->getVersion()
					)
				);

				if ( $publish_result->isSuccess() ) {
					return array(
						'success'    => true,
						'invoice_id' => $invoice->getId(),
					);
				} else {
					return array(
						'success' => false,
						'error'   => 'Failed to publish invoice: ' . implode( ', ', $publish_result->getErrors() ),
					);
				}
			} else {
				$errors = $result->getErrors();
				return array(
					'success' => false,
					'error'   => 'Failed to create invoice: ' . ( ! empty( $errors ) ? $errors[0]->getDetail() : 'Unknown error' ),
				);
			}
		} catch ( Exception $e ) {
			error_log( 'WTG2: Exception in create_deposit_invoice: ' . $e->getMessage() );
			return array(
				'success' => false,
				'error'   => 'Exception: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Create balance invoice (scheduled or manual).
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $booking    Booking data.
	 * @return array ['success' => bool, 'invoice_id' => string, 'invoice_url' => string, 'error' => string]
	 */
	public static function create_balance_invoice( $booking_id, $booking ) {
		$client = self::get_client();

		if ( ! $client ) {
			return array(
				'success' => false,
				'error'   => 'Square client not configured',
			);
		}

		try {
			// Get or create customer.
			$customer_id = self::get_or_create_customer( $booking, $client );

			if ( ! $customer_id ) {
				return array(
					'success' => false,
					'error'   => 'Failed to create/retrieve Square customer',
				);
			}

			// Build line items.
			$line_items = self::build_line_items( $booking, 'balance' );

			// Get location ID.
			$environment = get_option( 'wtg_square_environment', 'sandbox' );
			$location_id = get_option( "wtg_square_{$environment}_location_id", '' );

			if ( empty( $location_id ) ) {
				return array(
					'success' => false,
					'error'   => 'Square location ID not configured',
				);
			}

			// Create invoice.
			$invoice_api = $client->getInvoicesApi();

			$body = new \Square\Models\CreateInvoiceRequest(
				new \Square\Models\Invoice([
					'locationId'      => $location_id,
					'customerId'      => $customer_id,
					'paymentRequests' => [
						new \Square\Models\InvoicePaymentRequest([
							'requestType'   => 'BALANCE',
							'dueDate'       => $booking['tour_date'],
							'automaticPaymentSource' => 'NONE',
							'reminders'     => [
								new \Square\Models\InvoicePaymentReminder([
									'relativeScheduledDays' => -1,
									'message'               => 'Reminder: Your wine tour balance payment is due tomorrow.',
								]),
							],
						]),
					],
					'deliveryMethod'  => 'EMAIL',
					'invoiceNumber'   => $booking['booking_code'] ?? 'WTG-' . $booking_id,
					'title'           => 'Wine Tours Grapevine - Balance Due',
					'description'     => sprintf(
						'Balance payment for tour on %s at %s',
						date( 'F j, Y', strtotime( $booking['tour_date'] ) ),
						self::get_time_slot_label( $booking['time_slot'] )
					),
					'primaryRecipient' => new \Square\Models\InvoiceRecipient([
						'customerId' => $customer_id,
					]),
				])
			);

			$result = $invoice_api->createInvoice( $body );

			if ( $result->isSuccess() ) {
				$invoice = $result->getBody()->getInvoice();

				// Publish the invoice.
				$publish_result = $invoice_api->publishInvoice(
					$invoice->getId(),
					new \Square\Models\PublishInvoiceRequest(
						$invoice->getVersion()
					)
				);

				if ( $publish_result->isSuccess() ) {
					$published_invoice = $publish_result->getBody()->getInvoice();
					return array(
						'success'     => true,
						'invoice_id'  => $published_invoice->getId(),
						'invoice_url' => $published_invoice->getPublicUrl() ?? '',
					);
				} else {
					return array(
						'success' => false,
						'error'   => 'Failed to publish invoice: ' . implode( ', ', $publish_result->getErrors() ),
					);
				}
			} else {
				$errors = $result->getErrors();
				return array(
					'success' => false,
					'error'   => 'Failed to create invoice: ' . ( ! empty( $errors ) ? $errors[0]->getDetail() : 'Unknown error' ),
				);
			}
		} catch ( Exception $e ) {
			error_log( 'WTG2: Exception in create_balance_invoice: ' . $e->getMessage() );
			return array(
				'success' => false,
				'error'   => 'Exception: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Build invoice line items.
	 *
	 * @param array  $booking      Booking data.
	 * @param string $invoice_type 'deposit' or 'balance'.
	 * @return array Line items array.
	 */
	private static function build_line_items( $booking, $invoice_type ) {
		$tickets = $booking['tickets'] ?? 1;

		if ( 'deposit' === $invoice_type ) {
			// Use the stored deposit_amount (already reflects any gift cert discount).
			$total_amount = floatval( $booking['deposit_amount'] ?? ( 35.00 * $tickets ) );
			$description  = sprintf(
				'Tour Deposit - %d ticket%s',
				$tickets,
				$tickets > 1 ? 's' : ''
			);

			$line_items = array(
				array(
					'name'             => $description,
					'quantity'         => '1',
					'item_type'        => 'ITEM',
					'base_price_money' => array(
						'amount'   => (int) round( $total_amount * 100 ),
						'currency' => 'USD',
					),
				),
			);
		} else {
			// Balance invoice: use stored balance_due (already reflects gift cert discount + tax).
			$balance_due     = floatval( $booking['balance_due'] ?? ( 130.00 * $tickets ) );
			$gross_balance   = 130.00 * $tickets;
			$balance_discount = $gross_balance - $balance_due;
			$description     = sprintf(
				'Tour Balance - %d ticket%s',
				$tickets,
				$tickets > 1 ? 's' : ''
			);

			if ( $balance_discount > 0 && $balance_discount < $gross_balance ) {
				// Partial discount: show gross balance with a discount line item.
				$line_items = array(
					array(
						'name'             => $description,
						'quantity'         => (string) $tickets,
						'item_type'        => 'ITEM',
						'base_price_money' => array(
							'amount'   => (int) ( 130.00 * 100 ), // $130 per ticket.
							'currency' => 'USD',
						),
					),
					array(
						'name'             => 'Gift Certificate Credit',
						'quantity'         => '1',
						'item_type'        => 'ITEM',
						'base_price_money' => array(
							'amount'   => (int) round( $balance_discount * -100 ),
							'currency' => 'USD',
						),
					),
				);
			} else {
				// No discount or full discount: single line item with the actual balance.
				$line_items = array(
					array(
						'name'             => $description,
						'quantity'         => '1',
						'item_type'        => 'ITEM',
						'base_price_money' => array(
							'amount'   => (int) round( $balance_due * 100 ),
							'currency' => 'USD',
						),
					),
				);
			}
		}

		return $line_items;
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
			return null;
		}

		try {
			// Search for existing customer by email.
			$search_result = $customers_api->searchCustomers(
				new \Square\Models\SearchCustomersRequest([
					'query' => new \Square\Models\CustomerQuery([
						'filter' => new \Square\Models\CustomerFilter([
							'emailAddress' => new \Square\Models\CustomerTextFilter([
								'exact' => $email,
							]),
						]),
					]),
				])
			);

			if ( $search_result->isSuccess() && ! empty( $search_result->getBody()->getCustomers() ) ) {
				$customer = $search_result->getBody()->getCustomers()[0];
				return $customer->getId();
			}

			// Customer not found, create new one.
			$create_result = $customers_api->createCustomer(
				new \Square\Models\CreateCustomerRequest([
					'givenName'    => $booking['first_name'] ?? '',
					'familyName'   => $booking['last_name'] ?? '',
					'emailAddress' => $email,
					'phoneNumber'  => $booking['phone'] ?? '',
					'note'         => 'Wine Tours Grapevine customer',
				])
			);

			if ( $create_result->isSuccess() ) {
				$customer = $create_result->getBody()->getCustomer();
				return $customer->getId();
			} else {
				error_log( 'WTG2: Failed to create Square customer: ' . implode( ', ', $create_result->getErrors() ) );
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
