<?php
/**
 * Admin Settings
 *
 * Plugin settings page using WordPress Settings API.
 *
 * @package WTG2
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Admin settings class.
 */
class WTG_Admin_Settings {

	/**
	 * Option group name.
	 */
	const OPTION_GROUP = 'wtg_settings';

	/**
	 * Initialize settings.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Register settings.
	 */
	public static function register_settings() {
		// Tour Configuration Section.
		add_settings_section(
			'wtg_tour_config',
			__( 'Tour Configuration', 'wtg2' ),
			array( __CLASS__, 'tour_config_section_callback' ),
			self::OPTION_GROUP
		);

		// Seat Capacity.
		register_setting( self::OPTION_GROUP, 'wtg_seat_capacity', array(
			'type'              => 'integer',
			'default'           => 14,
			'sanitize_callback' => 'absint',
		) );

		add_settings_field(
			'wtg_seat_capacity',
			__( 'Seat Capacity', 'wtg2' ),
			array( __CLASS__, 'seat_capacity_callback' ),
			self::OPTION_GROUP,
			'wtg_tour_config'
		);

		// Unlock Threshold.
		register_setting( self::OPTION_GROUP, 'wtg_unlock_threshold', array(
			'type'              => 'integer',
			'default'           => 5,
			'sanitize_callback' => 'absint',
		) );

		add_settings_field(
			'wtg_unlock_threshold',
			__( 'Progressive Unlock Threshold', 'wtg2' ),
			array( __CLASS__, 'unlock_threshold_callback' ),
			self::OPTION_GROUP,
			'wtg_tour_config'
		);

		// Made Threshold (tour confirmed).
		register_setting( self::OPTION_GROUP, 'wtg_made_threshold', array(
			'type'              => 'integer',
			'default'           => 5,
			'sanitize_callback' => 'absint',
		) );

		add_settings_field(
			'wtg_made_threshold',
			__( 'Tour "Made" Threshold', 'wtg2' ),
			array( __CLASS__, 'made_threshold_callback' ),
			self::OPTION_GROUP,
			'wtg_tour_config'
		);

		// Invoice Hours Before.
		register_setting( self::OPTION_GROUP, 'wtg_invoice_hours_before', array(
			'type'              => 'integer',
			'default'           => 48,
			'sanitize_callback' => 'absint',
		) );

		add_settings_field(
			'wtg_invoice_hours_before',
			__( 'Invoice Timing', 'wtg2' ),
			array( __CLASS__, 'invoice_hours_callback' ),
			self::OPTION_GROUP,
			'wtg_tour_config'
		);

		// Square Integration Section.
		add_settings_section(
			'wtg_square_config',
			__( 'Square Integration', 'wtg2' ),
			array( __CLASS__, 'square_config_section_callback' ),
			self::OPTION_GROUP
		);

		// Square Environment.
		register_setting( self::OPTION_GROUP, 'wtg_square_environment', array(
			'type'              => 'string',
			'default'           => 'sandbox',
			'sanitize_callback' => 'sanitize_text_field',
		) );

		add_settings_field(
			'wtg_square_environment',
			__( 'Environment', 'wtg2' ),
			array( __CLASS__, 'square_environment_callback' ),
			self::OPTION_GROUP,
			'wtg_square_config'
		);

		// Application ID.
		register_setting( self::OPTION_GROUP, 'wtg_square_application_id', array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		) );

		add_settings_field(
			'wtg_square_application_id',
			__( 'Application ID', 'wtg2' ),
			array( __CLASS__, 'square_application_id_callback' ),
			self::OPTION_GROUP,
			'wtg_square_config'
		);

