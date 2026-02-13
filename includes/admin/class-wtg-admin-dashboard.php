<?php
/**
 * Admin Dashboard
 *
 * Displays dashboard metrics and overview.
 *
 * @package WTG2
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Admin dashboard class.
 */
class WTG_Admin_Dashboard {

	/**
	 * Render dashboard page.
	 */
	public static function render() {
		?>
		<div class="wrap wtg-admin-page">
			<h1><?php echo esc_html__( 'Wine Tours Dashboard', 'wtg2' ); ?></h1>

			<div class="wtg-dashboard-widgets">
				<?php
				self::render_revenue_widget();
				self::render_booking_status_widget();
				self::render_upcoming_tours_widget();
				self::render_gift_certificate_widget();
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render revenue metrics widget.
	 */
	private static function render_revenue_widget() {
		global $wpdb;

		// Calculate revenue metrics.
		$table = $wpdb->prefix . 'wtg_bookings';

		// Total deposits collected.
		$deposits = $wpdb->get_var(
			"SELECT COALESCE(SUM(deposit_amount), 0)
			FROM {$table}
			WHERE payment_status IN ('deposit_paid', 'paid_full')"
		);

		// Total balance due (for deposit_paid bookings only).
		$balance_due = $wpdb->get_var(
			"SELECT COALESCE(SUM(total_amount - deposit_amount), 0)
			FROM {$table}
			WHERE payment_status = 'deposit_paid'"
		);

		// Total revenue (all paid bookings).
		$total_revenue = $wpdb->get_var(
			"SELECT COALESCE(SUM(total_amount), 0)
			FROM {$table}
			WHERE payment_status IN ('deposit_paid', 'paid_full')"
		);

		// Average booking value.
		$booking_count = $wpdb->get_var(
			"SELECT COUNT(*)
			FROM {$table}
			WHERE payment_status IN ('deposit_paid', 'paid_full')"
		);

		$avg_booking = $booking_count > 0 ? $total_revenue / $booking_count : 0;

		?>
		<div class="wtg-widget">
			<h3><?php esc_html_e( 'Revenue Metrics', 'wtg2' ); ?></h3>

			<div class="wtg-metric">
				<span class="wtg-metric-label"><?php esc_html_e( 'Total Deposits Collected', 'wtg2' ); ?></span>
				<span class="wtg-metric-value success">$<?php echo number_format( $deposits, 2 ); ?></span>
			</div>

			<div class="wtg-metric">
				<span class="wtg-metric-label"><?php esc_html_e( 'Balance Due', 'wtg2' ); ?></span>
				<span class="wtg-metric-value warning">$<?php echo number_format( $balance_due, 2 ); ?></span>
			</div>

			<div class="wtg-metric">
				<span class="wtg-metric-label"><?php esc_html_e( 'Total Revenue', 'wtg2' ); ?></span>
				<span class="wtg-metric-value">$<?php echo number_format( $total_revenue, 2 ); ?></span>
			</div>

			<div class="wtg-metric">
				<span class="wtg-metric-label"><?php esc_html_e( 'Average Booking Value', 'wtg2' ); ?></span>
				<span class="wtg-metric-value">$<?php echo number_format( $avg_booking, 2 ); ?></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render booking status widget.
	 */
	private static function render_booking_status_widget() {
		global $wpdb;

		$table = $wpdb->prefix . 'wtg_bookings';

		// Get counts and totals by status.
		$statuses = array( 'pending', 'deposit_paid', 'paid_full', 'manual', 'refunded' );
		$status_data = array();

		foreach ( $statuses as $status ) {
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE payment_status = %s",
					$status
				)
			);

			$total = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(total_amount), 0) FROM {$table} WHERE payment_status = %s",
					$status
				)
			);

			$status_data[ $status ] = array(
				'count' => $count,
				'total' => $total,
			);
		}

		?>
		<div class="wtg-widget">
			<h3><?php esc_html_e( 'Bookings by Status', 'wtg2' ); ?></h3>

			<?php foreach ( $statuses as $status ) : ?>
				<div class="wtg-metric">
					<span class="wtg-metric-label">
						<span class="wtg-status-badge <?php echo esc_attr( $status ); ?>">
							<?php echo esc_html( ucwords( str_replace( '_', ' ', $status ) ) ); ?>
						</span>
					</span>
					<span class="wtg-metric-value">
						<?php echo esc_html( $status_data[ $status ]['count'] ); ?> bookings
						<span style="font-size: 14px; color: #646970;">
							($<?php echo number_format( $status_data[ $status ]['total'], 2 ); ?>)
						</span>
					</span>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render upcoming tours widget.
	 */
	private static function render_upcoming_tours_widget() {
		global $wpdb;

		$table = $wpdb->prefix . 'wtg_bookings';
		$capacity = WTG_Availability_Controller::get_capacity();

		// Get next 10 unique tour dates.
		$today = date( 'Y-m-d' );
		$dates = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT tour_date
				FROM {$table}
				WHERE tour_date >= %s
				ORDER BY tour_date ASC
				LIMIT 10",
				$today
			)
		);

		?>
		<div class="wtg-widget">
			<h3><?php esc_html_e( 'Upcoming Tours', 'wtg2' ); ?></h3>

			<?php if ( empty( $dates ) ) : ?>
				<p class="wtg-text-muted"><?php esc_html_e( 'No upcoming tours scheduled.', 'wtg2' ); ?></p>
			<?php else : ?>
				<div style="max-height: 400px; overflow-y: auto;">
					<?php foreach ( $dates as $date ) : ?>
						<?php
						$date_formatted = date( 'D, M j, Y', strtotime( $date ) );

						// Get ticket counts for each slot.
						$slots = array( 'sat_am', 'sat_pm', 'fri_pm', 'fri_am' );
						$slot_counts = array();

						foreach ( $slots as $slot ) {
							$sold = WTG_Booking::count_tickets_sold( $date, $slot );
							$slot_counts[ $slot ] = $sold;
						}
						?>
						<div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #dcdcde;">
							<strong><?php echo esc_html( $date_formatted ); ?></strong>
							<div style="font-size: 13px; margin-top: 5px;">
								<?php foreach ( $slots as $slot ) : ?>
									<?php
									$sold = $slot_counts[ $slot ];
									$label = WTG_Availability_Controller::get_slot_label( $slot );
									$percentage = ( $sold / $capacity ) * 100;

									if ( $percentage >= 100 ) {
										$color = '#d63638'; // Full
									} elseif ( $percentage >= 75 ) {
										$color = '#dba617'; // Nearly full
									} else {
										$color = '#00a32a'; // Available
									}
									?>
									<div style="margin-top: 3px;">
										<span style="color: <?php echo esc_attr( $color ); ?>;">●</span>
										<?php echo esc_html( $label ); ?>:
										<?php echo esc_html( $sold ); ?>/<?php echo esc_html( $capacity ); ?> seats
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render gift certificate widget.
	 */
	private static function render_gift_certificate_widget() {
		global $wpdb;

		$table = $wpdb->prefix . 'wtg_gift_certificates';

		// Get counts and totals by status.
		$statuses = array( 'active', 'redeemed', 'expired', 'cancelled' );
		$status_data = array();

		foreach ( $statuses as $status ) {
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE status = %s",
					$status
				)
			);

			$total = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(amount), 0) FROM {$table} WHERE status = %s",
					$status
				)
			);

			$status_data[ $status ] = array(
				'count' => $count,
				'total' => $total,
			);
		}

		?>
		<div class="wtg-widget">
			<h3><?php esc_html_e( 'Gift Certificates', 'wtg2' ); ?></h3>

			<?php foreach ( $statuses as $status ) : ?>
				<div class="wtg-metric">
					<span class="wtg-metric-label">
						<span class="wtg-status-badge <?php echo esc_attr( $status ); ?>">
							<?php echo esc_html( ucfirst( $status ) ); ?>
						</span>
					</span>
					<span class="wtg-metric-value">
						<?php echo esc_html( $status_data[ $status ]['count'] ); ?> certificates
						<span style="font-size: 14px; color: #646970;">
							($<?php echo number_format( $status_data[ $status ]['total'], 2 ); ?>)
						</span>
					</span>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}
}
