<?php
/**
 * Balance Confirmation Email Template
 *
 * Sent when customer's booking is fully paid (via balance payment or gift certificate).
 *
 * @package WTG2
 * @subpackage Emails
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<?php if ( ! empty( $gc_applied ) ) : ?>
<h2>Booking Confirmed - You're All Set!</h2>

<p>Hi <?php echo esc_html( $customer_name ); ?>,</p>

<p>Your gift certificate has been applied and your wine tour booking is fully covered. You're all set for an amazing wine tasting experience!</p>
<?php else : ?>
<h2>Payment Complete - You're All Set!</h2>

<p>Hi <?php echo esc_html( $customer_name ); ?>,</p>

<p>Excellent news! We've received your final payment and your wine tour booking is now fully paid. You're all set for an amazing wine tasting experience!</p>
<?php endif; ?>

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
		<?php if ( ! empty( $gc_applied ) ) : ?>
		<tr>
			<td>Gift Certificate Applied:</td>
			<td><strong style="color: #28a745;">$<?php echo esc_html( $discount_applied ); ?></strong></td>
		</tr>
		<?php else : ?>
		<tr>
			<td>Total Paid:</td>
			<td><strong style="color: #28a745;">$<?php echo esc_html( $total_paid ); ?></strong></td>
		</tr>
		<?php endif; ?>
	</table>
</div>

<div style="background-color: #d4edda; border-left: 4px solid #28a745; padding: 15px 20px; margin: 20px 0;">
	<?php if ( ! empty( $gc_applied ) ) : ?>
	<p style="margin: 0; color: #155724;"><strong>&#10003; Booking Confirmed</strong> - Gift certificate applied, no further payment required!</p>
	<?php else : ?>
	<p style="margin: 0; color: #155724;"><strong>&#10003; Payment Complete</strong> - No further payment required!</p>
	<?php endif; ?>
</div>

<div class="divider"></div>

<h2>Important Tour Information</h2>

<p><strong>Meeting Location:</strong> Wine Tours Grapevine Departure Point<br>
<em>(Exact address will be sent 24 hours before your tour)</em></p>

<p><strong>Arrival Time:</strong> Please arrive <strong>15 minutes before</strong> your scheduled time:</p>
<ul style="color: #555555; margin: 10px 0; padding-left: 20px;">
	<li>Tour Time: <?php echo esc_html( $time_slot ); ?></li>
	<li>Please Arrive By: <?php echo esc_html( $arrival_time ); ?></li>
</ul>

<div class="divider"></div>

<h2>What to Bring</h2>

<ul style="color: #555555; margin: 15px 0; padding-left: 20px;">
	<li><strong>Valid Photo ID</strong> - Required for wine tasting (must be 21+)</li>
	<li><strong>Comfortable Shoes</strong> - You'll be walking between wineries</li>
	<li><strong>Camera</strong> - Capture memories of your wine country experience</li>
	<li><strong>Light Jacket</strong> - Some wineries may be cooler inside</li>
	<li><strong>Sunglasses & Sunscreen</strong> - For outdoor portions of the tour</li>
</ul>

<div class="divider"></div>

<h2>Tour Includes</h2>

<ul style="color: #555555; margin: 15px 0; padding-left: 20px;">
	<li>Visits to 3-4 premium Grapevine wineries</li>
	<li>Wine tasting flights at each location</li>
	<li>Expert tour guide with extensive wine knowledge</li>
	<li>Comfortable transportation between wineries</li>
	<li>Behind-the-scenes insights into Texas winemaking</li>
</ul>

<div class="alert-box">
	<p><strong>Cancellation Policy:</strong> Cancellations made 48+ hours before tour date receive a full refund. Cancellations within 48 hours are non-refundable.</p>
</div>

<div class="divider"></div>

<p>If you need to make any changes or have questions about your tour, please contact us at <a href="mailto:info@winetoursgrapevine.com">info@winetoursgrapevine.com</a> or call us at (555) 123-4567.</p>

<p><strong>Save this email</strong> for your records and reference on tour day!</p>

<p style="margin-top: 25px;">We're thrilled to share Grapevine's wine country with you!</p>

<p style="margin-top: 25px;">
	Cheers,<br>
	<strong>The Wine Tours Grapevine Team</strong>
</p>
