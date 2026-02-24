<?php
/**
 * Gravity Forms Integration
 *
 * Handles integration with Gravity Forms for gift certificates and bookings.
 *
 * @package WTG2
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Gravity Forms integration class.
 */
class WTG_Gravity_Forms {

	/**
	 * Form IDs.
	 */
	const BOOKING_FORM_ID = 8;
	const GIFT_CERT_FORM_ID = 12;

	/**
	 * Booking Form Field IDs (Form 8).
	 */
	const FIELD_TOUR_DATE = 1;
	const FIELD_TIME_SLOT = 2;
	const FIELD_TICKETS = 3;
	const FIELD_FIRST_NAME = 4;
	const FIELD_LAST_NAME = 5;
	const FIELD_EMAIL = 6;
	const FIELD_PHONE = 7;
	const FIELD_GIFT_CERT_CODE = 14;

	/**
	 * Gift Certificate Form Field IDs (Form 9).
	 */
	const FIELD_GC_PURCHASER_NAME = 1;      // Subfields: 1.3 (first), 1.6 (last)
	const FIELD_GC_PURCHASER_EMAIL = 2;
	const FIELD_GC_RECIPIENT_NAME = 3;       // Subfields: 3.3 (first), 3.6 (last)
	const FIELD_GC_RECIPIENT_EMAIL = 4;
	const FIELD_GC_AMOUNT = 9;
	const FIELD_GC_MESSAGE = 17;

	/**
	 * Initialize the integration.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Gift Certificate Form hooks — use entry_post_save so the code exists before notifications fire.
		add_action( 'gform_entry_post_save_' . self::GIFT_CERT_FORM_ID, array( $this, 'process_gift_certificate_purchase' ), 10, 2 );
		add_filter( 'gform_notification_' . self::GIFT_CERT_FORM_ID, array( $this, 'update_gift_cert_notification' ), 10, 3 );

		// Booking Form hooks.
		add_filter( 'gform_validation_' . self::BOOKING_FORM_ID, array( $this, 'validate_booking_form' ) );
		add_action( 'gform_after_submission_' . self::BOOKING_FORM_ID, array( $this, 'process_booking_submission' ), 10, 2 );
		add_filter( 'gform_product_info_' . self::BOOKING_FORM_ID, array( $this, 'apply_gift_cert_discount' ), 5, 3 );

		// AJAX validation for gift certificate codes.
		add_action( 'wp_ajax_wtg_validate_gift_cert', array( $this, 'ajax_validate_gift_cert' ) );
		add_action( 'wp_ajax_nopriv_wtg_validate_gift_cert', array( $this, 'ajax_validate_gift_cert' ) );

		// Field visibility - make Field 14 visible.
		add_filter( 'gform_field_content_' . self::BOOKING_FORM_ID . '_' . self::FIELD_GIFT_CERT_CODE, array( $this, 'ensure_gift_cert_field_visible' ), 10, 5 );

		// Custom field rendering for booking form.
		add_filter( 'gform_field_content_' . self::BOOKING_FORM_ID . '_' . self::FIELD_TOUR_DATE, array( $this, 'render_custom_date_picker' ), 10, 5 );
		add_filter( 'gform_field_content_' . self::BOOKING_FORM_ID . '_' . self::FIELD_TIME_SLOT, array( $this, 'add_seat_grid_container' ), 10, 5 );
	}

	/**
	 * Process gift certificate purchase after form submission.
	 *
	 * @param array $entry The entry that was just created.
	 * @param array $form  The form currently being processed.
	 */
	public function process_gift_certificate_purchase( $entry, $form ) {
		// Generate unique code.
		$code = WTG_Gift_Certificate::generate_unique_code();

		// Get the purchase amount from the payment (most reliable for radio product fields).
		$amount = floatval( rgar( $entry, 'payment_amount' ) );

		// Fallback: extract from GF product info if payment_amount is missing.
		if ( $amount <= 0 ) {
			$products = GFCommon::get_product_fields( $form, $entry );
			if ( ! empty( $products['products'] ) ) {
				foreach ( $products['products'] as $product ) {
					$price    = GFCommon::to_number( $product['price'] );
					$quantity = intval( $product['quantity'] );
					$amount  += $price * $quantity;
				}
			}
		}

		// Concatenate name parts (Field 1: 1.3=first, 1.6=last).
		$purchaser_first = rgar( $entry, '1.3' );
		$purchaser_last = rgar( $entry, '1.6' );
		$purchaser_name = trim( $purchaser_first . ' ' . $purchaser_last );
		$purchaser_email = rgar( $entry, self::FIELD_GC_PURCHASER_EMAIL );

		// Concatenate recipient name parts (Field 3: 3.3=first, 3.6=last).
		$recipient_first = rgar( $entry, '3.3' );
		$recipient_last = rgar( $entry, '3.6' );
		$recipient_name = trim( $recipient_first . ' ' . $recipient_last );
		$recipient_email = rgar( $entry, self::FIELD_GC_RECIPIENT_EMAIL );

		$message = rgar( $entry, self::FIELD_GC_MESSAGE );

		// Create gift certificate record.
		$gift_cert_id = WTG_Gift_Certificate::create(
			array(
				'code'             => $code,
				'gf_entry_id'      => $entry['id'],
				'purchaser_name'   => $purchaser_name,
				'purchaser_email'  => $purchaser_email,
				'recipient_name'   => $recipient_name,
				'recipient_email'  => $recipient_email,
				'amount'           => $amount,
				'message'          => $message,
				'status'           => 'active',
			)
		);

		if ( $gift_cert_id ) {
			// Store the code in entry meta for notification use.
			gform_update_meta( $entry['id'], 'wtg_gift_cert_code', $code );
			gform_update_meta( $entry['id'], 'wtg_gift_cert_id', $gift_cert_id );

			// Log success.
			if ( function_exists( 'error_log' ) ) {
				error_log( sprintf( 'WTG2: Gift certificate %s created for entry %d', $code, $entry['id'] ) );
			}

			// Send emails directly (GF notifications unreliable with Square add-on).
			$gc_data = array(
				'code'            => $code,
				'amount'          => $amount,
				'purchaser_name'  => $purchaser_name,
				'purchaser_email' => $purchaser_email,
				'recipient_name'  => $recipient_name,
				'recipient_email' => $recipient_email,
				'message'         => $message,
			);
			WTG_Email_Templates::send_gift_certificate_purchaser( $gc_data );
			WTG_Email_Templates::send_gift_certificate_recipient( $gc_data );
		}
	}

