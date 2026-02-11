<?php
/**
 * Gift Certificate Model
 *
 * Handles CRUD operations for the wp_wtg_gift_certificates table.
 *
 * @package WTG2
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Gift Certificate model class.
 */
class WTG_Gift_Certificate {

	/**
	 * Get table name.
	 *
	 * @return string Full table name with prefix.
	 */
	private static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'wtg_gift_certificates';
	}

	/**
	 * Generate a unique gift certificate code.
	 *
	 * Format: WTG-XXXX-XXXX
	 * Characters: A-Z and 2-9 (excludes O, 0, I, 1 for clarity).
	 *
	 * @return string Unique gift certificate code.
	 */
	public static function generate_unique_code() {
		global $wpdb;

		// Characters to use (exclude O, 0, I, 1 to avoid confusion).
		$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

		do {
			$code = 'WTG-';
			$code .= substr( str_shuffle( $chars ), 0, 4 );
			$code .= '-';
			$code .= substr( str_shuffle( $chars ), 0, 4 );

			// Check if code already exists.
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM " . self::get_table_name() . " WHERE code = %s",
					$code
				)
			);
		} while ( $exists > 0 );

		return $code;
	}

	/**
	 * Create a new gift certificate.
	 *
	 * @param array $data Gift certificate data.
	 * @return int|false Gift certificate ID on success, false on failure.
	 */
	public static function create( $data ) {
		global $wpdb;

		$defaults = array(
			'code'                    => self::generate_unique_code(),
			'gf_entry_id'             => 0,
			'purchaser_name'          => null,
			'purchaser_email'         => null,
			'recipient_name'          => null,
			'recipient_email'         => null,
			'amount'                  => 0.00,
			'message'                 => null,
			'status'                  => 'active',
			'redeemed_at'             => null,
			'redeemed_by_booking_id'  => null,
		);

		$data = wp_parse_args( $data, $defaults );

		$result = $wpdb->insert(
			self::get_table_name(),
			array(
				'code'                   => sanitize_text_field( $data['code'] ),
				'gf_entry_id'            => absint( $data['gf_entry_id'] ),
				'purchaser_name'         => sanitize_text_field( $data['purchaser_name'] ),
				'purchaser_email'        => sanitize_email( $data['purchaser_email'] ),
				'recipient_name'         => sanitize_text_field( $data['recipient_name'] ),
				'recipient_email'        => sanitize_email( $data['recipient_email'] ),
				'amount'                 => floatval( $data['amount'] ),
				'message'                => wp_kses_post( $data['message'] ),
				'status'                 => sanitize_text_field( $data['status'] ),
				'redeemed_at'            => $data['redeemed_at'],
				'redeemed_by_booking_id' => ! empty( $data['redeemed_by_booking_id'] ) ? absint( $data['redeemed_by_booking_id'] ) : null,
			),
			array(
				'%s', // code
				'%d', // gf_entry_id
				'%s', // purchaser_name
				'%s', // purchaser_email
				'%s', // recipient_name
				'%s', // recipient_email
				'%f', // amount
				'%s', // message
				'%s', // status
				'%s', // redeemed_at
				'%d', // redeemed_by_booking_id
			)
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get gift certificate by ID.
	 *
	 * @param int $id Gift certificate ID.
	 * @return object|null Gift certificate object or null if not found.
	 */
	public static function get_by_id( $id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . self::get_table_name() . " WHERE id = %d",
				$id
			)
		);
	}

	/**
	 * Get gift certificate by code.
	 *
	 * @param string $code Gift certificate code.
	 * @return object|null Gift certificate object or null if not found.
	 */
	public static function get_by_code( $code ) {
		global $wpdb;

		// Normalize code (uppercase, trim spaces).
		$code = strtoupper( trim( $code ) );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . self::get_table_name() . " WHERE code = %s",
				$code
			)
		);
	}

	/**
	 * Get gift certificate by Gravity Forms entry ID.
	 *
	 * @param int $gf_entry_id Gravity Forms entry ID.
	 * @return object|null Gift certificate object or null if not found.
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
	 * Validate a gift certificate code.
	 *
	 * @param string $code Gift certificate code.
	 * @return array Validation result with 'valid' boolean and 'message' string.
	 */
	public static function validate_code( $code ) {
		$cert = self::get_by_code( $code );

		if ( ! $cert ) {
			return array(
				'valid'   => false,
				'message' => 'Invalid gift certificate code.',
			);
		}

		if ( 'active' !== $cert->status ) {
			$status_messages = array(
				'redeemed'  => 'This gift certificate has already been redeemed.',
				'expired'   => 'This gift certificate has expired.',
				'cancelled' => 'This gift certificate has been cancelled.',
			);

			return array(
				'valid'   => false,
				'message' => isset( $status_messages[ $cert->status ] ) ? $status_messages[ $cert->status ] : 'This gift certificate is not active.',
			);
		}

		return array(
			'valid'   => true,
			'message' => 'Gift certificate is valid!',
			'cert'    => $cert,
		);
	}

	/**
	 * Redeem a gift certificate.
	 *
	 * @param string $code       Gift certificate code.
	 * @param int    $booking_id Booking ID that redeemed this certificate.
	 * @return bool True on success, false on failure.
	 */
	public static function redeem( $code, $booking_id ) {
		$cert = self::get_by_code( $code );

		if ( ! $cert || 'active' !== $cert->status ) {
			return false;
		}

		return self::update(
			$cert->id,
			array(
				'status'                 => 'redeemed',
				'redeemed_at'            => current_time( 'mysql' ),
				'redeemed_by_booking_id' => absint( $booking_id ),
			)
		);
	}

	/**
	 * Update a gift certificate.
	 *
	 * @param int   $id   Gift certificate ID.
	 * @param array $data Data to update.
	 * @return bool True on success, false on failure.
	 */
	public static function update( $id, $data ) {
		global $wpdb;

		$allowed_fields = array(
			'code'                   => '%s',
			'purchaser_name'         => '%s',
			'purchaser_email'        => '%s',
			'recipient_name'         => '%s',
			'recipient_email'        => '%s',
			'amount'                 => '%f',
			'message'                => '%s',
			'status'                 => '%s',
			'redeemed_at'            => '%s',
			'redeemed_by_booking_id' => '%d',
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
	 * Delete a gift certificate.
	 *
	 * @param int $id Gift certificate ID.
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
	 * Get gift certificates by status.
	 *
	 * @param string $status Status (active, redeemed, expired, cancelled).
	 * @param int    $limit  Maximum number of results (default: 100).
	 * @return array Array of gift certificate objects.
	 */
	public static function get_by_status( $status, $limit = 100 ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . self::get_table_name() . "
				WHERE status = %s
				ORDER BY created_at DESC
				LIMIT %d",
				$status,
				$limit
			)
		);
	}

	/**
	 * Get all gift certificates.
	 *
	 * @param int $limit  Maximum number of results (default: 100).
	 * @param int $offset Offset for pagination (default: 0).
	 * @return array Array of gift certificate objects.
	 */
	public static function get_all( $limit = 100, $offset = 0 ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . self::get_table_name() . "
				ORDER BY created_at DESC
				LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);
	}

	/**
	 * Search gift certificates by email or code.
	 *
	 * @param string $search Search term.
	 * @param int    $limit  Maximum number of results (default: 100).
	 * @return array Array of gift certificate objects.
	 */
	public static function search( $search, $limit = 100 ) {
		global $wpdb;

		$search = '%' . $wpdb->esc_like( $search ) . '%';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . self::get_table_name() . "
				WHERE code LIKE %s
				   OR purchaser_email LIKE %s
				   OR recipient_email LIKE %s
				ORDER BY created_at DESC
				LIMIT %d",
				$search,
				$search,
				$search,
				$limit
			)
		);
	}
}
