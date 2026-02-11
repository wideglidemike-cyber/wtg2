<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @package WTG2
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The core plugin class.
 */
class WTG_Plugin {

	/**
	 * The single instance of the class.
	 *
	 * @var WTG_Plugin
	 */
	protected static $instance = null;

	/**
	 * Main WTG_Plugin Instance.
	 *
	 * Ensures only one instance of WTG_Plugin is loaded or can be loaded.
	 *
	 * @return WTG_Plugin - Main instance.
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->define_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 */
	private function load_dependencies() {
		// Models.
		require_once WTG2_PLUGIN_DIR . 'includes/models/class-wtg-booking.php';
		require_once WTG2_PLUGIN_DIR . 'includes/models/class-wtg-gift-certificate.php';
		require_once WTG2_PLUGIN_DIR . 'includes/models/class-wtg-date-override.php';

		// Controllers.
		require_once WTG2_PLUGIN_DIR . 'includes/controllers/class-wtg-availability-controller.php';
		require_once WTG2_PLUGIN_DIR . 'includes/controllers/class-wtg-date-picker-controller.php';
		require_once WTG2_PLUGIN_DIR . 'includes/controllers/class-wtg-seating-grid-controller.php';

		// Integrations.
		require_once WTG2_PLUGIN_DIR . 'includes/integrations/class-wtg-gravity-forms.php';

		// Services.
		require_once WTG2_PLUGIN_DIR . 'includes/services/class-wtg-square-invoice.php';

		// Email.
		require_once WTG2_PLUGIN_DIR . 'includes/emails/class-wtg-email-templates.php';

		// API endpoints.
		require_once WTG2_PLUGIN_DIR . 'includes/api/class-wtg-square-webhook.php';

		// Admin.
		if ( is_admin() ) {
			require_once WTG2_PLUGIN_DIR . 'includes/admin/class-wtg-admin.php';
		}

		// Frontend (loaded later when needed).
	}

	/**
	 * Register all of the hooks related to the plugin.
	 */
	private function define_hooks() {
		// Admin hooks.
		if ( is_admin() ) {
			WTG_Admin::get_instance();
		}

		// Public hooks.
		if ( ! is_admin() ) {
			// Enqueue frontend scripts.
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
		}

		// Gravity Forms integration.
		if ( class_exists( 'GFForms' ) ) {
			new WTG_Gravity_Forms();
		}

		// REST API hooks.
		add_action( 'rest_api_init', array( 'WTG_Square_Webhook', 'register_routes' ) );

		// AJAX hooks for availability checking.
		add_action( 'wp_ajax_wtg_check_availability', array( $this, 'ajax_check_availability' ) );
		add_action( 'wp_ajax_nopriv_wtg_check_availability', array( $this, 'ajax_check_availability' ) );

		// AJAX hooks for seating grid.
		add_action( 'wp_ajax_wtg_get_seating_grid', array( $this, 'ajax_get_seating_grid' ) );
		add_action( 'wp_ajax_nopriv_wtg_get_seating_grid', array( $this, 'ajax_get_seating_grid' ) );

		// Cron hooks.
		add_action( 'wtg_send_pending_invoices', array( $this, 'process_pending_invoices' ) );
	}

	/**
	 * Run the plugin.
	 */
	public function run() {
		// Plugin is initialized via constructor and hooks.
		// Additional runtime logic can be added here if needed.
	}

	/**
	 * Enqueue frontend scripts and styles.
	 */
	public function enqueue_frontend_scripts() {
		// Enqueue frontend styles.
		wp_enqueue_style(
			'wtg-frontend',
			WTG2_PLUGIN_URL . 'assets/css/wtg-frontend.css',
			array(),
			WTG2_VERSION
		);

		// Enqueue gift certificate validation script.
		wp_enqueue_script(
			'wtg-gift-cert-validation',
			WTG2_PLUGIN_URL . 'assets/js/gift-cert-validation.js',
			array( 'jquery', 'gform_gravityforms' ),
			WTG2_VERSION,
			true
		);

		// Enqueue availability checking script.
		wp_enqueue_script(
			'wtg-availability',
			WTG2_PLUGIN_URL . 'assets/js/availability.js',
			array( 'jquery' ),
			WTG2_VERSION,
			true
		);

		// Localize scripts with AJAX URL and nonces.
		$ajax_data = array(
			'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
			'nonceGiftCert'      => wp_create_nonce( 'wtg_validate_gc' ),
			'nonceAvailability'  => wp_create_nonce( 'wtg_availability' ),
		);

		wp_localize_script( 'wtg-gift-cert-validation', 'wtgAjax', $ajax_data );
		wp_localize_script( 'wtg-availability', 'wtgAjax', $ajax_data );
	}