	/**
	 * Update gift certificate notification to include the actual code.
	 *
	 * @param array $notification The notification object.
	 * @param array $form         The form object.
	 * @param array $entry        The entry object.
	 * @return array Modified notification.
	 */
	public function update_gift_cert_notification( $notification, $form, $entry ) {
		// Guard: entry can be null during admin previews or delayed payment flows.
		if ( empty( $entry ) || ! isset( $entry['id'] ) ) {
			return $notification;
		}

		// Get the gift certificate code from entry meta.
		$code = gform_get_meta( $entry['id'], 'wtg_gift_cert_code' );

		if ( empty( $code ) ) {
			// Fallback: try to get from database by entry ID.
			$gift_cert = WTG_Gift_Certificate::get_by_gf_entry( $entry['id'] );
			if ( $gift_cert ) {
				$code = $gift_cert->code;
			} else {
				$code = 'CODE-NOT-GENERATED';
			}
		}

		// Replace {entry_id} or add custom merge tag {gift_cert_code}.
		$notification['message'] = str_replace( '{entry_id}', $code, $notification['message'] );
		$notification['message'] = str_replace( '{gift_cert_code}', $code, $notification['message'] );
		$notification['subject'] = str_replace( '{entry_id}', $code, $notification['subject'] );
		$notification['subject'] = str_replace( '{gift_cert_code}', $code, $notification['subject'] );

		return $notification;
	}

