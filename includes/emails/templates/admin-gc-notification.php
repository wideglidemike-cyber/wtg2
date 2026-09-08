<?php
/**
 * Admin Gift Certificate Purchase Notification Email Template
 *
 * Sent to the site admin when a new gift certificate is purchased.
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

<h2>New Gift Certificate Purchase</h2>

<p><?php echo $quantity > 1 ? esc_html( $quantity ) . ' new gift certificates have' : 'A new gift certificate has'; ?> been purchased on the website.</p>

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
			<tr>
				<td>Codes:</td>
				<td><strong><?php echo esc_html( implode( ', ', $codes ) ); ?></strong></td>
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
			<td>Purchase Date:</td>
			<td><?php echo esc_html( $purchase_date ); ?></td>
		</tr>
	</table>
</div>

<div class="info-box">
	<table>
		<tr>
			<td>Purchaser:</td>
			<td><?php echo esc_html( $purchaser_name ); ?></td>
		</tr>
		<tr>
			<td>Purchaser Email:</td>
			<td><?php echo esc_html( $purchaser_email ); ?></td>
		</tr>
		<tr>
			<td>Recipient:</td>
			<td><?php echo esc_html( $recipient_name ); ?></td>
		</tr>
		<tr>
			<td>Recipient Email:</td>
			<td><?php echo esc_html( $recipient_email ); ?></td>
		</tr>
	</table>
</div>

<?php if ( ! empty( $message ) ) : ?>
<div style="background-color: #f8f9fa; border-left: 4px solid #722F37; padding: 15px 20px; margin: 20px 0;">
	<p style="margin: 0 0 5px; font-weight: 600; color: #333;">Personal Message:</p>
	<p style="margin: 0; color: #555555; font-style: italic;">"<?php echo esc_html( $message ); ?>"</p>
</div>
<?php endif; ?>

<p style="margin-top: 25px; font-size: 13px; color: #999;">Confirmation emails have been sent to both the purchaser and recipient.</p>
