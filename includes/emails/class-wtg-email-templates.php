<?php
/**
 * Email Templates
 *
 * Handles sending email notifications to customers.
 *
 * @package WTG2
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Email templates class.
 */
class WTG_Email_Templates {

	/**
	 * Send deposit payment confirmation.
	 *
	 * @param array $booking Booking data.
	 */
	public static function send_deposit_confirmation( $booking ) {
		$to = $booking['email'];
		$subject = sprintf(
			'Deposit Confirmed - Wine Tours Grapevine Booking %s',
			$booking['booking_code']
		);

		$message = self::get_email_template( 'deposit-confirmation', array(
			'customer_name'  => $booking['first_name'] . ' ' . $booking['last_name'],
			'booking_code'   => $booking['booking_code'],
			'tour_date'      => date( 'F j, Y', strtotime( $booking['tour_date'] ) ),
			'time_slot'      => self::get_time_slot_label( $booking['time_slot'] ),
			'tickets'        => $booking['tickets'],
			'deposit_amount' => '$' . number_format( floatval( $booking['deposit_amount'] ), 2 ),
			'balance_due'    => '$' . number_format( floatval( $booking['balance_due'] ), 2 ),
		) );

		self::send_email( $to, $subject, $message );
	}

	/**
	 * Send balance payment confirmation.
	 *
	 * @param array $booking Booking data.
	 */
	public static function send_balance_confirmation( $booking ) {
		$to = $booking['email'];
		$subject = sprintf(
			'Payment Complete - Wine Tours Grapevine Booking %s',
			$booking['booking_code']
		);

		$message = self::get_email_template( 'balance-confirmation', array(
			'customer_name'  => $booking['first_name'] . ' ' . $booking['last_name'],
			'booking_code'   => $booking['booking_code'],
			'tour_date'      => date( 'F j, Y', strtotime( $booking['tour_date'] ) ),
			'time_slot'      => self::get_time_slot_label( $booking['time_slot'] ),
			'tickets'        => $booking['tickets'],
			'total_paid'     => '$' . number_format( floatval( $booking['deposit_amount'] ) + floatval( $booking['balance_due'] ), 2 ),
		) );

		self::send_email( $to, $subject, $message );
	}

	/**
	 * Send balance invoice reminder.
	 *
	 * @param array  $booking     Booking data.
	 * @param string $invoice_url Square invoice URL.
	 */
	public static function send_balance_invoice( $booking, $invoice_url = '' ) {
		$to = $booking['email'];
		$subject = sprintf(
			'Balance Due - Wine Tours Grapevine Booking %s',
			$booking['booking_code']
		);

		$message = self::get_email_template( 'balance-invoice', array(
			'customer_name' => $booking['first_name'] . ' ' . $booking['last_name'],
			'booking_code'  => $booking['booking_code'],
			'tour_date'     => date( 'F j, Y', strtotime( $booking['tour_date'] ) ),
			'time_slot'     => self::get_time_slot_label( $booking['time_slot'] ),
			'tickets'       => $booking['tickets'],
			'balance_due'   => '$' . number_format( floatval( $booking['balance_due'] ), 2 ),
			'invoice_url'   => $invoice_url,
		) );

		self::send_email( $to, $subject, $message );
	}

	/**
	 * Get email template.
	 *
	 * @param string $template Template name.
	 * @param array  $vars     Template variables.
	 * @return string HTML email content.
	 */
	private static function get_email_template( $template, $vars ) {
		ob_start();
		extract( $vars );
		$template_file = WTG2_PLUGIN_DIR . "includes/emails/templates/{$template}.php";

		if ( file_exists( $template_file ) ) {
			include $template_file;
		} else {
			// Fallback to plain text if template not found.
			echo '<p>Thank you for your booking with Wine Tours Grapevine.</p>';
			foreach ( $vars as $key => $value ) {
				echo '<p><strong>' . esc_html( ucwords( str_replace( '_', ' ', $key ) ) ) . ':</strong> ' . esc_html( $value ) . '</p>';
			}
		}

		return ob_get_clean();
	}

	/**
	 * Send email via WordPress wp_mail.
	 *
	 * @param string $to      Recipient email.
	 * @param string $subject Email subject.
	 * @param string $message Email body (HTML).
	 */
	private static function send_email( $to, $subject, $message ) {
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: Wine Tours Grapevine <noreply@winetoursgrapevine.com>',
		);

		$sent = wp_mail( $to, $subject, $message, $headers );

		if ( $sent ) {
			error_log( sprintf( 'WTG2: Email sent to %s - %s', $to, $subject ) );
		} else {
			error_log( sprintf( 'WTG2: Failed to send email to %s - %s', $to, $subject ) );
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
