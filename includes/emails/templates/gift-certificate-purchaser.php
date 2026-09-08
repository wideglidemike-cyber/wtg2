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

<?php
$codes    = isset( $codes ) && is_array( $codes ) ? $codes : array( $code );
$quantity = isset( $quantity ) ? intval( $quantity ) : count( $codes );
?>

<h2>Gift Certificate Purchase Confirmed!</h2>

<p>Hi <?php echo esc_html( $purchaser_name ); ?>,</p>

<p>Thank you for purchasing <?php echo $quantity > 1 ? esc_html( $quantity ) . ' Wine Tours Grapevine gift certificates' : 'a Wine Tours Grapevine gift certificate'; ?>! Here are the details of your purchase:</p>

<div class="info-box">
	<table>
		<?php if ( $quantity > 1 ) : ?>
			<tr>
				<td>Certificates:</td>
				<td><strong><?php echo esc_html( $quantity ); ?></strong></td>
			</tr>
			<tr>
				<td>Value Each:</td>
				<td><strong>$<?php echo esc_html( $amount ); ?></strong></td>
			</tr>
			<tr>
				<td>Total Paid:</td>
				<td><strong>$<?php echo esc_html( $total_amount ); ?></strong></td>
			</tr>
		<?php else : ?>
			<tr>
				<td>Gift Certificate Code:</td>
				<td><strong><?php echo esc_html( $code ); ?></strong></td>
			</tr>
			<tr>
				<td>Amount:</td>
				<td><strong>$<?php echo esc_html( $amount ); ?></strong></td>
			</tr>
		<?php endif; ?>
		<tr>
			<td>Recipient:</td>
			<td><?php echo esc_html( $recipient_name ); ?> (<?php echo esc_html( $recipient_email ); ?>)</td>
		</tr>
	</table>
</div>

<?php if ( $quantity > 1 ) : ?>
	<p style="margin-bottom: 8px;"><strong>Your gift certificate codes:</strong></p>
	<div class="info-box">
		<table>
			<?php foreach ( $codes as $i => $single_code ) : ?>
				<tr>
					<td style="width: 40px; color: #999;"><?php echo esc_html( $i + 1 ); ?>.</td>
					<td><strong style="font-size: 16px; color: #722F37; letter-spacing: 1px;"><?php echo esc_html( $single_code ); ?></strong></td>
					<td style="text-align: right;">$<?php echo esc_html( $amount ); ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
	</div>
	<p style="color: #555555;">Each code is separate and covers one tour, so they can be handed out individually.</p>
<?php endif; ?>

<?php if ( ! empty( $message ) ) : ?>
<div style="background-color: #f8f9fa; border-left: 4px solid #722F37; padding: 15px 20px; margin: 20px 0;">
	<p style="margin: 0; color: #555555; font-style: italic;">"<?php echo esc_html( $message ); ?>"</p>
</div>
<?php endif; ?>

<div style="background-color: #d4edda; border-left: 4px solid #28a745; padding: 15px 20px; margin: 20px 0;">
	<p style="margin: 0; color: #155724;"><strong>&#10003; Notification Sent</strong> - We've emailed the gift certificate <?php echo $quantity > 1 ? 'codes' : 'details'; ?> to <?php echo esc_html( $recipient_name ); ?>.</p>
</div>

<div class="divider"></div>

<p>The recipient can use <?php echo $quantity > 1 ? 'these codes' : 'the code <strong>' . esc_html( $code ) . '</strong>'; ?> when booking a wine tour on our website. The gift certificate value will be applied to their booking total, and any unused balance stays on the code for a future booking.</p>

<p>If you have any questions, please contact us at <a href="mailto:info@winetoursgrapevine.com">info@winetoursgrapevine.com</a>.</p>

<p style="margin-top: 25px;">
	Cheers,<br>
	<strong>The Wine Tours Grapevine Team</strong>
</p>
