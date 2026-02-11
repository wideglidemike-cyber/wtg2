<?php
/**
 * Availability Controller
 *
 * Handles progressive slot unlock logic and availability calculations.
 *
 * @package WTG2
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Availability controller class.
 */
class WTG_Availability_Controller {

	/**
	 * Progressive unlock logic constants.
	 *
	 * Sat AM → Sat PM → Fri PM → Fri AM
	 */
	const SLOT_ORDER = array( 'sat_am', 'sat_pm', 'fri_pm', 'fri_am' );

	/**
	 * Get seat capacity from options.
	 *
	 * @return int Seat capacity per slot.
	 */
	public static function get_capacity() {
		return absint( get_option( 'wtg_seat_capacity', 14 ) );
	}

	/**
	 * Get unlock threshold from options.
	 *
	 * @return int Number of paid tickets needed to unlock next slot.
	 */
	public static function get_threshold() {
		return absint( get_option( 'wtg_unlock_threshold', 10 ) );
	}

	/**
	 * Check if a specific slot is available for a date.
	 *
	 * @param string $tour_date Tour date (Y-m-d format).
	 * @param string $time_slot Time slot (fri_am, fri_pm, sat_am, sat_pm).
	 * @param int    $requested_tickets Number of tickets requested (default: 1).
	 * @return array Availability result with 'available' boolean and 'reason' string.
	 */
	public static function check_slot_availability( $tour_date, $time_slot, $requested_tickets = 1 ) {
		// Check if date is in the past.
		if ( strtotime( $tour_date ) < strtotime( 'today' ) ) {
			return array(
				'available' => false,
				'reason'    => 'Tour date has passed.',
			);
		}

		// Check manual override (slot marked as full).
		if ( WTG_Date_Override::is_slot_full( $tour_date, $time_slot ) ) {
			return array(
				'available' => false,
				'reason'    => 'This time slot is currently unavailable.',
			);
		}

		// Check if slot is unlocked based on progressive logic.
		$unlock_check = self::is_slot_unlocked( $tour_date, $time_slot );
		if ( ! $unlock_check['unlocked'] ) {
			return array(
				'available' => false,
				'reason'    => $unlock_check['reason'],
			);
		}

		// Check capacity.
		$tickets_sold = WTG_Booking::count_tickets_sold( $tour_date, $time_slot );
		$capacity = self::get_capacity();
		$remaining = $capacity - $tickets_sold;

		if ( $remaining < $requested_tickets ) {
			return array(
				'available' => false,
				'reason'    => sprintf( 'Only %d seat(s) remaining. You requested %d.', max( 0, $remaining ), $requested_tickets ),
			);
		}

		// Slot is available!
		return array(
			'available' => true,
			'reason'    => sprintf( '%d of %d seats available.', $remaining, $capacity ),
			'remaining' => $remaining,
			'capacity'  => $capacity,
			'sold'      => $tickets_sold,
		);
	}

