<?php
/**
 * Admin Gift Certificates Management
 *
 * Handles gift certificate operations in the admin interface.
 *
 * @package WTG2
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Admin gift certificates class.
 */
class WTG_Admin_Gift_Certificates {

	/**
	 * Render gift certificates page.
	 */
	public static function render() {
		// Handle form submissions.
		self::handle_form_submission();

		// Determine which view to show.
		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
		$cert_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		if ( 'new' === $action ) {
			self::render_create_form();
		} elseif ( 'view' === $action && $cert_id ) {
			self::render_detail_view( $cert_id );
		} else {
			self::render_list_view();
		}
	}

	/**
	 * Handle form submission.
	 */
	private static function handle_form_submission() {
		// Handle cancel action.
		if ( isset( $_GET['action'] ) && 'cancel' === $_GET['action'] && isset( $_GET['id'] ) ) {
			check_admin_referer( 'wtg_cancel_cert_' . $_GET['id'] );

			$cert_id = absint( $_GET['id'] );
			WTG_Gift_Certificate::cancel( $cert_id );

			wp_redirect( admin_url( 'admin.php?page=wtg-gift-certificates&message=cancelled' ) );
			exit;
		}

		// Handle expire action.
		if ( isset( $_GET['action'] ) && 'expire' === $_GET['action'] && isset( $_GET['id'] ) ) {
			check_admin_referer( 'wtg_expire_cert_' . $_GET['id'] );

			$cert_id = absint( $_GET['id'] );
			WTG_Gift_Certificate::expire( $cert_id );

			wp_redirect( admin_url( 'admin.php?page=wtg-gift-certificates&message=expired' ) );
			exit;
		}

		// Handle create action.
		if ( isset( $_POST['wtg_create_certificate'] ) ) {
			check_admin_referer( 'wtg_create_certificate' );

			$data = array(
				'code'              => strtoupper( sanitize_text_field( $_POST['code'] ) ),
				'amount'            => floatval( $_POST['amount'] ),
				'purchaser_name'    => sanitize_text_field( $_POST['purchaser_name'] ),
				'purchaser_email'   => sanitize_email( $_POST['purchaser_email'] ),
				'recipient_name'    => sanitize_text_field( $_POST['recipient_name'] ),
				'recipient_email'   => sanitize_email( $_POST['recipient_email'] ),
				'expiration_date'   => sanitize_text_field( $_POST['expiration_date'] ),
				'notes'             => sanitize_textarea_field( $_POST['notes'] ),
			);

			WTG_Gift_Certificate::create( $data );

			wp_redirect( admin_url( 'admin.php?page=wtg-gift-certificates&message=created' ) );
			exit;
		}
	}

	/**
	 * Render list view.
	 */
	private static function render_list_view() {
		global $wpdb;

		// Get search parameter.
		$search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';

		// Pagination.
		$per_page = 20;
		$paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$offset = ( $paged - 1 ) * $per_page;

		// Build query.
		$table = $wpdb->prefix . 'wtg_gift_certificates';
		$where_clauses = array( '1=1' );

		if ( $search ) {
			$search_like = '%' . $wpdb->esc_like( $search ) . '%';
			$where_clauses[] = $wpdb->prepare(
				'(code LIKE %s OR purchaser_name LIKE %s OR purchaser_email LIKE %s OR recipient_name LIKE %s OR recipient_email LIKE %s)',
				$search_like,
				$search_like,
				$search_like,
				$search_like,
				$search_like
			);
		}

		if ( $status_filter ) {
			$where_clauses[] = $wpdb->prepare( 'status = %s', $status_filter );
		}

		$where = implode( ' AND ', $where_clauses );

		// Get total count.
		$total_items = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
		$total_pages = ceil( $total_items / $per_page );

		// Get certificates.
		$certificates = $wpdb->get_results(
			"SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT {$per_page} OFFSET {$offset}"
		);

		// Show message.
		if ( isset( $_GET['message'] ) ) {
			$message = sanitize_text_field( $_GET['message'] );
			$messages = array(
				'created'   => __( 'Gift certificate created successfully.', 'wtg2' ),
				'cancelled' => __( 'Gift certificate cancelled successfully.', 'wtg2' ),
				'expired'   => __( 'Gift certificate expired successfully.', 'wtg2' ),
			);

			if ( isset( $messages[ $message ] ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $message ] ) . '</p></div>';
			}
		}

