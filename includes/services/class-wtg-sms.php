<?php
/**
 * SMS Service
 *
 * Sends SMS messages via Twilio REST API using wp_remote_post().
 * No SDK required — credentials stored as WP options.
 *
 * @package WTG2
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * SMS service class.
 */
class WTG_SMS {

	/**
	 * Send an SMS via Twilio.
	 *
	 * @param string $to      Destination phone number (E.164 or 10-digit US).
	 * @param string $message Message body.
	 * @return array ['success' => bool, 'sid' => string, 'error' => string]
	 */
	public static function send( $to, $message ) {
		$account_sid = get_option( 'wtg_twilio_account_sid', '' );
		$auth_token  = get_option( 'wtg_twilio_auth_token', '' );
		$from_number = get_option( 'wtg_twilio_from_number', '' );

		if ( empty( $account_sid ) || empty( $auth_token ) || empty( $from_number ) ) {
			error_log( 'WTG2 SMS: Twilio credentials not configured.' );
			return array( 'success' => false, 'error' => 'Twilio credentials not configured.' );
		}

		// Normalize to E.164 — strip non-digits except leading +, assume US if no country code.
		$to = preg_replace( '/[^\d+]/', '', $to );
		if ( substr( $to, 0, 1 ) !== '+' ) {
			$to = '+1' . $to;
		}

		$url = "https://api.twilio.com/2010-04-01/Accounts/{$account_sid}/Messages.json";

		$response = wp_remote_post( $url, array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $account_sid . ':' . $auth_token ),
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
			'body'    => array(
				'From' => $from_number,
				'To'   => $to,
				'Body' => $message,
			),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			error_log( 'WTG2 SMS: wp_remote_post error: ' . $response->get_error_message() );
			return array( 'success' => false, 'error' => $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 300 ) {
			return array( 'success' => true, 'sid' => $body['sid'] ?? '' );
		}

		$error = $body['message'] ?? 'Unknown Twilio error';
		error_log( "WTG2 SMS: Twilio error (HTTP {$code}): {$error}" );
		return array( 'success' => false, 'error' => $error );
	}

	/**
	 * Send the 24-hour balance reminder SMS for a booking.
	 *
	 * @param array $booking Booking row from DB.
	 * @return array ['success' => bool, 'error' => string]
	 */
	public static function send_balance_reminder( $booking ) {
		$phone = $booking['phone'] ?? '';

		if ( empty( $phone ) ) {
			return array( 'success' => false, 'error' => 'No phone number on booking.' );
		}

		$invoice_url = $booking['invoice_url'] ?? '';
		$first_name  = $booking['first_name'] ?? 'there';
		$time_label  = self::get_time_label( $booking['time_slot'] ?? '' );

		$message = "Hey {$first_name}! Don't miss the bus — your Wine Tours Grapevine tour is TOMORROW at {$time_label} and your balance invoice is still due. Pay here: {$invoice_url} 🍷";

		return self::send( $phone, $message );
	}

	/**
	 * Send a test SMS to verify Twilio credentials.
	 *
	 * @param string $to Destination phone number.
	 * @return array ['success' => bool, 'error' => string]
	 */
	public static function send_test( $to ) {
		$message = "Test from Wine Tours Grapevine: Hey there! Don't miss the bus — your Wine Tours Grapevine tour is TOMORROW at 11:00 AM and your balance invoice is still due. Pay here: https://squareup.com/pay-invoice/SAMPLE 🍷";
		return self::send( $to, $message );
	}

	/**
	 * Get human-readable time label for a slot.
	 *
	 * @param string $slot DB slot value.
	 * @return string
	 */
	private static function get_time_label( $slot ) {
		$labels = array(
			'sat_am' => '11:00 AM',
			'sat_pm' => '5:00 PM',
			'fri_am' => '11:00 AM',
			'fri_pm' => '5:00 PM',
		);
		return $labels[ $slot ] ?? $slot;
	}
}
