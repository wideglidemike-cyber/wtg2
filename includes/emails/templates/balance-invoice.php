<?php
/**
 * Balance Invoice Email Template
 *
 * Sent 48 hours before tour date to remind customer of balance payment.
 *
 * @package WTG2
 * @subpackage Emails
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<h2>Your Wine Tour is Coming Up!</h2>

<p>Hi <?php echo esc_html( $customer_name ); ?>,</p>

<p>We're looking forward to hosting you on your wine tour! This is a friendly reminder that your balance payment is now due.</p>

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
			<td>Number of Tickets:</td>
			<td><?php echo esc_html( $tickets ); ?></td>
		</tr>
		<tr>
			<td>Amount Due:</td>
			<td><strong style="color: #6b1f3d; font-size: 18px;">$<?php echo esc_html( $balance_due ); ?></strong></td>
		</tr>
	</table>
</div>

<div style="text-align: center; margin: 30px 0;">
	<a href="<?php echo esc_url( $invoice_url ); ?>" class="button">Pay Balance Now</a>
</div>

<div class="alert-box">
	<p><strong>Payment Required:</strong> Please complete your balance payment before your tour date to ensure your reservation remains confirmed.</p>
</div>

<div class="divider"></div>

<h2>Tour Day Reminders</h2>

<p><strong>Arrival Time:</strong> Please arrive 15 minutes before your scheduled time slot. This ensures we can start promptly and you won't miss any part of the experience.</p>

<p><strong>What to Bring:</strong></p>
<ul style="color: #555555; margin: 15px 0; padding-left: 20px;">
	<li>Valid ID (required for wine tasting)</li>
	<li>Comfortable walking shoes</li>
	<li>Camera for photos</li>
	<li>Sunglasses and sunscreen (for outdoor portions)</li>
</ul>

<p><strong>Dress Code:</strong> Casual and comfortable. We recommend layers as temperatures can vary between indoor and outdoor areas.</p>

<div class="divider"></div>

<p>If you have any questions or concerns, please don't hesitate to contact us at <a href="mailto:info@winetoursgrapevine.com">info@winetoursgrapevine.com</a>.</p>

<p>We can't wait to show you the best of Grapevine wine country!</p>

<p style="margin-top: 25px;">
	Cheers,<br>
	<strong>The Wine Tours Grapevine Team</strong>
</p>
