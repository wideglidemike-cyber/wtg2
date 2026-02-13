<?php
/**
 * Deposit Confirmation Email Template
 *
 * Sent when customer pays their deposit.
 *
 * @package WTG2
 * @subpackage Emails
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<h2>Thank You for Your Deposit!</h2>

<p>Hi <?php echo esc_html( $customer_name ); ?>,</p>

<p>We've received your deposit payment and your wine tour booking is confirmed! Get ready for an unforgettable experience exploring Grapevine's finest wineries.</p>

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
			<td>Deposit Paid:</td>
			<td><strong>$<?php echo esc_html( $deposit_amount ); ?></strong></td>
		</tr>
		<tr>
			<td>Balance Due:</td>
			<td><strong>$<?php echo esc_html( $balance_due ); ?></strong></td>
		</tr>
	</table>
</div>

<div class="alert-box">
	<p><strong>Important:</strong> Your balance payment of $<?php echo esc_html( $balance_due ); ?> (includes 8.25% TX sales tax) is due before your tour date. We'll send you a payment reminder 72 hours before your scheduled tour.</p>
</div>

<div class="divider"></div>

<h2>What to Expect</h2>

<p>Our premium wine tour includes:</p>
<ul style="color: #555555; margin: 15px 0; padding-left: 20px;">
	<li>Visits to 3-4 carefully selected Grapevine wineries</li>
	<li>Professional and knowledgeable tour guide</li>
	<li>Comfortable transportation between locations</li>
	<li>Wine tasting experiences at each winery</li>
	<li>Insider knowledge about Texas wine country</li>
</ul>

<p><strong>Please arrive 15 minutes before your scheduled time.</strong> Our tour will depart promptly, and we want to ensure you don't miss out on any part of this amazing experience!</p>

<div class="divider"></div>

<p>If you have any questions or need to make changes to your booking, please contact us at <a href="mailto:info@winetoursgrapevine.com">info@winetoursgrapevine.com</a>.</p>

<p>We're excited to show you the best of Grapevine wine country!</p>

<p style="margin-top: 25px;">
	Cheers,<br>
	<strong>The Wine Tours Grapevine Team</strong>
</p>
