<?php
/**
 * Base Email Template
 *
 * Provides HTML wrapper with branding, header, and footer for all emails.
 *
 * @package WTG2
 * @subpackage Emails
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $email_title ); ?></title>
	<style>
		body {
			margin: 0;
			padding: 0;
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
			font-size: 16px;
			line-height: 1.6;
			color: #333333;
			background-color: #f4f4f4;
		}
		.email-container {
			max-width: 600px;
			margin: 0 auto;
			background-color: #ffffff;
		}
		.email-header {
			background: linear-gradient(135deg, #6b1f3d 0%, #8b2f4d 100%);
			color: #ffffff;
			padding: 40px 30px;
			text-align: center;
		}
		.email-header h1 {
			margin: 0;
			font-size: 28px;
			font-weight: 600;
			letter-spacing: -0.5px;
		}
		.email-header p {
			margin: 10px 0 0;
			font-size: 14px;
			opacity: 0.9;
		}
		.email-body {
			padding: 40px 30px;
		}
		.email-body h2 {
			margin: 0 0 20px;
			font-size: 22px;
			font-weight: 600;
			color: #6b1f3d;
		}
		.email-body p {
			margin: 0 0 15px;
			color: #555555;
		}
		.info-box {
			background-color: #f9f9f9;
			border-left: 4px solid #6b1f3d;
			padding: 20px;
			margin: 25px 0;
		}
		.info-box table {
			width: 100%;
			border-collapse: collapse;
		}
		.info-box td {
			padding: 8px 0;
			vertical-align: top;
		}
		.info-box td:first-child {
			font-weight: 600;
			color: #333333;
			width: 140px;
		}
		.info-box td:last-child {
			color: #555555;
		}
		.button {
			display: inline-block;
			padding: 14px 32px;
			background: linear-gradient(135deg, #6b1f3d 0%, #8b2f4d 100%);
			color: #ffffff !important;
			text-decoration: none;
			border-radius: 4px;
			font-weight: 600;
			font-size: 16px;
			text-align: center;
			margin: 20px 0;
		}
		.button:hover {
			background: linear-gradient(135deg, #5a1a33 0%, #7a2843 100%);
		}
		.alert-box {
			background-color: #fff9e6;
			border-left: 4px solid #f0ad4e;
			padding: 15px 20px;
			margin: 20px 0;
		}
		.alert-box p {
			margin: 0;
			color: #856404;
		}
		.email-footer {
			background-color: #f9f9f9;
			padding: 30px;
			text-align: center;
			border-top: 1px solid #e0e0e0;
		}
		.email-footer p {
			margin: 5px 0;
			font-size: 14px;
			color: #777777;
		}
		.email-footer a {
			color: #6b1f3d;
			text-decoration: none;
		}
		.divider {
			height: 1px;
			background-color: #e0e0e0;
			margin: 30px 0;
		}
		@media only screen and (max-width: 600px) {
			.email-header {
				padding: 30px 20px;
			}
			.email-body {
				padding: 30px 20px;
			}
			.email-footer {
				padding: 20px;
			}
			.info-box td:first-child {
				width: 120px;
			}
		}
	</style>
</head>
<body>
	<div class="email-container">
		<!-- Header -->
		<div class="email-header">
			<h1>Wine Tours Grapevine</h1>
			<p>Premium Wine Tasting Experiences</p>
		</div>

		<!-- Body Content -->
		<div class="email-body">
			<?php echo $email_content; ?>
		</div>

		<!-- Footer -->
		<div class="email-footer">
			<p><strong>Wine Tours Grapevine</strong></p>
			<p>Grapevine, TX</p>
			<p>
				<a href="mailto:info@winetoursgrapevine.com">info@winetoursgrapevine.com</a>
			</p>
			<p style="margin-top: 15px; font-size: 12px; color: #999999;">
				This email was sent regarding your wine tour booking. Please do not reply to this email.
			</p>
		</div>
	</div>
</body>
</html>
