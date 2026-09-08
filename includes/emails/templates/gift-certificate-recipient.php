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

<?php
$codes    = isset( $codes ) && is_array( $codes ) ? $codes : array( $code );
$quantity = isset( $quantity ) ? intval( $quantity ) : count( $codes );
?>

<h2>You've Received <?php echo $quantity > 1 ? esc_html( $quantity ) . ' Gift Certificates' : 'a Gift Certificate'; ?>!</h2>

<p>Hi <?php echo esc_html( $recipient_name ); ?>,</p>

<?php if ( $quantity > 1 ) : ?>
	<p><?php echo esc_html( $purchaser_name ); ?> has sent you <strong><?php echo esc_html( $quantity ); ?> Wine Tours Grapevine gift certificates</strong>! Get ready for an unforgettable wine tasting experience in Grapevine, Texas.</p>

	<div class="info-box">
		<table>
			<tr>
				<td>Certificates:</td>
				<td><strong><?php echo esc_html( $quantity ); ?></strong></td>
			</tr>
			<tr>
				<td>Value Each:</td>
				<td><strong>$<?php echo esc_html( $amount ); ?></strong></td>
			</tr>
			<tr>
				<td>Total Value:</td>
				<td><strong>$<?php echo esc_html( $total_amount ); ?></strong></td>
			</tr>
			<tr>
				<td>From:</td>
				<td><?php echo esc_html( $purchaser_name ); ?></td>
			</tr>
		</table>
	</div>

	<p style="margin-bottom: 8px;"><strong>Your gift certificate codes:</strong></p>
	<div class="info-box">
		<table>
			<?php foreach ( $codes as $i => $single_code ) : ?>
				<tr>
					<td style="width: 40px; color: #999;"><?php echo esc_html( $i + 1 ); ?>.</td>
					<td><strong style="font-size: 18px; color: #722F37; letter-spacing: 1px;"><?php echo esc_html( $single_code ); ?></strong></td>
					<td style="text-align: right;">$<?php echo esc_html( $amount ); ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
	</div>

	<p style="color: #555555;">Each code is separate and covers one tour, so you can keep them or pass them along individually.</p>
<?php else : ?>
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
<?php endif; ?>

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
	<li>Enter <?php echo $quantity > 1 ? 'one of your gift certificate codes' : 'your gift certificate code <strong>' . esc_html( $code ) . '</strong>'; ?> on the booking form</li>
	<li>The certificate value will be applied to your booking total</li>
</ol>

<div class="alert-box">
	<p><strong>Save this email!</strong> You'll need <?php echo $quantity > 1 ? 'these codes' : 'the code above'; ?> when booking your tour.</p>
	<p style="margin-top: 8px;">If a code covers more than your booking, the unused balance stays on it for a future tour.</p>
</div>

<div class="divider"></div>

<p>If you have any questions, please contact us at <a href="mailto:info@winetoursgrapevine.com">info@winetoursgrapevine.com</a>.</p>

<p style="margin-top: 25px;">
	Cheers,<br>
	<strong>The Wine Tours Grapevine Team</strong>
</p>