	/**
	 * Validate booking form (including gift certificate codes).
	 *
	 * @param array $validation_result Contains the form and validation result.
	 * @return array Modified validation result.
	 */
	public function validate_booking_form( $validation_result ) {
		$form = $validation_result['form'];

		// Get form values for availability check.
		$tour_date = rgpost( 'input_' . self::FIELD_TOUR_DATE );
		$time_slot = rgpost( 'input_' . self::FIELD_TIME_SLOT );
		$tickets = absint( rgpost( 'input_' . self::FIELD_TICKETS ) );

		// Validate availability.
		if ( ! empty( $tour_date ) && ! empty( $time_slot ) && $tickets > 0 ) {
			// Convert date format if needed.
			$tour_date_formatted = date( 'Y-m-d', strtotime( $tour_date ) );

			// Convert time slot label to database format.
			$time_slot_db = $this->convert_time_slot_to_db_format( $time_slot );

			// Check availability.
			$availability = WTG_Availability_Controller::check_slot_availability(
				$tour_date_formatted,
				$time_slot_db,
				$tickets
			);

			if ( ! $availability['available'] ) {
				// Find the time slot field and mark it as failed.
				foreach ( $form['fields'] as &$field ) {
					if ( $field->id == self::FIELD_TIME_SLOT ) {
						$field->failed_validation = true;
						$field->validation_message = $availability['reason'];
						$validation_result['is_valid'] = false;
						break;
					}
				}
			}
		}

		// Validate gift certificate code if provided.
		foreach ( $form['fields'] as &$field ) {
			if ( $field->id == self::FIELD_GIFT_CERT_CODE ) {
				$code = rgpost( 'input_' . self::FIELD_GIFT_CERT_CODE );

				// Only validate if code was entered.
				if ( ! empty( $code ) ) {
					$validation = WTG_Gift_Certificate::validate_code( $code );

					if ( ! $validation['valid'] ) {
						$field->failed_validation = true;
						$field->validation_message = $validation['message'];
						$validation_result['is_valid'] = false;
					}
				}

				break;
			}
		}

		$validation_result['form'] = $form;
		return $validation_result;
	}

