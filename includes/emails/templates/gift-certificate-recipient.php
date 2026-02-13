<?php
/**
 * Gift Certificate Recipient Email Template
 *
 * Sent to the person receiving the gift certificate.
 *
 * @package WTG2
 * @subpackage Emails
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<h2>You've Received a Gift Certificate!</h2>

<p>Hi <?php echo esc_html( $recipient_name ); ?>,</p>

<p><?php echo esc_html( $purchaser_name ); ?> has sent you a Wine Tours Grapevine gift certificate! Get ready for an unforgettable wine tasting experience in Grapevine, Texas.</p>

<div class="info-box">
	<table>
		<tr>
			<td>Gift Certificate Code:</td>
			<td><strong style="font-size: 18px; color: #722F37;"><?php echo esc_html( $code ); ?></strong></td>
		</tr>
		<tr>
			<td>Value:</td>
			<td><strong>$<?php echo esc_html( $amount ); ?></strong></td>
		</tr>
		<tr>
			<td>From:</td>
			<td><?php echo esc_html( $purchaser_name ); ?></td>
		</tr>
	</table>
</div>

<?php if ( ! empty( $message ) ) : ?>
<div style="background-color: #f8f9fa; border-left: 4px solid #722F37; padding: 15px 20px; margin: 20px 0;">
	<p style="margin: 0 0 5px 0; color: #999999; font-size: 12px;">Personal message from <?php echo esc_html( $purchaser_name ); ?>:</p>
	<p style="margin: 0; color: #555555; font-style: italic;">"<?php echo esc_html( $message ); ?>"</p>
</div>
<?php endif; ?>

<div class="divider"></div>

<h2>How to Redeem</h2>

<ol style="color: #555555; margin: 15px 0; padding-left: 20px;">
	<li>Visit <a href="https://winetoursgrapevine.com">winetoursgrapevine.com</a> and choose your tour date</li>
	<li>Enter your gift certificate code <strong><?php echo esc_html( $code ); ?></strong> on the booking form</li>
	<li>The certificate value will be applied to your booking total</li>
</ol>

<div class="alert-box">
	<p><strong>Save this email!</strong> You'll need the code above when booking your tour.</p>
</div>

<div class="divider"></div>

<p>If you have any questions, please contact us at <a href="mailto:info@winetoursgrapevine.com">info@winetoursgrapevine.com</a>.</p>

<p style="margin-top: 25px;">
	Cheers,<br>
	<strong>The Wine Tours Grapevine Team</strong>
</p>
