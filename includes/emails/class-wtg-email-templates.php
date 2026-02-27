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

		$tickets      = intval( $booking['tickets'] ?? 1 );
		$balance      = floatval( $booking['balance_due'] );
		$tax          = round( 165.00 * $tickets * 0.0825, 2 );
		$balance_with_tax = $balance + $tax;

		$message = self::get_email_template( 'deposit-confirmation', array(
			'customer_name'  => $booking['first_name'] . ' ' . $booking['last_name'],
			'booking_code'   => $booking['booking_code'],
			'tour_date'      => date( 'F j, Y', strtotime( $booking['tour_date'] ) ),
			'time_slot'      => self::get_time_slot_label( $booking['time_slot'] ),
			'tickets'        => $tickets,
			'deposit_amount' => number_format( floatval( $booking['deposit_amount'] ), 2 ),
			'balance_due'    => number_format( $balance_with_tax, 2 ),
		) );

		self::send_email( $to, $subject, $message, 'deposit_confirmation' );
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

		$gc_applied       = floatval( $booking['discount_applied'] ?? 0 ) > 0;
		$discount_applied = floatval( $booking['discount_applied'] ?? 0 );

		$message = self::get_email_template( 'balance-confirmation', array(
			'customer_name'    => $booking['first_name'] . ' ' . $booking['last_name'],
			'booking_code'     => $booking['booking_code'],
			'tour_date'        => date( 'F j, Y', strtotime( $booking['tour_date'] ) ),
			'time_slot'        => self::get_time_slot_label( $booking['time_slot'] ),
			'tickets'          => $booking['tickets'],
			'total_paid'       => number_format( floatval( $booking['deposit_amount'] ) + floatval( $booking['balance_due'] ), 2 ),
			'arrival_time'     => self::get_arrival_time( $booking['time_slot'] ),
			'gc_applied'       => $gc_applied,
			'discount_applied' => number_format( $discount_applied, 2 ),
		) );

		self::send_email( $to, $subject, $message, 'balance_confirmation' );
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
			'balance_due'   => number_format( floatval( $booking['balance_due'] ), 2 ),
			'invoice_url'   => $invoice_url,
		) );

		self::send_email( $to, $subject, $message, 'balance_invoice' );
	}

	/**
	 * Send manual booking confirmation (admin-created, no payment).
	 *
	 * @param array $booking Booking data.
	 */
	public static function send_manual_booking_confirmation( $booking ) {
		$to = $booking['email'];
		$subject = sprintf(
			'Booking Confirmed - Wine Tours Grapevine Booking %s',
			$booking['booking_code']
		);

		$message = self::get_email_template( 'manual-booking-confirmation', array(
			'customer_name' => trim( ( $booking['first_name'] ?? '' ) . ' ' . ( $booking['last_name'] ?? '' ) ),
			'booking_code'  => $booking['booking_code'],
			'tour_date'     => date( 'F j, Y', strtotime( $booking['tour_date'] ) ),
			'time_slot'     => self::get_time_slot_label( $booking['time_slot'] ),
			'tickets'       => $booking['tickets'],
			'arrival_time'  => self::get_arrival_time( $booking['time_slot'] ),
		) );

		self::send_email( $to, $subject, $message, 'manual_booking' );
	}

	/**
	 * Send gift certificate confirmation to purchaser.
	 *
	 * @param array $gc_data Gift certificate data.
	 */
	public static function send_gift_certificate_purchaser( $gc_data ) {
		$to      = $gc_data['purchaser_email'];
		$subject = sprintf( 'Gift Certificate Confirmed - %s', $gc_data['code'] );

		$message = self::get_email_template( 'gift-certificate-purchaser', array(
			'purchaser_name'  => $gc_data['purchaser_name'],
			'recipient_name'  => $gc_data['recipient_name'],
			'recipient_email' => $gc_data['recipient_email'],
			'code'            => $gc_data['code'],
			'amount'          => number_format( floatval( $gc_data['amount'] ), 2 ),
			'message'         => $gc_data['message'],
		) );

		self::send_email( $to, $subject, $message, 'gc_purchaser' );
	}

	/**
	 * Send gift certificate notification to recipient.
	 *
	 * @param array $gc_data Gift certificate data.
	 */
	public static function send_gift_certificate_recipient( $gc_data ) {
		$to      = $gc_data['recipient_email'];
		$subject = 'You\'ve Received a Wine Tours Grapevine Gift Certificate!';

		$message = self::get_email_template( 'gift-certificate-recipient', array(
			'purchaser_name' => $gc_data['purchaser_name'],
			'recipient_name' => $gc_data['recipient_name'],
			'code'           => $gc_data['code'],
			'amount'         => number_format( floatval( $gc_data['amount'] ), 2 ),
			'message'        => $gc_data['message'],
		) );

		self::send_email( $to, $subject, $message, 'gc_recipient' );
	}

	/**
	 * Send admin notification for gift certificate purchase.
	 *
	 * @param array $gc_data Gift certificate data.
	 */
	public static function send_admin_gc_notification( $gc_data ) {
		$admin_email = get_option( 'wtg_admin_email', '' );
		if ( empty( $admin_email ) ) {
			return;
		}

		$subject = sprintf( 'New Gift Certificate Purchase - %s', $gc_data['code'] );

		$message = self::get_email_template( 'admin-gc-notification', array(
			'purchaser_name'  => $gc_data['purchaser_name'],
			'purchaser_email' => $gc_data['purchaser_email'],
			'recipient_name'  => $gc_data['recipient_name'],
			'recipient_email' => $gc_data['recipient_email'],
			'code'            => $gc_data['code'],
			'amount'          => number_format( floatval( $gc_data['amount'] ), 2 ),
			'message'         => $gc_data['message'],
			'purchase_date'   => current_time( 'F j, Y g:i A' ),
		) );

		self::send_email( $admin_email, $subject, $message, 'admin_gc' );
	}

	/**
	 * Send admin notification for new booking.
	 *
	 * @param object|array $booking Booking data.
	 */
	public static function send_admin_booking_notification( $booking ) {
		$admin_email = get_option( 'wtg_admin_email', '' );
		if ( empty( $admin_email ) ) {
			return;
		}

		$booking = (object) $booking;

		$subject = sprintf(
			'New Booking - %s (%s)',
			trim( $booking->first_name . ' ' . $booking->last_name ),
			$booking->booking_code
		);

		$discount = floatval( $booking->discount_applied ?? 0 );

		$message = self::get_email_template( 'admin-booking-notification', array(
			'customer_name'    => trim( $booking->first_name . ' ' . $booking->last_name ),
			'customer_email'   => $booking->email,
			'booking_code'     => $booking->booking_code,
			'tour_date'        => date( 'F j, Y', strtotime( $booking->tour_date ) ),
			'time_slot'        => self::get_time_slot_label( $booking->time_slot ),
			'tickets'          => $booking->tickets,
			'deposit_amount'   => number_format( floatval( $booking->deposit_amount ), 2 ),
			'balance_due'      => number_format( floatval( $booking->balance_due ), 2 ),
			'payment_status'   => $booking->payment_status,
			'discount_applied' => $discount > 0 ? number_format( $discount, 2 ) : '',
			'booking_date'     => current_time( 'F j, Y g:i A' ),
		) );

		self::send_email( $admin_email, $subject, $message, 'admin_booking' );
	}

	/**
	 * Get email template.
	 *
	 * Checks the active theme for an override at wtg2/emails/{template}.php
	 * before falling back to the plugin default.
	 *
	 * @param string $template Template name.
	 * @param array  $vars     Template variables.
	 * @return string HTML email content.
	 */
	private static function get_email_template( $template, $vars ) {
		ob_start();
		extract( $vars );

		// Allow theme override: wp-content/themes/{theme}/wtg2/emails/{template}.php
		$theme_file    = get_stylesheet_directory() . "/wtg2/emails/{$template}.php";
		$template_file = file_exists( $theme_file )
			? $theme_file
			: WTG2_PLUGIN_DIR . "includes/emails/templates/{$template}.php";

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
	 * Send email via WordPress wp_mail and log the result.
	 *
	 * @param string $to         Recipient email.
	 * @param string $subject    Email subject.
	 * @param string $message    Email body (HTML).
	 * @param string $email_type Optional type for logging (e.g. 'gc_purchaser', 'admin_booking').
	 */
	private static function send_email( $to, $subject, $message, $email_type = 'general' ) {
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

		self::log_email( $to, $subject, $email_type, $sent ? 'sent' : 'failed' );
	}

	/**
	 * Log an email send attempt to the database.
	 *
	 * @param string $to         Recipient email.
	 * @param string $subject    Email subject.
	 * @param string $email_type Email type identifier.
	 * @param string $status     'sent' or 'failed'.
	 */
	private static function log_email( $to, $subject, $email_type, $status ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wtg_email_log';

		// Silently skip if the table doesn't exist yet (pre-activation).
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return;
		}

		$wpdb->insert(
			$table,
			array(
				'to_email'   => $to,
				'subject'    => $subject,
				'email_type' => $email_type,
				'status'     => $status,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get email log entries for admin display.
	 *
	 * @param int $limit Number of entries to return.
	 * @return array Log entries.
	 */
	public static function get_log( $limit = 50 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wtg_email_log';
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Get time slot label.
	 *
	 * @param string $slot Database slot value.
	 * @return string Human-readable label.
	 */
	/**
	 * Get arrival time (15 minutes before tour start).
	 *
	 * @param string $slot Database slot value.
	 * @return string Human-readable arrival time.
	 */
	private static function get_arrival_time( $slot ) {
		$times = array(
			'sat_am' => '10:45 AM',
			'sat_pm' => '4:45 PM',
			'fri_am' => '10:45 AM',
			'fri_pm' => '4:45 PM',
		);

		return isset( $times[ $slot ] ) ? $times[ $slot ] : '15 minutes before your tour';
	}

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
