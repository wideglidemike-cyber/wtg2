<?php
/**
 * Square Webhook Handler
 *
 * Handles Square webhook notifications for payment events.
 *
 * @package WTG2
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Square webhook class.
 */
class WTG_Square_Webhook {

	/**
	 * Register REST API routes.
	 */
	public static function register_routes() {
		register_rest_route( 'wtg/v1', '/square/webhook', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'handle_webhook' ),
			'permission_callback' => array( __CLASS__, 'verify_signature' ),
		) );
	}

	/**
	 * Verify webhook signature (HMAC SHA256).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	public static function verify_signature( $request ) {
		$signature = $request->get_header( 'x-square-hmacsha256-signature' );
		$body = $request->get_body();
		$webhook_signature_key = get_option( 'wtg_square_webhook_signature', '' );

		if ( empty( $signature ) || empty( $webhook_signature_key ) ) {
			self::log_webhook_event( 'signature_error', array(
				'error' => 'Missing signature or webhook key',
			) );
			return new WP_Error( 'invalid_signature', 'Missing signature or key', array( 'status' => 401 ) );
		}

		// Construct signature string: URL + body.
		$url = rest_url( 'wtg/v1/square/webhook' );
		$string_to_sign = $url . $body;

		// Calculate HMAC.
		$calculated_signature = base64_encode(
			hash_hmac( 'sha256', $string_to_sign, $webhook_signature_key, true )
		);

		if ( ! hash_equals( $signature, $calculated_signature ) ) {
			self::log_webhook_event( 'signature_mismatch', array(
				'received'   => $signature,
				'calculated' => $calculated_signature,
			) );
			return new WP_Error( 'invalid_signature', 'Signature mismatch', array( 'status' => 401 ) );
		}

		return true;
	}

	/**
	 * Handle webhook event.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public static function handle_webhook( $request ) {
		$body = json_decode( $request->get_body(), true );

		if ( ! isset( $body['type'] ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid payload' ), 400 );
		}

		// Log all webhook events.
		self::log_webhook_event( $body['type'], $body );

		// Handle different event types.
		switch ( $body['type'] ) {
			case 'invoice.payment_made':
				self::handle_payment_made( $body['data'] ?? array() );
				break;

			case 'invoice.updated':
				self::handle_invoice_updated( $body['data'] ?? array() );
				break;

			case 'payment.updated':
				self::handle_payment_updated( $body['data'] ?? array() );
				break;

			default:
				// Unhandled event type - just log it.
				break;
		}

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Handle payment made event.
	 *
	 * Updates booking payment status and sends confirmation email.
	 *
	 * @param array $data Event data.
	 */
	private static function handle_payment_made( $data ) {
		if ( empty( $data['object']['invoice']['id'] ) ) {
			return;
		}

		$invoice_id = $data['object']['invoice']['id'];

		// Find booking by invoice_square_id.
		global $wpdb;
		$table = $wpdb->prefix . 'wtg_bookings';

		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE invoice_square_id = %s",
				$invoice_id
			),
			ARRAY_A
		);

		if ( ! $booking ) {
			error_log( 'WTG2: Booking not found for invoice ID: ' . $invoice_id );
			return;
		}

		// Determine new payment status based on current status.
		$new_status = '';
		if ( 'pending' === $booking['payment_status'] ) {
			$new_status = 'deposit_paid';
		} elseif ( 'deposit_paid' === $booking['payment_status'] ) {
			$new_status = 'paid_full';
		}

		if ( empty( $new_status ) ) {
			// Already in final state or unexpected transition.
			return;
		}

		// Update booking payment status.
		require_once WTG2_PLUGIN_DIR . 'includes/models/class-wtg-booking.php';
		WTG_Booking::update( $booking['id'], array(
			'payment_status' => $new_status,
		) );

		// Send confirmation email.
		require_once WTG2_PLUGIN_DIR . 'includes/emails/class-wtg-email-templates.php';

		if ( 'deposit_paid' === $new_status ) {
			WTG_Email_Templates::send_deposit_confirmation( $booking );
		} elseif ( 'paid_full' === $new_status ) {
			WTG_Email_Templates::send_balance_confirmation( $booking );
		}

		error_log(
			sprintf(
				'WTG2: Payment received for booking %s. Status updated to: %s',
				$booking['booking_code'],
				$new_status
			)
		);
	}

	/**
	 * Handle invoice updated event.
	 *
	 * @param array $data Event data.
	 */
	private static function handle_invoice_updated( $data ) {
		// Track invoice status changes for monitoring.
		// Currently just logged, but could be extended for admin notifications.
		if ( ! empty( $data['object']['invoice'] ) ) {
			$invoice = $data['object']['invoice'];
			error_log(
				sprintf(
					'WTG2: Invoice %s updated. Status: %s',
					$invoice['id'] ?? 'unknown',
					$invoice['status'] ?? 'unknown'
				)
			);
		}
	}

	/**
	 * Handle payment updated event.
	 *
	 * @param array $data Event data.
	 */
	private static function handle_payment_updated( $data ) {
		// Handle payment status changes.
		// This can capture refunds and other payment updates.
		if ( ! empty( $data['object']['payment'] ) ) {
			$payment = $data['object']['payment'];

			// Check if payment was refunded.
			if ( isset( $payment['status'] ) && 'COMPLETED' === $payment['status'] ) {
				// Normal payment completion - handled by invoice.payment_made.
				return;
			}

			if ( isset( $payment['status'] ) && 'CANCELED' === $payment['status'] ) {
				// Payment was canceled/refunded.
				// Find booking and update status if needed.
				self::handle_refund( $payment );
			}
		}
	}

	/**
	 * Handle refund.
	 *
	 * @param array $payment Payment data.
	 */
	private static function handle_refund( $payment ) {
		if ( empty( $payment['invoice_id'] ) ) {
			return;
		}

		$invoice_id = $payment['invoice_id'];

		// Find booking by invoice_square_id.
		global $wpdb;
		$table = $wpdb->prefix . 'wtg_bookings';

		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE invoice_square_id = %s",
				$invoice_id
			),
			ARRAY_A
		);

		if ( ! $booking ) {
			return;
		}

		// Update booking to refunded status.
		require_once WTG2_PLUGIN_DIR . 'includes/models/class-wtg-booking.php';
		WTG_Booking::update( $booking['id'], array(
			'payment_status' => 'refunded',
		) );

		error_log(
			sprintf(
				'WTG2: Refund processed for booking %s',
				$booking['booking_code']
			)
		);
	}

	/**
	 * Log webhook event.
	 *
	 * @param string $event_type Event type.
	 * @param array  $data       Event data.
	 */
	private static function log_webhook_event( $event_type, $data ) {
		if ( function_exists( 'error_log' ) ) {
			error_log(
				sprintf(
					'WTG2 Square Webhook [%s]: %s',
					$event_type,
					wp_json_encode( $data )
				)
			);
		}
	}
}