		// Webhook Signature Key.
		register_setting( self::OPTION_GROUP, 'wtg_square_webhook_signature', array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		) );

		add_settings_field(
			'wtg_square_webhook_signature',
			__( 'Webhook Signature Key', 'wtg2' ),
			array( __CLASS__, 'square_webhook_signature_callback' ),
			self::OPTION_GROUP,
			'wtg_square_config'
		);

		// Sandbox Access Token.
		register_setting( self::OPTION_GROUP, 'wtg_square_sandbox_access_token', array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		) );

		add_settings_field(
			'wtg_square_sandbox_access_token',
			__( 'Sandbox Access Token', 'wtg2' ),
			array( __CLASS__, 'square_sandbox_access_token_callback' ),
			self::OPTION_GROUP,
			'wtg_square_config'
		);

		// Sandbox Location ID.
		register_setting( self::OPTION_GROUP, 'wtg_square_sandbox_location_id', array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		) );

		add_settings_field(
			'wtg_square_sandbox_location_id',
			__( 'Sandbox Location ID', 'wtg2' ),
			array( __CLASS__, 'square_sandbox_location_id_callback' ),
			self::OPTION_GROUP,
			'wtg_square_config'
		);

		// Production Access Token.
		register_setting( self::OPTION_GROUP, 'wtg_square_production_access_token', array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		) );

		add_settings_field(
			'wtg_square_production_access_token',
			__( 'Production Access Token', 'wtg2' ),
			array( __CLASS__, 'square_production_access_token_callback' ),
			self::OPTION_GROUP,
			'wtg_square_config'
		);

		// Production Location ID.
		register_setting( self::OPTION_GROUP, 'wtg_square_production_location_id', array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		) );

		add_settings_field(
			'wtg_square_production_location_id',
			__( 'Production Location ID', 'wtg2' ),
			array( __CLASS__, 'square_production_location_id_callback' ),
			self::OPTION_GROUP,
			'wtg_square_config'
		);
	}

	/**
	 * Render settings page.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'wtg2' ) );
		}

		// Show message if settings saved.
		if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved successfully.', 'wtg2' ) . '</p></div>';
		}

		?>
		<div class="wrap wtg-admin-page">
			<h1><?php esc_html_e( 'Wine Tours Settings', 'wtg2' ); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::OPTION_GROUP );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Tour configuration section callback.
	 */
	public static function tour_config_section_callback() {
		echo '<p>' . esc_html__( 'Configure tour capacity and progressive unlock settings.', 'wtg2' ) . '</p>';
	}

	/**
	 * Seat capacity field callback.
	 */
	public static function seat_capacity_callback() {
		$value = get_option( 'wtg_seat_capacity', 14 );
		?>
		<input type="number" name="wtg_seat_capacity" value="<?php echo esc_attr( $value ); ?>" min="1" max="50" class="regular-text">
		<p class="description">
			<?php esc_html_e( 'Maximum number of seats available per time slot.', 'wtg2' ); ?>
		</p>
		<?php
	}

	/**
	 * Unlock threshold field callback.
	 */
	public static function unlock_threshold_callback() {
		$value = get_option( 'wtg_unlock_threshold', 5 );
		?>
		<input type="number" name="wtg_unlock_threshold" value="<?php echo esc_attr( $value ); ?>" min="1" max="50" class="regular-text">
		<p class="description">
			<?php esc_html_e( 'Number of paid tickets required to unlock the next time slot. Progressive order: Sat AM → Sat PM → Fri PM → Fri AM.', 'wtg2' ); ?>
		</p>
		<?php
	}

	/**
	 * Made threshold field callback.
	 */
	public static function made_threshold_callback() {
		$value = get_option( 'wtg_made_threshold', 5 );
		?>
		<input type="number" name="wtg_made_threshold" value="<?php echo esc_attr( $value ); ?>" min="1" max="50" class="regular-text">
		<p class="description">
			<?php esc_html_e( 'Minimum paid tickets for a tour to be "made" (confirmed). Seats show as pending/yellow until this number is reached, then switch to confirmed/green.', 'wtg2' ); ?>
		</p>
		<?php
	}

	/**
	 * Invoice hours field callback.
	 */
	public static function invoice_hours_callback() {
		$value = get_option( 'wtg_invoice_hours_before', 72 );
		?>
		<input type="number" name="wtg_invoice_hours_before" value="<?php echo esc_attr( $value ); ?>" min="1" max="168" class="regular-text">
		<p class="description">
			<?php esc_html_e( 'How many hours before the tour to send the Square invoice for balance due (Week 6 feature).', 'wtg2' ); ?>
		</p>
		<?php
	}

	/**
	 * Square configuration section callback.
	 */
	public static function square_config_section_callback() {
		echo '<p>' . esc_html__( 'Configure Square payment integration for automated invoice creation and payment processing.', 'wtg2' ) . '</p>';
	}

	/**
	 * Square environment field callback.
	 */
	public static function square_environment_callback() {
		$value = get_option( 'wtg_square_environment', 'sandbox' );
		?>
		<select name="wtg_square_environment" class="regular-text">
			<option value="sandbox" <?php selected( $value, 'sandbox' ); ?>><?php esc_html_e( 'Sandbox (Testing)', 'wtg2' ); ?></option>
			<option value="production" <?php selected( $value, 'production' ); ?>><?php esc_html_e( 'Production (Live)', 'wtg2' ); ?></option>
		</select>
		<p class="description">
			<?php esc_html_e( 'Select sandbox for testing, production for live payments. Credentials below are environment-specific.', 'wtg2' ); ?>
		</p>
		<?php
	}

	/**
	 * Application ID field callback.
	 */
	public static function square_application_id_callback() {
		$value = get_option( 'wtg_square_application_id', '' );
		?>
		<input type="text" name="wtg_square_application_id" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
		<p class="description">
			<?php esc_html_e( 'Your Square Application ID from the Square Developer Dashboard.', 'wtg2' ); ?>
		</p>
		<?php
	}

	/**
	 * Webhook signature key field callback.
	 */
	public static function square_webhook_signature_callback() {
		$value = get_option( 'wtg_square_webhook_signature', '' );
		?>
		<input type="password" name="wtg_square_webhook_signature" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
		<p class="description">
			<?php esc_html_e( 'Webhook signature key for validating Square webhook notifications. Find this in the Square Developer Dashboard under Webhooks.', 'wtg2' ); ?>
		</p>
		<?php
	}

	/**
	 * Sandbox access token field callback.
	 */
	public static function square_sandbox_access_token_callback() {
		$value = get_option( 'wtg_square_sandbox_access_token', '' );
		?>
		<input type="password" name="wtg_square_sandbox_access_token" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
		<p class="description">
			<?php esc_html_e( 'Your Square Sandbox access token for testing. Keep this secure.', 'wtg2' ); ?>
		</p>
		<?php
	}

	/**
	 * Sandbox location ID field callback.
	 */
	public static function square_sandbox_location_id_callback() {
		$value = get_option( 'wtg_square_sandbox_location_id', '' );
		?>
		<input type="text" name="wtg_square_sandbox_location_id" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
		<p class="description">
			<?php esc_html_e( 'Your Square Sandbox location ID for testing.', 'wtg2' ); ?>
		</p>
		<?php
	}

	/**
	 * Production access token field callback.
	 */
	public static function square_production_access_token_callback() {
		$value = get_option( 'wtg_square_production_access_token', '' );
		?>
		<input type="password" name="wtg_square_production_access_token" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
		<p class="description">
			<?php esc_html_e( 'Your Square Production access token for live payments. Keep this secure.', 'wtg2' ); ?>
		</p>
		<?php
	}

	/**
	 * Production location ID field callback.
	 */
	public static function square_production_location_id_callback() {
		$value = get_option( 'wtg_square_production_location_id', '' );
		?>
		<input type="text" name="wtg_square_production_location_id" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
		<p class="description">
			<?php esc_html_e( 'Your Square Production location ID for live payments.', 'wtg2' ); ?>
		</p>
		<?php
	}
}

// Initialize settings.
WTG_Admin_Settings::init();