	/**
	 * Process booking submission after validation.
	 *
	 * @param array $entry The entry that was just created.
	 * @param array $form  The form currently being processed.
	 */
	public function process_booking_submission( $entry, $form ) {
		// Get form field values.
		$tour_date = rgar( $entry, self::FIELD_TOUR_DATE );
		$time_slot = rgar( $entry, self::FIELD_TIME_SLOT );
		$tickets = absint( rgar( $entry, self::FIELD_TICKETS ) );
		$first_name = rgar( $entry, self::FIELD_FIRST_NAME );
		$last_name = rgar( $entry, self::FIELD_LAST_NAME );
		$email = rgar( $entry, self::FIELD_EMAIL );
		$phone = rgar( $entry, self::FIELD_PHONE );
		$gift_cert_code = rgar( $entry, self::FIELD_GIFT_CERT_CODE );

		// Convert date format if needed (GF might use different format).
		$tour_date = date( 'Y-m-d', strtotime( $tour_date ) );

		// Convert time slot to database format.
		$time_slot = $this->convert_time_slot_to_db_format( $time_slot );

		// Combine name for admin display.
		$customer_name = trim( $first_name . ' ' . $last_name );

		// Calculate pricing.
		$deposit_per_ticket = 35.00;
		$balance_per_ticket = 130.00;
		$tax_rate           = 0.0825;
		$total_per_ticket   = $deposit_per_ticket + $balance_per_ticket; // $165.00

		$deposit_amount   = $deposit_per_ticket * $tickets;
		$balance_pretax   = $balance_per_ticket * $tickets;
		$tax_amount       = round( $total_per_ticket * $tickets * $tax_rate, 2 );
		$total_amount     = $deposit_amount + $balance_pretax + $tax_amount;
		$balance_due      = $balance_pretax; // Pre-tax only; tax is added separately on the invoice.
		$discount_applied = 0.00;
		$gift_cert_id     = null;

		// Apply gift certificate if provided.
		if ( ! empty( $gift_cert_code ) ) {
			$gift_cert = WTG_Gift_Certificate::get_by_code( $gift_cert_code );
			if ( $gift_cert && 'active' === $gift_cert->status ) {
				$gift_cert_id     = $gift_cert->id;
				$discount_applied = floatval( $gift_cert->amount );

				// Apply cert to deposit first, remainder to balance, remainder to tax.
				$deposit_discount = min( $discount_applied, $deposit_amount );
				$remaining_credit = $discount_applied - $deposit_discount;
				$balance_discount = min( $remaining_credit, $balance_pretax );
				$remaining_credit = $remaining_credit - $balance_discount;
				$tax_discount     = min( $remaining_credit, $tax_amount );

				$deposit_amount = $deposit_amount - $deposit_discount;
				$balance_due    = max( 0, $balance_pretax - $balance_discount );
				$tax_amount     = max( 0, $tax_amount - $tax_discount );

				// Prevent rounding dust (sub-cent) from creating amounts.
				if ( $deposit_amount < 0.01 ) {
					$deposit_amount = 0.00;
				}
				if ( $balance_due < 0.01 ) {
					$balance_due = 0.00;
				}
				if ( $tax_amount < 0.01 ) {
					$tax_amount = 0.00;
				}

				$total_amount = $deposit_amount + $balance_due + $tax_amount;
			}
		}

		// Determine payment status.
		$payment_status = 'pending';
		if ( $deposit_amount <= 0 && $balance_due <= 0 && $tax_amount <= 0 ) {
			// Gift cert covers everything (deposit + balance + tax).
			$payment_status = 'paid_full';
		} elseif ( $deposit_amount <= 0 && $balance_due > 0 ) {
			// Gift cert covers the deposit; GF/Square did not charge.
			$payment_status = 'deposit_paid';
		} elseif ( ! empty( $entry['payment_status'] ) && 'Paid' === $entry['payment_status'] ) {
			// GF/Square charged a (possibly reduced) deposit.
			$payment_status = 'deposit_paid';
		}

		// Create booking record.
		$booking_id = WTG_Booking::create(
			array(
				'gf_entry_id'      => $entry['id'],
				'tour_date'        => $tour_date,
				'time_slot'        => $time_slot,
				'tickets'          => $tickets,
				'customer_name'    => $customer_name,
				'customer_email'   => $email,
				'customer_phone'   => $phone,
				'total_amount'     => $total_amount,
				'deposit_amount'   => $deposit_amount,
				'balance_due'      => $balance_due,
				'payment_status'   => $payment_status,
				'gift_cert_id'     => $gift_cert_id,
				'discount_applied' => $discount_applied,
			)
		);

		if ( $booking_id ) {
			// Redeem the gift certificate.
			if ( ! empty( $gift_cert_code ) ) {
				WTG_Gift_Certificate::redeem( $gift_cert_code, $booking_id );
			}

			// Store booking ID in entry meta.
			gform_update_meta( $entry['id'], 'wtg_booking_id', $booking_id );

			// Log success.
			if ( function_exists( 'error_log' ) ) {
				error_log( sprintf( 'WTG2: Booking %d created for entry %d', $booking_id, $entry['id'] ) );
			}

			// Deposit is collected directly via Gravity Forms Square add-on — no separate invoice needed.
			$booking = WTG_Booking::get_by_id( $booking_id );
			if ( $booking ) {
				// Only create a balance invoice if there's something to charge.
				if ( 'paid_full' !== $payment_status ) {
					$invoice_result = WTG_Square_Invoice::create_balance_invoice( $booking_id, $booking );

					if ( $invoice_result['success'] ) {
						WTG_Booking::update(
							$booking_id,
							array( 'balance_square_id' => $invoice_result['invoice_id'] )
						);

						error_log( sprintf(
							'WTG2: Draft balance invoice %s created for booking %d',
							$invoice_result['invoice_id'],
							$booking_id
						) );
					} else {
						error_log( sprintf(
							'WTG2: Failed to create draft invoice for booking %d: %s',
							$booking_id,
							$invoice_result['error']
						) );
					}
				} else {
					error_log( sprintf( 'WTG2: Booking %d fully paid by gift certificate — no invoice needed.', $booking_id ) );
				}

				// Send confirmation email.
				if ( 'paid_full' === $payment_status ) {
					WTG_Email_Templates::send_balance_confirmation( $booking );
				} elseif ( 'deposit_paid' === $payment_status ) {
					WTG_Email_Templates::send_deposit_confirmation( $booking );
				}
			}
		}
	}

	/**
	 * Convert time slot label to database format.
	 *
	 * @param string $label Time slot label from form.
	 * @return string Database format (fri_am, fri_pm, sat_am, sat_pm).
	 */
	private function convert_time_slot_to_db_format( $label ) {
		$label = strtolower( trim( $label ) );

		$map = array(
			'friday am'      => 'fri_am',
			'friday pm'      => 'fri_pm',
			'saturday am'    => 'sat_am',
			'saturday pm'    => 'sat_pm',
			'fri am'         => 'fri_am',
			'fri pm'         => 'fri_pm',
			'sat am'         => 'sat_am',
			'sat pm'         => 'sat_pm',
		);

		return isset( $map[ $label ] ) ? $map[ $label ] : $label;
	}

