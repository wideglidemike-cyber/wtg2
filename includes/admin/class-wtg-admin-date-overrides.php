<?php
/**
 * Admin Date Overrides
 *
 * Calendar interface for managing date overrides.
 *
 * @package WTG2
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Admin date overrides class.
 */
class WTG_Admin_Date_Overrides {

	/**
	 * Render date overrides page.
	 */
	public static function render() {
		$current_month = isset( $_GET['month'] ) ? absint( $_GET['month'] ) : date( 'n' );
		$current_year = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : date( 'Y' );

		// Validate month/year.
		$current_month = max( 1, min( 12, $current_month ) );
		$current_year = max( 2020, min( 2030, $current_year ) );

		$month_data = self::get_month_data( $current_year, $current_month );

		?>
		<div class="wrap wtg-admin-page">
			<h1><?php esc_html_e( 'Date Overrides Calendar', 'wtg2' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Click on time slots to mark them as manually full or available. Color coding: Green (available), Yellow (naturally full from bookings), Red (manually marked full).', 'wtg2' ); ?>
			</p>

			<!-- Legend -->
			<div class="wtg-widget" style="margin-bottom: 20px; max-width: 600px;">
				<h3><?php esc_html_e( 'Legend', 'wtg2' ); ?></h3>
				<table style="width: 100%;">
					<tr>
						<td style="padding: 5px;">
							<span class="wtg-calendar-slot available" style="display: inline-block; width: 60px; text-align: center;">S-AM</span>
						</td>
						<td><?php esc_html_e( 'Available - Slot has open seats', 'wtg2' ); ?></td>
					</tr>
					<tr>
						<td style="padding: 5px;">
							<span class="wtg-calendar-slot made" style="display: inline-block; width: 60px; text-align: center;">S-PM</span>
						</td>
						<td><?php esc_html_e( 'Made - Threshold reached, next slot unlocked', 'wtg2' ); ?></td>
					</tr>
					<tr>
						<td style="padding: 5px;">
							<span class="wtg-calendar-slot naturally-full" style="display: inline-block; width: 60px; text-align: center;">F-PM</span>
						</td>
						<td><?php esc_html_e( 'Naturally Full - All seats sold through bookings', 'wtg2' ); ?></td>
					</tr>
					<tr>
						<td style="padding: 5px;">
							<span class="wtg-calendar-slot manually-full" style="display: inline-block; width: 60px; text-align: center;">S-AM</span>
						</td>
						<td><?php esc_html_e( 'Manually Full - Marked as unavailable by admin (click to toggle)', 'wtg2' ); ?></td>
					</tr>
					<tr>
						<td style="padding: 5px;">
							<span class="wtg-calendar-slot locked" style="display: inline-block; width: 60px; text-align: center;">F-AM</span>
						</td>
						<td><?php esc_html_e( 'Locked - Not yet unlocked by progressive unlock logic', 'wtg2' ); ?></td>
					</tr>
				</table>
				<p style="margin-top: 15px;">
					<strong><?php esc_html_e( 'Slot Labels:', 'wtg2' ); ?></strong>
					S-AM (Saturday 11am), S-PM (Saturday 5pm), F-PM (Friday 5pm), F-AM (Friday 11am)
				</p>
			</div>

			<div class="wtg-calendar-wrapper">
				<!-- Calendar Header with Navigation -->
				<div class="wtg-calendar-header">
					<div class="wtg-calendar-nav">
						<a href="<?php echo esc_url( self::get_calendar_url( $current_year, $current_month - 1 ) ); ?>" class="button">
							&laquo; <?php esc_html_e( 'Previous', 'wtg2' ); ?>
						</a>
					</div>
					<h3><?php echo esc_html( date( 'F Y', mktime( 0, 0, 0, $current_month, 1, $current_year ) ) ); ?></h3>
					<div class="wtg-calendar-nav">
						<a href="<?php echo esc_url( self::get_calendar_url( $current_year, $current_month + 1 ) ); ?>" class="button">
							<?php esc_html_e( 'Next', 'wtg2' ); ?> &raquo;
						</a>
					</div>
				</div>

				<!-- Calendar Grid -->
				<table class="wtg-calendar">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Sun', 'wtg2' ); ?></th>
							<th><?php esc_html_e( 'Mon', 'wtg2' ); ?></th>
							<th><?php esc_html_e( 'Tue', 'wtg2' ); ?></th>
							<th><?php esc_html_e( 'Wed', 'wtg2' ); ?></th>
							<th><?php esc_html_e( 'Thu', 'wtg2' ); ?></th>
							<th><?php esc_html_e( 'Fri', 'wtg2' ); ?></th>
							<th><?php esc_html_e( 'Sat', 'wtg2' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $month_data['weeks'] as $week ) : ?>
							<tr>
								<?php foreach ( $week as $day ) : ?>
									<td class="<?php echo $day['is_current_month'] ? '' : 'other-month'; ?>">
										<?php if ( $day['date'] ) : ?>
											<div class="wtg-calendar-date"><?php echo esc_html( $day['day'] ); ?></div>
											<?php if ( $day['is_tour_day'] ) : ?>
												<div class="wtg-calendar-slots">
													<?php foreach ( $day['slots'] as $slot_key => $slot_info ) : ?>
														<div class="wtg-calendar-slot <?php echo esc_attr( $slot_info['class'] ?? '' ); ?>"
															data-date="<?php echo esc_attr( $day['date'] ); ?>"
															data-slot="<?php echo esc_attr( $slot_key ); ?>"
															data-override="<?php echo esc_attr( ! empty( $slot_info['is_override'] ) ? '1' : '0' ); ?>"
															data-sold="<?php echo esc_attr( $slot_info['sold'] ?? 0 ); ?>"
															data-capacity="<?php echo esc_attr( $slot_info['capacity'] ?? 14 ); ?>"
															title="<?php echo esc_attr( $slot_info['title'] ?? '' ); ?>">
															<?php echo esc_html( $slot_info['label'] ?? '' ); ?>
														</div>
													<?php endforeach; ?>
												</div>
											<?php endif; ?>
										<?php endif; ?>
									</td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

		</div>
		<?php
	}

	/**
	 * Get calendar month data.
	 *
	 * @param int $year  Year.
	 * @param int $month Month (1-12).
	 * @return array Calendar data.
	 */
	public static function get_month_data( $year, $month ) {
		// Handle month overflow/underflow.
		if ( $month < 1 ) {
			$month = 12;
			$year--;
		} elseif ( $month > 12 ) {
			$month = 1;
			$year++;
		}

		$first_day = mktime( 0, 0, 0, $month, 1, $year );
		$days_in_month = date( 't', $first_day );
		$day_of_week = date( 'w', $first_day ); // 0 = Sunday

		$weeks = array();
		$current_week = array();

		// Fill in days from previous month.
		if ( $day_of_week > 0 ) {
			$prev_month = $month - 1;
			$prev_year = $year;
			if ( $prev_month < 1 ) {
				$prev_month = 12;
				$prev_year--;
			}
			$prev_month_days = date( 't', mktime( 0, 0, 0, $prev_month, 1, $prev_year ) );
			$prev_start = $prev_month_days - $day_of_week + 1;

			for ( $i = $prev_start; $i <= $prev_month_days; $i++ ) {
				$current_week[] = array(
					'date'             => sprintf( '%04d-%02d-%02d', $prev_year, $prev_month, $i ),
					'day'              => $i,
					'is_current_month' => false,
					'is_tour_day'      => false,
					'slots'            => array(),
				);
			}
		}

		// Fill in days for current month.
		for ( $day = 1; $day <= $days_in_month; $day++ ) {
			$date = sprintf( '%04d-%02d-%02d', $year, $month, $day );
			$day_num = (int) date( 'N', strtotime( $date ) ); // 1 = Monday, 7 = Sunday
			$is_tour_day = ( 5 === $day_num || 6 === $day_num ); // Friday or Saturday

			$slots = array();
			if ( $is_tour_day ) {
				$slots = self::get_slot_status( $date );
			}

			$current_week[] = array(
				'date'             => $date,
				'day'              => $day,
				'is_current_month' => true,
				'is_tour_day'      => $is_tour_day,
				'slots'            => $slots,
			);

			// End of week (Saturday).
			if ( count( $current_week ) === 7 ) {
				$weeks[] = $current_week;
				$current_week = array();
			}
		}

		// Fill in days from next month.
		if ( count( $current_week ) > 0 ) {
			$next_month = $month + 1;
			$next_year = $year;
			if ( $next_month > 12 ) {
				$next_month = 1;
				$next_year++;
			}

			$remaining = 7 - count( $current_week );
			for ( $i = 1; $i <= $remaining; $i++ ) {
				$current_week[] = array(
					'date'             => sprintf( '%04d-%02d-%02d', $next_year, $next_month, $i ),
					'day'              => $i,
					'is_current_month' => false,
					'is_tour_day'      => false,
					'slots'            => array(),
				);
			}
			$weeks[] = $current_week;
		}

		return array(
			'year'  => $year,
			'month' => $month,
			'weeks' => $weeks,
		);
	}

	/**
	 * Get slot status for a specific date.
	 *
	 * @param string $date Date (Y-m-d format).
	 * @return array Slot status data.
	 */
	private static function get_slot_status( $date ) {
		$capacity = WTG_Availability_Controller::get_capacity();
		$threshold = (int) get_option( 'wtg_unlock_threshold', 5 );
		$slots = array( 'sat_am', 'sat_pm', 'fri_pm', 'fri_am' );
		$slot_data = array();
		$slot_labels = array(
			'sat_am' => 'S-AM',
			'sat_pm' => 'S-PM',
			'fri_pm' => 'F-PM',
			'fri_am' => 'F-AM',
		);

		foreach ( $slots as $slot ) {
			$sold = WTG_Booking::count_tickets_sold( $date, $slot );
			$is_override = WTG_Date_Override::is_slot_full( $date, $slot );
			$unlock_check = WTG_Availability_Controller::is_slot_unlocked( $date, $slot );

			// Determine class and title.
			if ( ! $unlock_check['unlocked'] ) {
				$class = 'locked';
				$title = 'Locked: ' . $unlock_check['reason'];
			} elseif ( $is_override ) {
				$class = 'manually-full';
				$title = 'Manually marked as full. Click to remove override.';
			} elseif ( $sold >= $capacity ) {
				$class = 'naturally-full';
				$title = sprintf( 'Naturally full (%d/%d seats sold)', $sold, $capacity );
			} elseif ( $sold >= $threshold ) {
				$class = 'made';
				$title = sprintf( 'Made! Threshold reached (%d/%d seats sold). Next slot unlocked.', $sold, $capacity );
			} else {
				$class = 'available';
				$title = sprintf( 'Available (%d/%d seats sold). Click to mark as full.', $sold, $capacity );
			}

			$slot_data[ $slot ] = array(
				'label'       => $slot_labels[ $slot ],
				'class'       => $class,
				'title'       => $title,
				'sold'        => $sold,
				'capacity'    => $capacity,
				'is_override' => $is_override,
			);
		}

		return $slot_data;
	}

	/**
	 * Get calendar URL for a specific month/year.
	 *
	 * @param int $year  Year.
	 * @param int $month Month.
	 * @return string URL.
	 */
	private static function get_calendar_url( $year, $month ) {
		// Handle month overflow/underflow.
		if ( $month < 1 ) {
			$month = 12;
			$year--;
		} elseif ( $month > 12 ) {
			$month = 1;
			$year++;
		}

		return admin_url( 'admin.php?page=wtg-date-overrides&year=' . $year . '&month=' . $month );
	}
}
