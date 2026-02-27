<?php
/**
 * Admin New Booking Notification Email Template
 *
 * Sent to the site admin when a new booking is created.
 *
 * @package WTG2
 * @subpackage Emails
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<h2>New Booking Received</h2>

<p>A new tour booking has been made on the website.</p>

<div class="info-box">
	<table>
		<tr>
			<td>Booking Code:</td>
			<td><strong><?php echo esc_html( $booking_code ); ?></strong></td>
		</tr>
		<tr>
			<td>Tour Date:</td>
			<td><?php echo esc_html( $tour_date ); ?></td>
		</tr>
		<tr>
			<td>Time Slot:</td>
			<td><?php echo esc_html( $time_slot ); ?></td>
		</tr>
		<tr>
			<td>Tickets:</td>
			<td><?php echo esc_html( $tickets ); ?></td>
		</tr>
		<tr>
			<td>Payment Status:</td>
			<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $payment_status ) ) ); ?></td>
		</tr>
	</table>
</div>

<div class="info-box">
	<table>
		<tr>
			<td>Customer:</td>
			<td><?php echo esc_html( $customer_name ); ?></td>
		</tr>
		<tr>
			<td>Email:</td>
			<td><?php echo esc_html( $customer_email ); ?></td>
		</tr>
		<tr>
			<td>Deposit Paid:</td>
			<td>$<?php echo esc_html( $deposit_amount ); ?></td>
		</tr>
		<tr>
			<td>Balance Due:</td>
			<td>$<?php echo esc_html( $balance_due ); ?></td>
		</tr>
		<?php if ( ! empty( $discount_applied ) ) : ?>
		<tr>
			<td>GC Discount:</td>
			<td>$<?php echo esc_html( $discount_applied ); ?></td>
		</tr>
		<?php endif; ?>
	</table>
</div>

<p style="margin-top: 25px; font-size: 13px; color: #999;">Booked on <?php echo esc_html( $booking_date ); ?></p>