	/**
	 * Process pending invoices (cron callback).
	 *
	 * Runs hourly. Publishes draft balance invoices 72 hours before tour date.
	 * Draft invoices are created at booking time in Gravity Forms integration.
	 */
	public function process_pending_invoices() {
		// Get hours before tour to send invoice.
		$hours_before = get_option( 'wtg_invoice_hours_before', 72 );

		// Get bookings with draft invoices ready to publish.
		$bookings = WTG_Booking::get_pending_invoices( $hours_before );

		if ( empty( $bookings ) ) {
			return;
		}

		error_log( sprintf( 'WTG2: Publishing %d pending invoice(s).', count( $bookings ) ) );

		foreach ( $bookings as $booking ) {
			$invoice_id = $booking['balance_square_id'];

			// Publish the draft invoice — sends email to customer via Square.
			$publish_result = WTG_Square_Invoice::publish_invoice( $invoice_id, $booking['id'] );

			if ( $publish_result['success'] ) {
				// Mark invoice as sent.
				WTG_Booking::update(
					$booking['id'],
					array( 'invoice_sent_at' => current_time( 'mysql' ) )
				);

				// Send our own balance invoice notification email.
				WTG_Email_Templates::send_balance_invoice( $booking, $publish_result['invoice_url'] );

				error_log( sprintf(
					'WTG2: Balance invoice %s published for booking %d',
					$invoice_id,
					$booking['id']
				) );
			} else {
				error_log( sprintf(
					'WTG2: Failed to publish invoice %s for booking %d: %s',
					$invoice_id,
					$booking['id'],
					$publish_result['error']
				) );
			}
		}
	}

	/**
	 * AJAX handler for availability checking.
	 *
	 * Checks slot availability for a specific date and time slot.
	 */
	public function ajax_check_availability() {
		// Check nonce.
		check_ajax_referer( 'wtg_availability', 'nonce' );

		$tour_date = isset( $_POST['tour_date'] ) ? sanitize_text_field( $_POST['tour_date'] ) : '';
		$time_slot = isset( $_POST['time_slot'] ) ? sanitize_text_field( $_POST['time_slot'] ) : '';
		$tickets = isset( $_POST['tickets'] ) ? absint( $_POST['tickets'] ) : 1;

		if ( empty( $tour_date ) ) {
			wp_send_json_error( array( 'message' => 'Tour date is required.' ) );
		}

		// If time_slot is provided, check specific slot.
		if ( ! empty( $time_slot ) ) {
			$availability = WTG_Availability_Controller::check_slot_availability( $tour_date, $time_slot, $tickets );

			if ( $availability['available'] ) {
				wp_send_json_success( $availability );
			} else {
				wp_send_json_error( $availability );
			}
		}

		// Otherwise, return all slots for the date.
		$slots = WTG_Availability_Controller::get_available_slots( $tour_date, $tickets );
		wp_send_json_success( array( 'slots' => $slots ) );
	}

	/**
	 * AJAX handler for getting seating grid HTML.
	 *
	 * Returns the rendered seat-by-seat grid for a specific date and time slot.
	 */
	public function ajax_get_seating_grid() {
		// Check nonce.
		check_ajax_referer( 'wtg_availability', 'nonce' );

		$date = isset( $_POST['date'] ) ? sanitize_text_field( $_POST['date'] ) : '';
		$time_slot = isset( $_POST['time_slot'] ) ? sanitize_text_field( $_POST['time_slot'] ) : '';

		if ( empty( $date ) || empty( $time_slot ) ) {
			wp_send_json_error( array( 'message' => 'Date and time slot are required.' ) );
		}

		// Convert time slot to database format.
		$time_slot = $this->convert_time_slot_to_db_format( $time_slot );

		// Generate seat grid HTML.
		$grid_html = WTG_Seating_Grid_Controller::render_seat_grid( $date, $time_slot );

		wp_send_json_success( array( 'html' => $grid_html ) );
	}

	/**
	 * Convert time slot label to database format.
	 *
	 * @param string $label Time slot label from form.
	 * @return string Database format (fri_am, fri_pm, sat_am, sat_pm).
	 */
	private function convert_time_slot_to_db_format( $label ) {
		// If already in database format, return as-is.
		if ( in_array( $label, array( 'fri_am', 'fri_pm', 'sat_am', 'sat_pm' ), true ) ) {
			return $label;
		}

		$label = strtolower( trim( $label ) );

		// Map various label formats to database format.
		$map = array(
			'friday 11am to 3-4:15'    => 'fri_am',
			'friday 5pm to 9:45-10:15' => 'fri_pm',
			'saturday 11am to 3:45-4:15' => 'sat_am',
			'saturday 5pm to 9:45-10:15' => 'sat_pm',
			'friday am'                => 'fri_am',
			'friday pm'                => 'fri_pm',
			'saturday am'              => 'sat_am',
			'saturday pm'              => 'sat_pm',
			'fri am'                   => 'fri_am',
			'fri pm'                   => 'fri_pm',
			'sat am'                   => 'sat_am',
			'sat pm'                   => 'sat_pm',
		);

		foreach ( $map as $pattern => $db_format ) {
			if ( strpos( $label, $pattern ) !== false ) {
				return $db_format;
			}
		}

		return $label;
	}
}
