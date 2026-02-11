<?php
/**
 * Booking Model
 *
 * Handles CRUD operations for the wp_wtg_bookings table.
 *
 * @package WTG2
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Booking model class.
 */
class WTG_Booking {

	/**
	 * Get table name.
	 *
	 * @return string Full table name with prefix.
	 */
	private static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'wtg_bookings';
	}

	/**
	 * Create a new booking.
	 *
	 * @param array $data Booking data.
	 * @return int|false Booking ID on success, false on failure.
	 */
	public static function create( $data ) {
		global $wpdb;

		// Handle field name mapping from admin form to database columns.
		if ( isset( $data['customer_name'] ) && ! isset( $data['first_name'] ) ) {
			// Split customer_name into first_name and last_name.
			$name_parts = explode( ' ', trim( $data['customer_name'] ), 2 );
			$data['first_name'] = $name_parts[0];
			$data['last_name'] = isset( $name_parts[1] ) ? $name_parts[1] : '';
		}
		if ( isset( $data['customer_email'] ) && ! isset( $data['email'] ) ) {
			$data['email'] = $data['customer_email'];
		}
		if ( isset( $data['customer_phone'] ) && ! isset( $data['phone'] ) ) {
			$data['phone'] = $data['customer_phone'];
		}
		if ( isset( $data['total_amount'] ) && ! isset( $data['deposit_amount'] ) ) {
			// If total_amount is provided but deposit_amount isn't, calculate it.
			$data['deposit_amount'] = $data['total_amount'] * 0.5;
		}

		$defaults = array(
			'gf_entry_id'       => 0,
			'tour_date'         => '',
			'time_slot'         => '',
			'tickets'           => 0,
			'total_amount'      => null,
			'first_name'        => null,
			'last_name'         => null,
			'email'             => null,
			'phone'             => null,
			'deposit_amount'    => null,
			'balance_due'       => null,
			'payment_status'    => 'pending',
			'gift_cert_id'      => null,
			'discount_applied'  => 0.00,
			'deposit_square_id' => null,
			'balance_square_id' => null,
			'invoice_square_id' => null,
			'invoice_sent_at'   => null,
			'notes'             => null,
		);

		$data = wp_parse_args( $data, $defaults );

		// Generate booking code (will be updated after insert to include ID).
		$temp_code = 'TEMP-' . uniqid();

		$result = $wpdb->insert(
			self::get_table_name(),
			array(
				'booking_code'      => $temp_code,
				'gf_entry_id'       => absint( $data['gf_entry_id'] ),
				'tour_date'         => sanitize_text_field( $data['tour_date'] ),
				'time_slot'         => sanitize_text_field( $data['time_slot'] ),
				'tickets'           => absint( $data['tickets'] ),
				'total_amount'      => ! empty( $data['total_amount'] ) ? floatval( $data['total_amount'] ) : null,
				'first_name'        => sanitize_text_field( $data['first_name'] ),
				'last_name'         => sanitize_text_field( $data['last_name'] ),
				'email'             => sanitize_email( $data['email'] ),
				'phone'             => sanitize_text_field( $data['phone'] ),
				'deposit_amount'    => floatval( $data['deposit_amount'] ),
				'balance_due'       => floatval( $data['balance_due'] ),
				'payment_status'    => sanitize_text_field( $data['payment_status'] ),
				'gift_cert_id'      => ! empty( $data['gift_cert_id'] ) ? absint( $data['gift_cert_id'] ) : null,
				'discount_applied'  => floatval( $data['discount_applied'] ),
				'deposit_square_id' => ! empty( $data['deposit_square_id'] ) ? sanitize_text_field( $data['deposit_square_id'] ) : null,
				'balance_square_id' => ! empty( $data['balance_square_id'] ) ? sanitize_text_field( $data['balance_square_id'] ) : null,
				'invoice_square_id' => ! empty( $data['invoice_square_id'] ) ? sanitize_text_field( $data['invoice_square_id'] ) : null,
				'invoice_sent_at'   => $data['invoice_sent_at'],
				'notes'             => wp_kses_post( $data['notes'] ),
			),
			array(
				'%s', // booking_code
				'%d', // gf_entry_id
				'%s', // tour_date
				'%s', // time_slot
				'%d', // tickets
				'%f', // total_amount
				'%s', // first_name
				'%s', // last_name
				'%s', // email
				'%s', // phone
				'%f', // deposit_amount
				'%f', // balance_due
				'%s', // payment_status
				'%d', // gift_cert_id
				'%f', // discount_applied
				'%s', // deposit_square_id
				'%s', // balance_square_id
				'%s', // invoice_square_id
				'%s', // invoice_sent_at
				'%s', // notes
			)
		);

		if ( ! $result ) {
			return false;
		}

		$booking_id = $wpdb->insert_id;

		// Update with actual booking code.
		$booking_code = self::generate_booking_code( $booking_id );
		$wpdb->update(
			self::get_table_name(),
			array( 'booking_code' => $booking_code ),
			array( 'id' => $booking_id ),
			array( '%s' ),
			array( '%d' )
		);

		return $booking_id;
	}

	/**
	 * Get booking by ID.
	 *
	 * @param int $id Booking ID.
	 * @return array|null Booking array or null if not found.
	 */
	public static function get_by_id( $id ) {
		global $wpdb;

		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . self::get_table_name() . " WHERE id = %d",
				$id
			),
			ARRAY_A
		);

		if ( ! $booking ) {
			return null;
		}

		// Add admin form field names by mapping from database columns.
		$booking['customer_name'] = trim( ( $booking['first_name'] ?? '' ) . ' ' . ( $booking['last_name'] ?? '' ) );
		$booking['customer_email'] = $booking['email'] ?? '';
		$booking['customer_phone'] = $booking['phone'] ?? '';

		return $booking;
	}

	/**
	 * Get booking by Gravity Forms entry ID.
	 *
	 * @param int $gf_entry_id Gravity Forms entry ID.
	 * @return object|null Booking object or null if not found.
	 */
	public static function get_by_gf_entry( $gf_entry_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . self::get_table_name() . " WHERE gf_entry_id = %d",
				$gf_entry_id
			)
		);
	}

	/**
	 * Get all bookings for a specific date and time slot.
	 *
	 * @param string $tour_date Tour date (Y-m-d format).
	 * @param string $time_slot Time slot (fri_am, fri_pm, sat_am, sat_pm).
	 * @return array Array of booking objects.
	 */
	public static function get_by_date_slot( $tour_date, $time_slot ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . self::get_table_name() . "
				WHERE tour_date = %s AND time_slot = %s
				ORDER BY created_at ASC",
				$tour_date,
				$time_slot
			)
		);
	}

	/**
	 * Count total tickets sold for a date and time slot.
	 *
	 * @param string $tour_date Tour date (Y-m-d format).
	 * @param string $time_slot Time slot (fri_am, fri_pm, sat_am, sat_pm).
	 * @param array  $statuses  Payment statuses to include (default: all non-refunded).
	 * @return int Total tickets sold.
	 */
	public static function count_tickets_sold( $tour_date, $time_slot, $statuses = null ) {
		global $wpdb;

		// Default to all non-refunded statuses.
		if ( is_null( $statuses ) ) {
			$statuses = array( 'pending', 'deposit_paid', 'paid_full' );
		}

		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		$query = $wpdb->prepare(
			"SELECT COALESCE(SUM(tickets), 0) FROM " . self::get_table_name() . "
			WHERE tour_date = %s AND time_slot = %s AND payment_status IN ($placeholders)",
			array_merge( array( $tour_date, $time_slot ), $statuses )
		);

		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Count paid tickets only for a date and time slot.
	 *
	 * @param string $tour_date Tour date (Y-m-d format).
	 * @param string $time_slot Time slot (fri_am, fri_pm, sat_am, sat_pm).
	 * @return int Total paid tickets.
	 */
	public static function count_paid_tickets( $tour_date, $time_slot ) {
		return self::count_tickets_sold( $tour_date, $time_slot, array( 'deposit_paid', 'paid_full' ) );
	}

	/**
	 * Update a booking.
	 *
	 * @param int   $id   Booking ID.
	 * @param array $data Data to update.
	 * @return bool True on success, false on failure.
	 */
	public static function update( $id, $data ) {
		global $wpdb;

		// Handle field name mapping from admin form to database columns.
		if ( isset( $data['customer_name'] ) ) {
			// Split customer_name into first_name and last_name.
			$name_parts = explode( ' ', trim( $data['customer_name'] ), 2 );
			$data['first_name'] = $name_parts[0];
			$data['last_name'] = isset( $name_parts[1] ) ? $name_parts[1] : '';
			unset( $data['customer_name'] );
		}
		if ( isset( $data['customer_email'] ) ) {
			$data['email'] = $data['customer_email'];
			unset( $data['customer_email'] );
		}
		if ( isset( $data['customer_phone'] ) ) {
			$data['phone'] = $data['customer_phone'];
			unset( $data['customer_phone'] );
		}

		$allowed_fields = array(
			'tour_date'         => '%s',
			'time_slot'         => '%s',
			'tickets'           => '%d',
			'total_amount'      => '%f',
			'first_name'        => '%s',
			'last_name'         => '%s',
			'email'             => '%s',
			'phone'             => '%s',
			'deposit_amount'    => '%f',
			'balance_due'       => '%f',
			'payment_status'    => '%s',
			'gift_cert_id'      => '%d',
			'discount_applied'  => '%f',
			'deposit_square_id' => '%s',
			'balance_square_id' => '%s',
			'invoice_square_id' => '%s',
			'invoice_sent_at'   => '%s',
			'notes'             => '%s',
		);

		$update_data   = array();
		$update_format = array();

		foreach ( $data as $key => $value ) {
			if ( isset( $allowed_fields[ $key ] ) ) {
				$update_data[ $key ]   = $value;
				$update_format[]       = $allowed_fields[ $key ];
			}
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$result = $wpdb->update(
			self::get_table_name(),
			$update_data,
			array( 'id' => absint( $id ) ),
			$update_format,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete a booking.
	 *
	 * @param int $id Booking ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( $id ) {
		global $wpdb;

		$result = $wpdb->delete(
			self::get_table_name(),
			array( 'id' => absint( $id ) ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get bookings pending invoice send.
	 *
	 * @param int $hours_before Hours before tour date to send invoice.
	 * @return array Array of booking objects.
	 */
	public static function get_pending_invoices( $hours_before = 48 ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . self::get_table_name() . "
				WHERE invoice_sent_at IS NULL
				AND payment_status = 'deposit_paid'
				AND tour_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL %d HOUR)
				ORDER BY tour_date ASC",
				$hours_before
			)
		);
	}

	/**
	 * Get all bookings for a specific tour date.
	 *
	 * @param string $tour_date Tour date (Y-m-d format).
	 * @return array Array of booking objects grouped by time slot.
	 */
	public static function get_by_date( $tour_date ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . self::get_table_name() . "
				WHERE tour_date = %s
				ORDER BY
					FIELD(time_slot, 'sat_am', 'sat_pm', 'fri_pm', 'fri_am'),
					created_at ASC",
				$tour_date
			)
		);
	}

	/**
	 * Generate a unique booking code.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string Booking code in format WTG-YYYY-####.
	 */
	private static function generate_booking_code( $booking_id ) {
		$year = date( 'Y' );
		return sprintf( 'WTG-%s-%04d', $year, $booking_id );
	}
}