	/**
	 * AJAX handler to validate gift certificate codes.
	 */
	public function ajax_validate_gift_cert() {
		// Check nonce.
		check_ajax_referer( 'wtg_validate_gc', 'nonce' );

		$code = isset( $_POST['code'] ) ? sanitize_text_field( $_POST['code'] ) : '';

		if ( empty( $code ) ) {
			wp_send_json_error( array( 'message' => 'Please enter a gift certificate code.' ) );
		}

		$validation = WTG_Gift_Certificate::validate_code( $code );

		if ( $validation['valid'] ) {
			wp_send_json_success(
				array(
					'message' => $validation['message'],
					'amount'  => number_format( $validation['cert']->amount, 2 ),
				)
			);
		} else {
			wp_send_json_error( array(
				'message' => $validation['message'],
			) );
		}
	}

	/**
	 * Ensure gift certificate field is visible (override administrative visibility).
	 *
	 * @param string $content The field content.
	 * @param object $field   The field object.
	 * @param string $value   The field value.
	 * @param int    $lead_id The entry ID.
	 * @param int    $form_id The form ID.
	 * @return string Modified field content.
	 */
	public function ensure_gift_cert_field_visible( $content, $field, $value, $lead_id, $form_id ) {
		// Remove any hidden/administrative CSS classes if present.
		$content = str_replace( 'gfield_visibility_administrative', 'gfield_visibility_visible', $content );

		return $content;
	}

	/**
	 * Apply gift certificate discount to GF product info.
	 *
	 * Injects a negative-priced line item when a valid gift cert code is submitted,
	 * reducing the GF total (and thus the Square charge at checkout).
	 *
	 * @param array $product_info Product info array with 'products' key.
	 * @param array $form         The form object.
	 * @param array $entry        The entry object.
	 * @return array Modified product info.
	 */
	public function apply_gift_cert_discount( $product_info, $form, $entry ) {
		$gift_cert_code = rgpost( 'input_' . self::FIELD_GIFT_CERT_CODE );

		if ( empty( $gift_cert_code ) ) {
			return $product_info;
		}

		$gift_cert = WTG_Gift_Certificate::get_by_code( $gift_cert_code );

		if ( ! $gift_cert || 'active' !== $gift_cert->status ) {
			return $product_info;
		}

		// Calculate current product total (the deposit).
		$current_total = 0;
		foreach ( $product_info['products'] as $product ) {
			$price    = GFCommon::to_number( $product['price'] );
			$quantity = intval( $product['quantity'] );
			$current_total += $price * $quantity;
		}

		if ( $current_total <= 0 ) {
			return $product_info;
		}

		$cert_amount = floatval( $gift_cert->amount );

		// Cap the discount at the deposit total so GF total never goes negative.
		$deposit_discount = min( $cert_amount, $current_total );

		$product_info['products'][ self::FIELD_GIFT_CERT_CODE . '|gift_cert' ] = array(
			'name'     => 'Gift Certificate (' . strtoupper( trim( $gift_cert_code ) ) . ')',
			'price'    => -$deposit_discount,
			'quantity' => 1,
			'options'  => array(),
		);

		return $product_info;
	}

	/**
	 * Render custom date picker dropdown for tour date field.
	 *
	 * @param string $content The field content.
	 * @param object $field   The field object.
	 * @param string $value   The field value.
	 * @param int    $lead_id The entry ID.
	 * @param int    $form_id The form ID.
	 * @return string Modified field content with custom date picker.
	 */
	public function render_custom_date_picker( $content, $field, $value, $lead_id, $form_id ) {
		// Generate custom date picker dropdown.
		$date_picker_html = WTG_Date_Picker_Controller::render_date_picker(
			$form_id,
			$field->id,
			$value
		);

		// Get field label and description.
		$field_label = $field->label;
		$field_description = $field->description;
		$is_required = $field->isRequired;
		$css_class = $field->cssClass;

		// Build the complete field HTML.
		$custom_content = sprintf(
			'<div class="ginput_container ginput_container_date">%s</div>',
			$date_picker_html
		);

		return $custom_content;
	}

	/**
	 * Add seat grid container after time slot field.
	 *
	 * @param string $content The field content.
	 * @param object $field   The field object.
	 * @param string $value   The field value.
	 * @param int    $lead_id The entry ID.
	 * @param int    $form_id The form ID.
	 * @return string Modified field content with seat grid container.
	 */
	public function add_seat_grid_container( $content, $field, $value, $lead_id, $form_id ) {
		// Add seat grid container after the time slot field.
		$content .= '<div id="wtg-seat-grid-container" class="wtg-grid-wrapper" style="margin-top: 20px;"></div>';

		return $content;
	}
}
