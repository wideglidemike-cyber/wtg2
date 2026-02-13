<?php
/**
 * Admin Bookings Management
 *
 * Handles booking CRUD operations in the admin interface.
 *
 * @package WTG2
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Admin bookings class.
 */
class WTG_Admin_Bookings {

	/**
	 * Render bookings page.
	 */
	public static function render() {
		// Handle form submissions.
		self::handle_form_submission();

		// Determine which view to show.
		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
		$booking_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		if ( 'edit' === $action && $booking_id ) {
			self::render_edit_form( $booking_id );
		} elseif ( 'new' === $action ) {
			self::render_edit_form( 0 );
		} else {
			self::render_list_view();
		}
	}

	/**
	 * Handle form submission.
	 */
	private static function handle_form_submission() {
		// Handle delete action.
		if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['id'] ) ) {
			check_admin_referer( 'wtg_delete_booking_' . $_GET['id'] );

			$booking_id = absint( $_GET['id'] );
			WTG_Booking::delete( $booking_id );

			wp_redirect( admin_url( 'admin.php?page=wtg-bookings&message=deleted' ) );
			exit;
		}

		// Handle save action.
		if ( isset( $_POST['wtg_save_booking'] ) ) {
			check_admin_referer( 'wtg_save_booking' );

			$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;

			// Sanitize and validate data.
			$data = array(
				'tour_date'         => sanitize_text_field( $_POST['tour_date'] ),
				'time_slot'         => sanitize_text_field( $_POST['time_slot'] ),
				'customer_name'     => sanitize_text_field( $_POST['customer_name'] ),
				'customer_email'    => sanitize_email( $_POST['customer_email'] ),
				'customer_phone'    => sanitize_text_field( $_POST['customer_phone'] ),
				'tickets'           => absint( $_POST['tickets'] ),
				'total_amount'      => floatval( $_POST['total_amount'] ),
				'deposit_amount'    => floatval( $_POST['deposit_amount'] ),
				'payment_status'    => sanitize_text_field( $_POST['payment_status'] ),
				'notes'             => sanitize_textarea_field( $_POST['notes'] ),
			);

			// For manual bookings, ensure amounts are zeroed out and balance_due is set.
			if ( 'manual' === $data['payment_status'] ) {
				$data['total_amount']   = 0;
				$data['deposit_amount'] = 0;
				$data['balance_due']    = 0;
			}

			// Handle gift certificate.
			if ( ! empty( $_POST['gift_cert_code'] ) ) {
				$data['gift_cert_code'] = sanitize_text_field( $_POST['gift_cert_code'] );
			}

			if ( $booking_id ) {
				// Check availability if tour date or time slot changed.
				$existing = WTG_Booking::get_by_id( $booking_id );

				if ( $existing && ( $existing['tour_date'] !== $data['tour_date'] || $existing['time_slot'] !== $data['time_slot'] ) ) {
					$availability = WTG_Availability_Controller::check_slot_availability(
						$data['tour_date'],
						$data['time_slot'],
						$data['tickets']
					);

					if ( ! $availability['available'] ) {
						$error_reason = urlencode( $availability['reason'] );
						wp_redirect( admin_url( 'admin.php?page=wtg-bookings&action=edit&id=' . $booking_id . '&error=slot_unavailable&reason=' . $error_reason ) );
						exit;
					}
				}

				// Update existing booking.
				WTG_Booking::update( $booking_id, $data );
				$message = 'updated';
			} else {
				// Check availability for new bookings.
				$availability = WTG_Availability_Controller::check_slot_availability(
					$data['tour_date'],
					$data['time_slot'],
					$data['tickets']
				);

				if ( ! $availability['available'] ) {
					$error_reason = urlencode( $availability['reason'] );
					wp_redirect( admin_url( 'admin.php?page=wtg-bookings&action=new&error=slot_unavailable&reason=' . $error_reason ) );
					exit;
				}

				// Create new booking.
				$booking_id = WTG_Booking::create( $data );
				$message = 'created';

				// Send confirmation email for manual bookings.
				if ( $booking_id && 'manual' === $data['payment_status'] ) {
					$booking = WTG_Booking::get_by_id( $booking_id );
					if ( $booking ) {
						WTG_Email_Templates::send_manual_booking_confirmation( $booking );
					}
				}
			}

			wp_redirect( admin_url( 'admin.php?page=wtg-bookings&message=' . $message ) );
			exit;
		}
	}

	/**
	 * Render list view.
	 */
	private static function render_list_view() {
		global $wpdb;

		// Get search and filter parameters.
		$search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '';
		$date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '';

		// Pagination.
		$per_page = 20;
		$paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$offset = ( $paged - 1 ) * $per_page;

		// Build query.
		$table = $wpdb->prefix . 'wtg_bookings';
		$where_clauses = array( '1=1' );

		if ( $search ) {
			$search_like = '%' . $wpdb->esc_like( $search ) . '%';
			$where_clauses[] = $wpdb->prepare(
				'(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s OR booking_code LIKE %s)',
				$search_like,
				$search_like,
				$search_like,
				$search_like,
				$search_like
			);
		}

		if ( $status_filter ) {
			$where_clauses[] = $wpdb->prepare( 'payment_status = %s', $status_filter );
		}

		if ( $date_from ) {
			$where_clauses[] = $wpdb->prepare( 'tour_date >= %s', $date_from );
		}

		if ( $date_to ) {
			$where_clauses[] = $wpdb->prepare( 'tour_date <= %s', $date_to );
		}

		$where = implode( ' AND ', $where_clauses );

		// Get total count.
		$total_items = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
		$total_pages = ceil( $total_items / $per_page );

		// Get bookings.
		$bookings = $wpdb->get_results(
			"SELECT * FROM {$table} WHERE {$where} ORDER BY tour_date DESC, created_at DESC LIMIT {$per_page} OFFSET {$offset}",
			ARRAY_A
		);

		// Show message.
		if ( isset( $_GET['message'] ) ) {
			$message = sanitize_text_field( $_GET['message'] );
			$messages = array(
				'created' => __( 'Booking created successfully.', 'wtg2' ),
				'updated' => __( 'Booking updated successfully.', 'wtg2' ),
				'deleted' => __( 'Booking deleted successfully.', 'wtg2' ),
			);

			if ( isset( $messages[ $message ] ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $message ] ) . '</p></div>';
			}
		}

		?>
		<div class="wrap wtg-admin-page">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Bookings', 'wtg2' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wtg-bookings&action=new' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New', 'wtg2' ); ?>
			</a>

			<!-- Search Box -->
			<form method="get" class="wtg-search-box">
				<input type="hidden" name="page" value="wtg-bookings">
				<input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search bookings...', 'wtg2' ); ?>">
				<button type="submit" class="button"><?php esc_html_e( 'Search', 'wtg2' ); ?></button>
			</form>

			<!-- Filters -->
			<form method="get" class="wtg-filters">
				<input type="hidden" name="page" value="wtg-bookings">
				<select name="status">
					<option value=""><?php esc_html_e( 'All Statuses', 'wtg2' ); ?></option>
					<option value="pending" <?php selected( $status_filter, 'pending' ); ?>><?php esc_html_e( 'Pending', 'wtg2' ); ?></option>
					<option value="deposit_paid" <?php selected( $status_filter, 'deposit_paid' ); ?>><?php esc_html_e( 'Deposit Paid', 'wtg2' ); ?></option>
					<option value="paid_full" <?php selected( $status_filter, 'paid_full' ); ?>><?php esc_html_e( 'Paid Full', 'wtg2' ); ?></option>
					<option value="manual" <?php selected( $status_filter, 'manual' ); ?>><?php esc_html_e( 'Manual', 'wtg2' ); ?></option>
					<option value="refunded" <?php selected( $status_filter, 'refunded' ); ?>><?php esc_html_e( 'Refunded', 'wtg2' ); ?></option>
				</select>
				<input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" placeholder="<?php esc_attr_e( 'From Date', 'wtg2' ); ?>">
				<input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" placeholder="<?php esc_attr_e( 'To Date', 'wtg2' ); ?>">
				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'wtg2' ); ?></button>
				<?php if ( $search || $status_filter || $date_from || $date_to ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wtg-bookings' ) ); ?>" class="button">
						<?php esc_html_e( 'Clear Filters', 'wtg2' ); ?>
					</a>
				<?php endif; ?>
			</form>

			<!-- Bookings Table -->
			<div class="wtg-table-wrapper">
				<table class="wtg-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Booking Code', 'wtg2' ); ?></th>
							<th><?php esc_html_e( 'Tour Date', 'wtg2' ); ?></th>
							<th><?php esc_html_e( 'Time Slot', 'wtg2' ); ?></th>
							<th><?php esc_html_e( 'Customer', 'wtg2' ); ?></th>
							<th><?php esc_html_e( 'Tickets', 'wtg2' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'wtg2' ); ?></th>
							<th><?php esc_html_e( 'Status', 'wtg2' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'wtg2' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $bookings ) ) : ?>
							<tr>
								<td colspan="8" style="text-align: center; padding: 40px;">
									<?php esc_html_e( 'No bookings found.', 'wtg2' ); ?>
								</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $bookings as $booking ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $booking['booking_code'] ?? '' ); ?></strong></td>
									<td><?php echo esc_html( date( 'M j, Y', strtotime( $booking['tour_date'] ?? 'now' ) ) ); ?></td>
									<td><?php echo esc_html( WTG_Availability_Controller::get_slot_label( $booking['time_slot'] ?? '' ) ); ?></td>
									<td>
										<?php echo esc_html( trim( ( $booking['first_name'] ?? '' ) . ' ' . ( $booking['last_name'] ?? '' ) ) ); ?><br>
										<small class="wtg-text-muted">
											<?php echo esc_html( $booking['email'] ?? '' ); ?>
										</small>
									</td>
									<td><?php echo esc_html( $booking['tickets'] ?? 0 ); ?></td>
									<td>$<?php echo number_format( floatval( $booking['total_amount'] ?? 0 ), 2 ); ?></td>
									<td>
										<span class="wtg-status-badge <?php echo esc_attr( $booking['payment_status'] ?? 'pending' ); ?>">
											<?php echo esc_html( ucwords( str_replace( '_', ' ', $booking['payment_status'] ?? 'pending' ) ) ); ?>
										</span>
									</td>
									<td>
										<div class="wtg-row-actions">
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=wtg-bookings&action=edit&id=' . ( $booking['id'] ?? 0 ) ) ); ?>">
												<?php esc_html_e( 'Edit', 'wtg2' ); ?>
											</a>
											<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wtg-bookings&action=delete&id=' . ( $booking['id'] ?? 0 ) ), 'wtg_delete_booking_' . ( $booking['id'] ?? 0 ) ) ); ?>" class="delete" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this booking?', 'wtg2' ); ?>');">
												<?php esc_html_e( 'Delete', 'wtg2' ); ?>
											</a>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>

				<!-- Pagination -->
				<?php if ( $total_pages > 1 ) : ?>
					<div class="wtg-pagination">
						<div class="wtg-pagination-info">
							<?php
							printf(
								esc_html__( 'Showing %d - %d of %d bookings', 'wtg2' ),
								$offset + 1,
								min( $offset + $per_page, $total_items ),
								$total_items
							);
							?>
						</div>
						<div class="wtg-pagination-links">
							<?php if ( $paged > 1 ) : ?>
								<a href="<?php echo esc_url( add_query_arg( 'paged', $paged - 1 ) ); ?>">&laquo; <?php esc_html_e( 'Previous', 'wtg2' ); ?></a>
							<?php endif; ?>

							<?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
								<?php if ( $i === $paged ) : ?>
									<span class="current"><?php echo esc_html( $i ); ?></span>
								<?php else : ?>
									<a href="<?php echo esc_url( add_query_arg( 'paged', $i ) ); ?>"><?php echo esc_html( $i ); ?></a>
								<?php endif; ?>
							<?php endfor; ?>

							<?php if ( $paged < $total_pages ) : ?>
								<a href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1 ) ); ?>"><?php esc_html_e( 'Next', 'wtg2' ); ?> &raquo;</a>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render edit/create form.
	 *
	 * @param int $booking_id Booking ID (0 for new booking).
	 */
	private static function render_edit_form( $booking_id ) {
		$booking = null;
		$is_new = ( 0 === $booking_id );

		if ( ! $is_new ) {
			$booking = WTG_Booking::get_by_id( $booking_id );
			if ( ! $booking ) {
				wp_die( __( 'Booking not found.', 'wtg2' ) );
			}
		}

		// Default values for new booking (manual = admin-created, no payment).
		$defaults = array(
			'tour_date'      => '',
			'time_slot'      => '',
			'customer_name'  => '',
			'customer_email' => '',
			'customer_phone' => '',
			'tickets'        => 1,
			'total_amount'   => 0,
			'deposit_amount' => 0,
			'payment_status' => 'manual',
			'gift_cert_code' => '',
			'notes'          => '',
		);

		$booking = $is_new ? $defaults : wp_parse_args( $booking, $defaults );

		?>
		<div class="wrap wtg-admin-page">
			<h1><?php echo $is_new ? esc_html__( 'Add New Booking', 'wtg2' ) : esc_html__( 'Edit Booking', 'wtg2' ); ?></h1>

			<?php if ( isset( $_GET['error'] ) && 'slot_unavailable' === $_GET['error'] ) : ?>
				<div class="notice notice-error is-dismissible">
					<p>
						<strong><?php esc_html_e( 'Slot unavailable:', 'wtg2' ); ?></strong>
						<?php echo esc_html( isset( $_GET['reason'] ) ? urldecode( $_GET['reason'] ) : __( 'The selected time slot is not available.', 'wtg2' ) ); ?>
					</p>
				</div>
			<?php endif; ?>

			<form method="post" action="">
				<?php wp_nonce_field( 'wtg_save_booking' ); ?>
				<input type="hidden" name="wtg_save_booking" value="1">
				<input type="hidden" name="booking_id" value="<?php echo esc_attr( $booking_id ); ?>">

				<table class="wtg-form-table">
					<tbody>
						<tr>
							<th><label for="tour_date"><?php esc_html_e( 'Tour Date', 'wtg2' ); ?> *</label></th>
							<td>
								<input type="date" id="tour_date" name="tour_date" value="<?php echo esc_attr( $booking['tour_date'] ); ?>" required>
							</td>
						</tr>
						<tr>
							<th><label for="time_slot"><?php esc_html_e( 'Time Slot', 'wtg2' ); ?> *</label></th>
							<td>
								<select id="time_slot" name="time_slot" required>
									<option value=""><?php esc_html_e( 'Select time slot', 'wtg2' ); ?></option>
									<option value="sat_am" <?php selected( $booking['time_slot'], 'sat_am' ); ?>>Saturday 11am to 3:45–4:15</option>
									<option value="sat_pm" <?php selected( $booking['time_slot'], 'sat_pm' ); ?>>Saturday 5pm to 9:45–10:15</option>
									<option value="fri_pm" <?php selected( $booking['time_slot'], 'fri_pm' ); ?>>Friday 5pm to 9:45–10:15</option>
									<option value="fri_am" <?php selected( $booking['time_slot'], 'fri_am' ); ?>>Friday 11am to 3:45–4:15</option>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="customer_name"><?php esc_html_e( 'Customer Name', 'wtg2' ); ?> *</label></th>
							<td>
								<input type="text" id="customer_name" name="customer_name" value="<?php echo esc_attr( $booking['customer_name'] ); ?>" required>
							</td>
						</tr>
						<tr>
							<th><label for="customer_email"><?php esc_html_e( 'Customer Email', 'wtg2' ); ?> *</label></th>
							<td>
								<input type="email" id="customer_email" name="customer_email" value="<?php echo esc_attr( $booking['customer_email'] ); ?>" required>
							</td>
						</tr>
						<tr>
							<th><label for="customer_phone"><?php esc_html_e( 'Customer Phone', 'wtg2' ); ?></label></th>
							<td>
								<input type="tel" id="customer_phone" name="customer_phone" value="<?php echo esc_attr( $booking['customer_phone'] ); ?>">
							</td>
						</tr>
						<tr>
							<th><label for="tickets"><?php esc_html_e( 'Number of Tickets', 'wtg2' ); ?> *</label></th>
							<td>
								<input type="number" id="tickets" name="tickets" value="<?php echo esc_attr( $booking['tickets'] ); ?>" min="1" max="14" required>
							</td>
						</tr>
						<tr>
							<th><label for="total_amount"><?php esc_html_e( 'Total Amount', 'wtg2' ); ?> *</label></th>
							<td>
								<input type="number" id="total_amount" name="total_amount" value="<?php echo esc_attr( $booking['total_amount'] ); ?>" step="0.01" min="0" required>
							</td>
						</tr>
						<tr>
							<th><label for="deposit_amount"><?php esc_html_e( 'Deposit Amount', 'wtg2' ); ?></label></th>
							<td>
								<input type="number" id="deposit_amount" name="deposit_amount" value="<?php echo esc_attr( $booking['deposit_amount'] ); ?>" step="0.01" min="0">
							</td>
						</tr>
						<tr>
							<th><label for="payment_status"><?php esc_html_e( 'Payment Status', 'wtg2' ); ?> *</label></th>
							<td>
								<select id="payment_status" name="payment_status" required>
									<option value="pending" <?php selected( $booking['payment_status'], 'pending' ); ?>><?php esc_html_e( 'Pending', 'wtg2' ); ?></option>
									<option value="deposit_paid" <?php selected( $booking['payment_status'], 'deposit_paid' ); ?>><?php esc_html_e( 'Deposit Paid', 'wtg2' ); ?></option>
									<option value="paid_full" <?php selected( $booking['payment_status'], 'paid_full' ); ?>><?php esc_html_e( 'Paid Full', 'wtg2' ); ?></option>
									<option value="manual" <?php selected( $booking['payment_status'], 'manual' ); ?>><?php esc_html_e( 'Manual (No Payment)', 'wtg2' ); ?></option>
									<option value="refunded" <?php selected( $booking['payment_status'], 'refunded' ); ?>><?php esc_html_e( 'Refunded', 'wtg2' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="gift_cert_code"><?php esc_html_e( 'Gift Certificate Code', 'wtg2' ); ?></label></th>
							<td>
								<input type="text" id="gift_cert_code" name="gift_cert_code" value="<?php echo esc_attr( $booking['gift_cert_code'] ); ?>">
								<span class="wtg-form-help"><?php esc_html_e( 'Optional: Enter gift certificate code if applicable.', 'wtg2' ); ?></span>
							</td>
						</tr>
						<tr>
							<th><label for="notes"><?php esc_html_e( 'Notes', 'wtg2' ); ?></label></th>
							<td>
								<textarea id="notes" name="notes"><?php echo esc_textarea( $booking['notes'] ); ?></textarea>
							</td>
						</tr>
					</tbody>
				</table>

				<?php if ( ! $is_new ) : ?>
					<!-- Invoice Actions -->
					<div class="wtg-invoice-actions" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #6b1f3d;">
						<h3 style="margin-top: 0;"><?php esc_html_e( 'Invoice Actions', 'wtg2' ); ?></h3>

						<?php if ( 'deposit_paid' === $booking['payment_status'] && empty( $booking['balance_square_id'] ) && floatval( $booking['balance_due'] ) > 0 ) : ?>
							<p>
								<button type="button" class="button wtg-send-balance-invoice" data-booking-id="<?php echo esc_attr( $booking_id ); ?>">
									<?php esc_html_e( 'Send Balance Invoice', 'wtg2' ); ?>
								</button>
								<span class="wtg-form-help"><?php esc_html_e( 'Manually send the balance invoice to the customer now.', 'wtg2' ); ?></span>
							</p>
						<?php elseif ( ! empty( $booking['balance_square_id'] ) ) : ?>
							<p style="color: #28a745;">
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'Balance invoice already sent.', 'wtg2' ); ?>
							</p>
						<?php endif; ?>

						<?php if ( in_array( $booking['payment_status'], array( 'deposit_paid', 'paid_full' ), true ) ) : ?>
							<p>
								<button type="button" class="button wtg-resend-invoice-email" data-booking-id="<?php echo esc_attr( $booking_id ); ?>" data-email-type="<?php echo 'paid_full' === $booking['payment_status'] ? 'balance-confirmation' : 'deposit-confirmation'; ?>">
									<?php esc_html_e( 'Resend Confirmation Email', 'wtg2' ); ?>
								</button>
								<span class="wtg-form-help">
									<?php
									if ( 'paid_full' === $booking['payment_status'] ) {
										esc_html_e( 'Resend the full payment confirmation email.', 'wtg2' );
									} else {
										esc_html_e( 'Resend the deposit confirmation email.', 'wtg2' );
									}
									?>
								</span>
							</p>
						<?php endif; ?>

						<div class="wtg-invoice-message" style="margin-top: 10px;"></div>
					</div>
				<?php endif; ?>

				<p class="submit">
					<button type="submit" class="button button-primary button-large">
						<?php echo $is_new ? esc_html__( 'Create Booking', 'wtg2' ) : esc_html__( 'Update Booking', 'wtg2' ); ?>
					</button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wtg-bookings' ) ); ?>" class="button button-large">
						<?php esc_html_e( 'Cancel', 'wtg2' ); ?>
					</a>
				</p>
			</form>
		</div>
		<?php
	}
}
