<?php
/**
 * Seating Grid Controller
 *
 * Generates visual seat-by-seat grid showing individual seat availability.
 *
 * @package WTG2
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Seating grid controller class.
 */
class WTG_Seating_Grid_Controller {

	/**
	 * Get individual seat data for a specific date and time slot.
	 *
	 * @param string $date      Tour date (Y-m-d).
	 * @param string $time_slot Time slot (fri_am, fri_pm, sat_am, sat_pm).
	 * @return array Array of seat data with status for each seat.
	 */
	public static function get_seat_data( $date, $time_slot ) {
		$capacity = WTG_Availability_Controller::get_capacity();

		// Get all bookings for this slot
		$bookings = WTG_Booking::get_by_date_slot( $date, $time_slot );

		$seats = array();
		$seat_number = 1;

		// Process each booking to assign seats
		foreach ( $bookings as $booking ) {
			$num_tickets = absint( $booking->tickets );
			$status = '';

			// Determine seat status based on payment status
			switch ( $booking->payment_status ) {
				case 'deposit_paid':
				case 'paid_full':
					$status = 'confirmed';
					break;
				case 'pending':
					$status = 'pending';
					break;
				case 'refunded':
					// Skip refunded bookings
					continue 2;
				default:
					$status = 'pending';
			}

			// Assign seats for this booking
			for ( $i = 0; $i < $num_tickets; $i++ ) {
				if ( $seat_number <= $capacity ) {
					$seats[] = array(
						'number' => $seat_number,
						'status' => $status,
						'booking_id' => $booking->id,
					);
					$seat_number++;
				}
			}
		}

		// Check if slot is locked based on progressive unlock
		$unlock_check = WTG_Availability_Controller::is_slot_unlocked( $date, $time_slot );
		$is_locked = ! $unlock_check['unlocked'];

		// Fill remaining seats
		while ( $seat_number <= $capacity ) {
			$seats[] = array(
				'number' => $seat_number,
				'status' => $is_locked ? 'locked' : 'available',
				'booking_id' => null,
			);
			$seat_number++;
		}

		return $seats;
	}

	/**
	 * Render seat grid HTML for a specific date and time slot.
	 *
	 * @param string $date      Tour date (Y-m-d).
	 * @param string $time_slot Time slot (fri_am, fri_pm, sat_am, sat_pm).
	 * @return string HTML for seat grid.
	 */
	public static function render_seat_grid( $date, $time_slot ) {
		$seats = self::get_seat_data( $date, $time_slot );
		$capacity = WTG_Availability_Controller::get_capacity();

		ob_start();
		?>
		<div class="wtg-seat-grid" data-date="<?php echo esc_attr( $date ); ?>" data-slot="<?php echo esc_attr( $time_slot ); ?>">
			<div class="wtg-seat-legend">
				<span class="wtg-legend-item">
					<span class="wtg-seat-box wtg-seat-pending" style="background-color: #ffeb3b; border: 1px solid #ddd; width: 20px; height: 20px; display: inline-block; margin-right: 5px;"></span>
					<span>PENDING</span>
				</span>
				<span class="wtg-legend-item">
					<span class="wtg-seat-box wtg-seat-confirmed" style="background-color: #4caf50; border: 1px solid #ddd; width: 20px; height: 20px; display: inline-block; margin-right: 5px;"></span>
					<span>CONFIRMED</span>
				</span>
				<span class="wtg-legend-item">
					<span class="wtg-seat-box wtg-seat-sold-out" style="background-color: #9e9e9e; border: 1px solid #ddd; width: 20px; height: 20px; display: inline-block; margin-right: 5px;"></span>
					<span>SOLD OUT</span>
				</span>
			</div>

			<div class="wtg-seats-container">
				<?php foreach ( $seats as $seat ) : ?>
					<div class="wtg-seat-box wtg-seat-<?php echo esc_attr( $seat['status'] ); ?>"
						 data-seat="<?php echo esc_attr( $seat['number'] ); ?>"
						 data-status="<?php echo esc_attr( $seat['status'] ); ?>"
						 style="
							width: 30px;
							height: 30px;
							display: inline-block;
							margin: 2px;
							border: 1px solid #ddd;
							<?php if ( 'confirmed' === $seat['status'] ) : ?>
								background-color: #4caf50;
							<?php elseif ( 'pending' === $seat['status'] ) : ?>
								background-color: #ffeb3b;
							<?php elseif ( 'locked' === $seat['status'] ) : ?>
								background-color: #9e9e9e;
							<?php else : ?>
								background-color: #fff;
							<?php endif; ?>
						"
						title="Seat <?php echo esc_attr( $seat['number'] ); ?> - <?php echo esc_attr( ucfirst( $seat['status'] ) ); ?>">
					</div>
				<?php endforeach; ?>
			</div>

			<div class="wtg-seat-summary" style="margin-top: 15px; font-size: 14px;">
				<?php
				$confirmed = count( array_filter( $seats, function( $s ) { return $s['status'] === 'confirmed'; } ) );
				$pending = count( array_filter( $seats, function( $s ) { return $s['status'] === 'pending'; } ) );
				$locked = count( array_filter( $seats, function( $s ) { return $s['status'] === 'locked'; } ) );
				$available = count( array_filter( $seats, function( $s ) { return $s['status'] === 'available'; } ) );

				$unlock_check = WTG_Availability_Controller::is_slot_unlocked( $date, $time_slot );
				?>
				<?php if ( ! $unlock_check['unlocked'] ) : ?>
					<p style="padding: 10px; background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; color: #856404; margin-bottom: 10px;">
						<strong>This time slot is locked.</strong><br>
						<?php echo esc_html( $unlock_check['reason'] ); ?>
					</p>
				<?php endif; ?>
				<p>
					<strong><?php echo $available; ?> of <?php echo $capacity; ?> seats available</strong>
					<?php if ( $confirmed > 0 ) : ?>
						<br>Confirmed: <?php echo $confirmed; ?>
					<?php endif; ?>
					<?php if ( $pending > 0 ) : ?>
						<br>Pending: <?php echo $pending; ?>
					<?php endif; ?>
					<?php if ( $locked > 0 ) : ?>
						<br>Locked: <?php echo $locked; ?>
					<?php endif; ?>
				</p>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
