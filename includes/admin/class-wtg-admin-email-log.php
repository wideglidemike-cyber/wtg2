<?php
/**
 * Admin Email Log
 *
 * Displays a log of all emails sent by the plugin.
 *
 * @package WTG2
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Admin email log class.
 */
class WTG_Admin_Email_Log {

	/**
	 * Render the email log page.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'wtg2' ) );
		}

		$logs = WTG_Email_Templates::get_log( 100 );

		?>
		<div class="wrap wtg-admin-page">
			<h1><?php esc_html_e( 'Email Log', 'wtg2' ); ?></h1>
			<p><?php esc_html_e( 'Recent emails sent by the plugin. Shows the last 100 entries.', 'wtg2' ); ?></p>

			<?php if ( empty( $logs ) ) : ?>
				<div class="notice notice-info">
					<p><?php esc_html_e( 'No emails have been logged yet. Emails will appear here after the next gift certificate purchase or booking.', 'wtg2' ); ?></p>
				</div>
			<?php else : ?>
				<table class="widefat fixed striped">
					<thead>
						<tr>
							<th style="width: 160px;"><?php esc_html_e( 'Date', 'wtg2' ); ?></th>
							<th style="width: 100px;"><?php esc_html_e( 'Type', 'wtg2' ); ?></th>
							<th style="width: 220px;"><?php esc_html_e( 'To', 'wtg2' ); ?></th>
							<th><?php esc_html_e( 'Subject', 'wtg2' ); ?></th>
							<th style="width: 80px;"><?php esc_html_e( 'Status', 'wtg2' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<td><?php echo esc_html( date( 'M j, Y g:i A', strtotime( $log->created_at ) ) ); ?></td>
								<td>
									<?php
									$type_labels = array(
										'gc_purchaser'         => 'GC Purchaser',
										'gc_recipient'         => 'GC Recipient',
										'admin_gc'             => 'Admin (GC)',
										'admin_booking'        => 'Admin (Booking)',
										'deposit_confirmation' => 'Deposit Confirm',
										'balance_confirmation' => 'Balance Confirm',
										'balance_invoice'      => 'Balance Invoice',
										'manual_booking'       => 'Manual Booking',
										'general'              => 'General',
									);
									$label = isset( $type_labels[ $log->email_type ] ) ? $type_labels[ $log->email_type ] : $log->email_type;
									echo esc_html( $label );
									?>
								</td>
								<td><?php echo esc_html( $log->to_email ); ?></td>
								<td><?php echo esc_html( $log->subject ); ?></td>
								<td>
									<?php if ( 'sent' === $log->status ) : ?>
										<span style="color: #28a745; font-weight: 600;">&#10003; Sent</span>
									<?php else : ?>
										<span style="color: #dc3545; font-weight: 600;">&#10007; Failed</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