		?>
		<div class="wrap wtg-admin-page">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Gift Certificates', 'wtg2' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wtg-gift-certificates&action=new' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New', 'wtg2' ); ?>
			</a>

			<!-- Search Box -->
			<form method="get" class="wtg-search-box">
				<input type="hidden" name="page" value="wtg-gift-certificates">
				<input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search certificates...', 'wtg2' ); ?>">
				<button type="submit" class="button"><?php esc_html_e( 'Search', 'wtg2' ); ?></button>
			</form>

			<!-- Filters -->
			<form method="get" class="wtg-filters">
				<input type="hidden" name="page" value="wtg-gift-certificates">
				<select name="status">
					<option value=""><?php esc_html_e( 'All Statuses', 'wtg2' ); ?></option>
					<option value="active" <?php selected( $status_filter, 'active' ); ?>><?php esc_html_e( 'Active', 'wtg2' ); ?></option>
					<option value="redeemed" <?php selected( $status_filter, 'redeemed' ); ?>><?php esc_html_e( 'Redeemed', 'wtg2' ); ?></option>
					<option value="expired" <?php selected( $status_filter, 'expired' ); ?>><?php esc_html_e( 'Expired', 'wtg2' ); ?></option>
					<option value="cancelled" <?php selected( $status_filter, 'cancelled' ); ?>><?php esc_html_e( 'Cancelled', 'wtg2' ); ?></option>
				</select>
				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'wtg2' ); ?></button>
				<?php if ( $search || $status_filter ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wtg-gift-certificates' ) ); ?>" class="button">
						<?php esc_html_e( 'Clear Filters', 'wtg2' ); ?>
					</a>
				<?php endif; ?>
			</form>

			<!-- Certificates Table -->
			<div class="wtg-table-wrapper">
				<table class="wtg-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Code', 'wtg2' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'wtg2' ); ?></th>
							<th><?php esc_html_e( 'Purchaser', 'wtg2' ); ?></th>
							<th><?php esc_html_e( 'Recipient', 'wtg2' ); ?></th>
							<th><?php esc_html_e( 'Expiration', 'wtg2' ); ?></th>
							<th><?php esc_html_e( 'Status', 'wtg2' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'wtg2' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $certificates ) ) : ?>
							<tr>
								<td colspan="7" style="text-align: center; padding: 40px;">
									<?php esc_html_e( 'No gift certificates found.', 'wtg2' ); ?>
								</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $certificates as $cert ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $cert->code ?? '' ); ?></strong></td>
									<td>$<?php echo number_format( floatval( $cert->amount ?? 0 ), 2 ); ?></td>
									<td>
										<?php echo esc_html( $cert->purchaser_name ?? '' ); ?><br>
										<small class="wtg-text-muted">
											<?php echo esc_html( $cert->purchaser_email ?? '' ); ?>
										</small>
									</td>
									<td>
										<?php echo esc_html( $cert->recipient_name ?? '' ); ?><br>
										<small class="wtg-text-muted">
											<?php echo esc_html( $cert->recipient_email ?? '' ); ?>
										</small>
									</td>
									<td>
										<?php
										if ( ! empty( $cert->expiration_date ) ) {
											echo esc_html( date( 'M j, Y', strtotime( $cert->expiration_date ) ) );
										} else {
											echo '<span class="wtg-text-muted">' . esc_html__( 'No expiration', 'wtg2' ) . '</span>';
										}
										?>
									</td>
									<td>
										<span class="wtg-status-badge <?php echo esc_attr( $cert->status ?? 'active' ); ?>">
											<?php echo esc_html( ucfirst( $cert->status ?? 'active' ) ); ?>
										</span>
									</td>
									<td>
										<div class="wtg-row-actions">
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=wtg-gift-certificates&action=view&id=' . ( $cert->id ?? 0 ) ) ); ?>">
												<?php esc_html_e( 'View', 'wtg2' ); ?>
											</a>
											<?php if ( 'active' === ( $cert->status ?? '' ) ) : ?>
												<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wtg-gift-certificates&action=cancel&id=' . ( $cert->id ?? 0 ) ), 'wtg_cancel_cert_' . ( $cert->id ?? 0 ) ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to cancel this certificate?', 'wtg2' ); ?>');">
													<?php esc_html_e( 'Cancel', 'wtg2' ); ?>
												</a>
												<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wtg-gift-certificates&action=expire&id=' . ( $cert->id ?? 0 ) ), 'wtg_expire_cert_' . ( $cert->id ?? 0 ) ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to expire this certificate?', 'wtg2' ); ?>');">
													<?php esc_html_e( 'Expire', 'wtg2' ); ?>
												</a>
											<?php endif; ?>
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
								esc_html__( 'Showing %d - %d of %d certificates', 'wtg2' ),
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
	 * Render create form.
	 */
	private static function render_create_form() {
		?>
		<div class="wrap wtg-admin-page">
			<h1><?php esc_html_e( 'Create Gift Certificate', 'wtg2' ); ?></h1>

			<form method="post" action="">
				<?php wp_nonce_field( 'wtg_create_certificate' ); ?>
				<input type="hidden" name="wtg_create_certificate" value="1">

				<table class="wtg-form-table">
					<tbody>
						<tr>
							<th><label for="code"><?php esc_html_e( 'Certificate Code', 'wtg2' ); ?> *</label></th>
							<td>
								<input type="text" id="code" name="code" value="<?php echo esc_attr( WTG_Gift_Certificate::generate_code() ); ?>" required>
								<span class="wtg-form-help"><?php esc_html_e( 'Unique code for this certificate (auto-generated).', 'wtg2' ); ?></span>
							</td>
						</tr>
						<tr>
							<th><label for="amount"><?php esc_html_e( 'Amount', 'wtg2' ); ?> *</label></th>
							<td>
								<input type="number" id="amount" name="amount" step="0.01" min="0" required>
							</td>
						</tr>
						<tr>
							<th><label for="purchaser_name"><?php esc_html_e( 'Purchaser Name', 'wtg2' ); ?> *</label></th>
							<td>
								<input type="text" id="purchaser_name" name="purchaser_name" required>
							</td>
						</tr>
						<tr>
							<th><label for="purchaser_email"><?php esc_html_e( 'Purchaser Email', 'wtg2' ); ?> *</label></th>
							<td>
								<input type="email" id="purchaser_email" name="purchaser_email" required>
							</td>
						</tr>
						<tr>
							<th><label for="recipient_name"><?php esc_html_e( 'Recipient Name', 'wtg2' ); ?></label></th>
							<td>
								<input type="text" id="recipient_name" name="recipient_name">
								<span class="wtg-form-help"><?php esc_html_e( 'Optional: Recipient if different from purchaser.', 'wtg2' ); ?></span>
							</td>
						</tr>
						<tr>
							<th><label for="recipient_email"><?php esc_html_e( 'Recipient Email', 'wtg2' ); ?></label></th>
							<td>
								<input type="email" id="recipient_email" name="recipient_email">
							</td>
						</tr>
						<tr>
							<th><label for="expiration_date"><?php esc_html_e( 'Expiration Date', 'wtg2' ); ?></label></th>
							<td>
								<input type="date" id="expiration_date" name="expiration_date">
								<span class="wtg-form-help"><?php esc_html_e( 'Optional: Leave blank for no expiration.', 'wtg2' ); ?></span>
							</td>
						</tr>
						<tr>
							<th><label for="notes"><?php esc_html_e( 'Notes', 'wtg2' ); ?></label></th>
							<td>
								<textarea id="notes" name="notes"></textarea>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary button-large">
						<?php esc_html_e( 'Create Certificate', 'wtg2' ); ?>
					</button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wtg-gift-certificates' ) ); ?>" class="button button-large">
						<?php esc_html_e( 'Cancel', 'wtg2' ); ?>
					</a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render detail view with redemption history.
	 *
	 * @param int $cert_id Certificate ID.
	 */
	private static function render_detail_view( $cert_id ) {
		$cert = WTG_Gift_Certificate::get_by_id( $cert_id );
		if ( ! $cert ) {
			wp_die( __( 'Certificate not found.', 'wtg2' ) );
		}

		// Get booking if redeemed.
		$booking = null;
		if ( 'redeemed' === $cert->status && ! empty( $cert->redeemed_by_booking_id ) ) {
			$booking = WTG_Booking::get_by_id( $cert->redeemed_by_booking_id );
		}

		?>
		<div class="wrap wtg-admin-page">
			<h1><?php esc_html_e( 'Gift Certificate Details', 'wtg2' ); ?></h1>

			<div class="wtg-widget" style="max-width: 800px;">
				<h3><?php echo esc_html( $cert->code ?? '' ); ?></h3>

				<table class="wtg-form-table">
					<tbody>
						<tr>
							<th><?php esc_html_e( 'Amount', 'wtg2' ); ?></th>
							<td>$<?php echo number_format( floatval( $cert->amount ?? 0 ), 2 ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Status', 'wtg2' ); ?></th>
							<td>
								<span class="wtg-status-badge <?php echo esc_attr( $cert->status ?? 'active' ); ?>">
									<?php echo esc_html( ucfirst( $cert->status ?? 'active' ) ); ?>
								</span>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Purchaser', 'wtg2' ); ?></th>
							<td>
								<?php echo esc_html( $cert->purchaser_name ?? '' ); ?><br>
								<?php echo esc_html( $cert->purchaser_email ?? '' ); ?>
							</td>
						</tr>
						<?php if ( ! empty( $cert->recipient_name ) ) : ?>
							<tr>
								<th><?php esc_html_e( 'Recipient', 'wtg2' ); ?></th>
								<td>
									<?php echo esc_html( $cert->recipient_name ?? '' ); ?><br>
									<?php echo esc_html( $cert->recipient_email ?? '' ); ?>
								</td>
							</tr>
						<?php endif; ?>
						<tr>
							<th><?php esc_html_e( 'Created', 'wtg2' ); ?></th>
							<td><?php echo esc_html( date( 'F j, Y g:i a', strtotime( $cert->created_at ?? 'now' ) ) ); ?></td>
						</tr>
						<?php if ( ! empty( $cert->expiration_date ) ) : ?>
							<tr>
								<th><?php esc_html_e( 'Expiration Date', 'wtg2' ); ?></th>
								<td><?php echo esc_html( date( 'F j, Y', strtotime( $cert->expiration_date ) ) ); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ( ! empty( $cert->notes ) ) : ?>
							<tr>
								<th><?php esc_html_e( 'Notes', 'wtg2' ); ?></th>
								<td><?php echo esc_html( $cert->notes ); ?></td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>

				<?php if ( 'redeemed' === ( $cert->status ?? '' ) ) : ?>
					<h3><?php esc_html_e( 'Redemption History', 'wtg2' ); ?></h3>
					<?php if ( $booking ) : ?>
						<table class="wtg-form-table">
							<tbody>
								<tr>
									<th><?php esc_html_e( 'Redeemed Date', 'wtg2' ); ?></th>
									<td><?php echo esc_html( date( 'F j, Y g:i a', strtotime( $cert->redeemed_at ?? 'now' ) ) ); ?></td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Booking', 'wtg2' ); ?></th>
									<td>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=wtg-bookings&action=edit&id=' . ( $booking['id'] ?? 0 ) ) ); ?>">
											<?php echo esc_html( $booking['booking_code'] ?? '' ); ?>
										</a>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Customer', 'wtg2' ); ?></th>
									<td>
										<?php echo esc_html( $booking['customer_name'] ?? '' ); ?><br>
										<?php echo esc_html( $booking['customer_email'] ?? '' ); ?>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Tour Date', 'wtg2' ); ?></th>
									<td><?php echo esc_html( date( 'F j, Y', strtotime( $booking['tour_date'] ?? 'now' ) ) ); ?></td>
								</tr>
							</tbody>
						</table>
					<?php else : ?>
						<p class="wtg-text-muted"><?php esc_html_e( 'Booking information not available.', 'wtg2' ); ?></p>
					<?php endif; ?>
				<?php endif; ?>

				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wtg-gift-certificates' ) ); ?>" class="button">
						&larr; <?php esc_html_e( 'Back to List', 'wtg2' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}
}