	/**
	 * Check if a slot is unlocked based on progressive logic.
	 *
	 * Progressive unlock order: Sat AM → Sat PM → Fri PM → Fri AM
	 * Each slot unlocks when the previous slot reaches the threshold.
	 *
	 * @param string $tour_date Tour date (Y-m-d format).
	 * @param string $time_slot Time slot to check.
	 * @return array Result with 'unlocked' boolean and 'reason' string.
	 */
	public static function is_slot_unlocked( $tour_date, $time_slot ) {
		$threshold = self::get_threshold();

		switch ( $time_slot ) {
			case 'sat_am':
				// Saturday AM is always unlocked (first slot).
				return array(
					'unlocked' => true,
					'reason'   => 'Saturday AM is always available.',
				);

			case 'sat_pm':
				// Saturday PM unlocks when Saturday AM reaches threshold.
				$sat_am_paid = WTG_Booking::count_paid_tickets( $tour_date, 'sat_am' );
				if ( $sat_am_paid < $threshold ) {
					return array(
						'unlocked' => false,
						'reason'   => sprintf( 'Saturday PM unlocks when Saturday AM reaches %d paid tickets. Currently: %d', $threshold, $sat_am_paid ),
					);
				}
				return array(
					'unlocked' => true,
					'reason'   => 'Saturday PM is unlocked.',
				);

			case 'fri_pm':
				// Friday PM unlocks when BOTH Saturday AM and Saturday PM reach threshold.
				// First check if Saturday PM is unlocked.
				$sat_pm_check = self::is_slot_unlocked( $tour_date, 'sat_pm' );
				if ( ! $sat_pm_check['unlocked'] ) {
					return array(
						'unlocked' => false,
						'reason'   => 'Friday PM requires both Saturday slots to be made first. ' . $sat_pm_check['reason'],
					);
				}

				// Now check if BOTH Saturday slots have reached threshold.
				$sat_am_paid = WTG_Booking::count_paid_tickets( $tour_date, 'sat_am' );
				$sat_pm_paid = WTG_Booking::count_paid_tickets( $tour_date, 'sat_pm' );

				if ( $sat_am_paid < $threshold ) {
					return array(
						'unlocked' => false,
						'reason'   => sprintf( 'Friday PM unlocks when both Saturday tours reach %d paid tickets. Saturday 11am: %d, Saturday 5pm: %d', $threshold, $sat_am_paid, $sat_pm_paid ),
					);
				}

				if ( $sat_pm_paid < $threshold ) {
					return array(
						'unlocked' => false,
						'reason'   => sprintf( 'Friday PM unlocks when both Saturday tours reach %d paid tickets. Saturday 11am: %d, Saturday 5pm: %d', $threshold, $sat_am_paid, $sat_pm_paid ),
					);
				}

				return array(
					'unlocked' => true,
					'reason'   => 'Friday PM is unlocked.',
				);

			case 'fri_am':
				// Friday AM unlocks when Friday PM reaches threshold.
				// But first, Friday PM must be unlocked.
				$fri_pm_check = self::is_slot_unlocked( $tour_date, 'fri_pm' );
				if ( ! $fri_pm_check['unlocked'] ) {
					return array(
						'unlocked' => false,
						'reason'   => 'Friday AM requires Friday PM to unlock first. ' . $fri_pm_check['reason'],
					);
				}

				$fri_pm_paid = WTG_Booking::count_paid_tickets( $tour_date, 'fri_pm' );
				if ( $fri_pm_paid < $threshold ) {
					return array(
						'unlocked' => false,
						'reason'   => sprintf( 'Friday AM unlocks when Friday PM reaches %d paid tickets. Currently: %d', $threshold, $fri_pm_paid ),
					);
				}
				return array(
					'unlocked' => true,
					'reason'   => 'Friday AM is unlocked.',
				);

			default:
				return array(
					'unlocked' => false,
					'reason'   => 'Invalid time slot.',
				);
		}
	}

	/**
	 * Get all available slots for a specific date.
	 *
	 * @param string $tour_date Tour date (Y-m-d format).
	 * @param int    $requested_tickets Number of tickets requested (default: 1).
	 * @return array Array of available slots with availability info.
	 */
	public static function get_available_slots( $tour_date, $requested_tickets = 1 ) {
		$slots = array();

		foreach ( self::SLOT_ORDER as $slot ) {
			$availability = self::check_slot_availability( $tour_date, $slot, $requested_tickets );
			$slots[ $slot ] = $availability;
		}

		return $slots;
	}

	/**
	 * Get human-readable slot label.
	 *
	 * @param string $time_slot Time slot key.
	 * @return string Human-readable label.
	 */
	public static function get_slot_label( $time_slot ) {
		$labels = array(
			'sat_am' => 'Saturday 11am to 3:45–4:15',
			'sat_pm' => 'Saturday 5pm to 9:45–10:15',
			'fri_pm' => 'Friday 5pm to 9:45–10:15',
			'fri_am' => 'Friday 11am to 3:45–4:15',
		);

		return isset( $labels[ $time_slot ] ) ? $labels[ $time_slot ] : $time_slot;
	}

	/**
	 * Get weekend date for a given date.
	 *
	 * Returns the Saturday date for any date in the Friday-Saturday weekend.
	 *
	 * @param string $date Any date (Y-m-d format).
	 * @return string Saturday date (Y-m-d format).
	 */
	public static function get_weekend_date( $date ) {
		$timestamp = strtotime( $date );
		$day_of_week = date( 'N', $timestamp ); // 1 = Monday, 7 = Sunday

		// If Friday (5), add 1 day to get Saturday.
		if ( 5 === $day_of_week ) {
			return date( 'Y-m-d', strtotime( '+1 day', $timestamp ) );
		}

		// If Saturday (6), return as-is.
		if ( 6 === $day_of_week ) {
			return date( 'Y-m-d', $timestamp );
		}

		// Invalid day (not Friday or Saturday).
		return $date;
	}

	/**
	 * Check if a date is a valid tour date (Friday or Saturday).
	 *
	 * @param string $date Date to check (Y-m-d format).
	 * @return bool True if Friday or Saturday, false otherwise.
	 */
	public static function is_valid_tour_date( $date ) {
		$day_of_week = date( 'N', strtotime( $date ) );
		return in_array( $day_of_week, array( 5, 6 ), true ); // 5 = Friday, 6 = Saturday
	}
}
