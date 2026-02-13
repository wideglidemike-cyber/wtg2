<?php
/**
 * Gift Certificate Purchaser Confirmation Email Template
 *
 * Sent to the person who purchased the gift certificate.
 *
 * @package WTG2
 * @subpackage Emails
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<h2>Gift Certificate Purchase Confirmed!</h2>

<p>Hi <?php echo esc_html( $purchaser_name ); ?>,</p>

<p>Thank you for purchasing a Wine Tours Grapevine gift certificate! Here are the details of your purchase:</p>

<div class="info-box">
	<table>
		<tr>
			<td>Gift Certificate Code:</td>
			<td><strong><?php echo esc_html( $code ); ?></strong></td>
		</tr>
		<tr>
			<td>Amount:</td>
			<td><strong>$<?php echo esc_html( $amount ); ?></strong></td>
		</tr>
		<tr>
			<td>Recipient:</td>
			<td><?php echo esc_html( $recipient_name ); ?> (<?php echo esc_html( $recipient_email ); ?>)</td>
		</tr>
	</table>
</div>

<?php if ( ! empty( $message ) ) : ?>
<div style="background-color: #f8f9fa; border-left: 4px solid #722F37; padding: 15px 20px; margin: 20px 0;">
	<p style="margin: 0; color: #555555; font-style: italic;">"<?php echo esc_html( $message ); ?>"</p>
</div>
<?php endif; ?>

<div style="background-color: #d4edda; border-left: 4px solid #28a745; padding: 15px 20px; margin: 20px 0;">
	<p style="margin: 0; color: #155724;"><strong>&#10003; Notification Sent</strong> - We've emailed the gift certificate details to <?php echo esc_html( $recipient_name ); ?>.</p>
</div>

<div class="divider"></div>

<p>The recipient can use the code <strong><?php echo esc_html( $code ); ?></strong> when booking a wine tour on our website. The gift certificate value will be applied to their booking total.</p>

<p>If you have any questions, please contact us at <a href="mailto:info@winetoursgrapevine.com">info@winetoursgrapevine.com</a>.</p>

<p style="margin-top: 25px;">
	Cheers,<br>
	<strong>The Wine Tours Grapevine Team</strong>
</p>
