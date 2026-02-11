<?php
/**
 * Date Override Model
 *
 * Handles CRUD operations for the wp_wtg_date_overrides table.
 * Manages manual "full" flags for specific date/time slot combinations.
 *
 * @package WTG2
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Date Override model class.
 */
class WTG_Date_Override {

	/**
	 * Get table name.
	 *
	 * @return string Full table name with prefix.
	 */
	private static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'wtg_date_overrides';
	}

	/**
	 * Get override for a specific date and time slot.
	 *
	 * @param string $tour_date Tour date (Y-m-d format).
	 * @param string $time_slot Time slot (fri_am, fri_pm, sat_am, sat_pm).
	 * @return object|null Override object or null if not found.
	 */
	public static function get_override( $tour_date, $time_slot ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . self::get_table_name() . "
				WHERE tour_date = %s AND time_slot = %s",
				$tour_date,
				$time_slot
			)
		);
	}

	/**
	 * Check if a slot is manually marked as full.
	 *
	 * @param string $tour_date Tour date (Y-m-d format).
	 * @param string $time_slot Time slot (fri_am, fri_pm, sat_am, sat_pm).
	 * @return bool True if slot is manually full, false otherwise.
	 */
	public static function is_slot_full( $tour_date, $time_slot ) {
		$override = self::get_override( $tour_date, $time_slot );

		return $override && ! empty( $override->is_full );
	}

	/**
	 * Set a slot as manually full.
	 *
	 * @param string $tour_date Tour date (Y-m-d format).
	 * @param string $time_slot Time slot (fri_am, fri_pm, sat_am, sat_pm).
	 * @param string $reason    Optional reason for marking full.
	 * @param int    $user_id   Optional user ID who created the override.
	 * @return bool True on success, false on failure.
	 */
	public static function set_full( $tour_date, $time_slot, $reason = null, $user_id = null ) {
		global $wpdb;

		// Check if override already exists.
		$existing = self::get_override( $tour_date, $time_slot );

		if ( $existing ) {
			// Update existing override.
			return self::update(
				$existing->id,
				array(
					'is_full' => 1,
					'reason'  => $reason,
				)
			);
		}

		// Create new override.
		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		$result = $wpdb->insert(
			self::get_table_name(),
			array(
				'tour_date'  => sanitize_text_field( $tour_date ),
				'time_slot'  => sanitize_text_field( $time_slot ),
				'is_full'    => 1,
				'reason'     => wp_kses_post( $reason ),
				'created_by' => absint( $user_id ),
			),
			array( '%s', '%s', '%d', '%s', '%d' )
		);

		return false !== $result;
	}

	/**
	 * Clear the full override for a slot.
	 *
	 * @param string $tour_date Tour date (Y-m-d format).
	 * @param string $time_slot Time slot (fri_am, fri_pm, sat_am, sat_pm).
	 * @return bool True on success, false on failure.
	 */
	public static function clear_full( $tour_date, $time_slot ) {
		$existing = self::get_override( $tour_date, $time_slot );

		if ( ! $existing ) {
			return false;
		}

		return self::update(
			$existing->id,
			array( 'is_full' => 0 )
		);
	}

	/**
	 * Toggle the full status for a slot.
	 *
	 * @param string $tour_date Tour date (Y-m-d format).
	 * @param string $time_slot Time slot (fri_am, fri_pm, sat_am, sat_pm).
	 * @param string $reason    Optional reason for marking full.
	 * @return bool New full status (true if now full, false if now available).
	 */
	public static function toggle_full( $tour_date, $time_slot, $reason = null ) {
		$is_currently_full = self::is_slot_full( $tour_date, $time_slot );

		if ( $is_currently_full ) {
			self::clear_full( $tour_date, $time_slot );
			return false;
		} else {
			self::set_full( $tour_date, $time_slot, $reason );
			return true;
		}
	}

	/**
	 * Get override by ID.
	 *
	 * @param int $id Override ID.
	 * @return object|null Override object or null if not found.
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
	 * Get all overrides for a specific date.
	 *
	 * @param string $tour_date Tour date (Y-m-d format).
	 * @return array Array of override objects.
	 */
	public static function get_by_date( $tour_date ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . self::get_table_name() . "
				WHERE tour_date = %s
				ORDER BY FIELD(time_slot, 'sat_am', 'sat_pm', 'fri_pm', 'fri_am')",
				$tour_date
			)
		);
	}

	/**
	 * Get all active overrides (slots marked as full).
	 *
	 * @param int $limit Maximum number of results (default: 100).
	 * @return array Array of override objects.
	 */
	public static function get_all_full( $limit = 100 ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . self::get_table_name() . "
				WHERE is_full = 1
				ORDER BY tour_date ASC, FIELD(time_slot, 'sat_am', 'sat_pm', 'fri_pm', 'fri_am')
				LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Update an override.
	 *
	 * @param int   $id   Override ID.
	 * @param array $data Data to update.
	 * @return bool True on success, false on failure.
	 */
	public static function update( $id, $data ) {
		global $wpdb;

		$allowed_fields = array(
			'tour_date'  => '%s',
			'time_slot'  => '%s',
			'is_full'    => '%d',
			'reason'     => '%s',
			'created_by' => '%d',
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
	 * Delete an override.
	 *
	 * @param int $id Override ID.
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
	 * Delete an override by date and slot.
	 *
	 * @param string $tour_date Tour date (Y-m-d format).
	 * @param string $time_slot Time slot (fri_am, fri_pm, sat_am, sat_pm).
	 * @return bool True on success, false on failure.
	 */
	public static function delete_by_date_slot( $tour_date, $time_slot ) {
		global $wpdb;

		$result = $wpdb->delete(
			self::get_table_name(),
			array(
				'tour_date' => sanitize_text_field( $tour_date ),
				'time_slot' => sanitize_text_field( $time_slot ),
			),
			array( '%s', '%s' )
		);

		return false !== $result;
	}
}
