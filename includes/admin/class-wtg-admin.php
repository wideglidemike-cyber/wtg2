<?php
/**
 * Admin Controller
 *
 * Main admin controller for the WTG2 plugin admin interface.
 *
 * @package WTG2
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Main admin class.
 */
class WTG_Admin {

	/**
	 * The single instance of the class.
	 *
	 * @var WTG_Admin
	 */
	protected static $instance = null;

	/**
	 * Main WTG_Admin Instance.
	 *
	 * Ensures only one instance of WTG_Admin is loaded or can be loaded.
	 *
	 * @return WTG_Admin - Main instance.
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
		$this->init();
	}

	/**
	 * Load admin dependencies.
	 */
	private function load_dependencies() {
		require_once WTG2_PLUGIN_DIR . 'includes/admin/class-wtg-admin-menu.php';
		require_once WTG2_PLUGIN_DIR . 'includes/admin/class-wtg-admin-dashboard.php';
		require_once WTG2_PLUGIN_DIR . 'includes/admin/class-wtg-admin-bookings.php';
		require_once WTG2_PLUGIN_DIR . 'includes/admin/class-wtg-admin-gift-certificates.php';
		require_once WTG2_PLUGIN_DIR . 'includes/admin/class-wtg-admin-date-overrides.php';
		require_once WTG2_PLUGIN_DIR . 'includes/admin/class-wtg-admin-settings.php';
		require_once WTG2_PLUGIN_DIR . 'includes/admin/class-wtg-admin-email-log.php';
	}

	/**
	 * Initialize admin hooks.
	 */
	public function init() {
		// Initialize menu.
		WTG_Admin_Menu::get_instance();

		// Enqueue admin scripts and styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// AJAX hooks.
		add_action( 'wp_ajax_wtg_toggle_date_override', array( $this, 'ajax_toggle_date_override' ) );
		add_action( 'wp_ajax_wtg_quick_edit_booking', array( $this, 'ajax_quick_edit_booking' ) );
		add_action( 'wp_ajax_wtg_search_gift_certs', array( $this, 'ajax_search_gift_certs' ) );
		add_action( 'wp_ajax_wtg_get_calendar_month', array( $this, 'ajax_get_calendar_month' ) );
		add_action( 'wp_ajax_wtg_send_balance_invoice', array( $this, 'ajax_send_balance_invoice' ) );
		add_action( 'wp_ajax_wtg_resend_invoice_email', array( $this, 'ajax_resend_invoice_email' ) );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on our plugin pages.
		if ( strpos( $hook, 'wtg-' ) === false && strpos( $hook, 'wine-tours' ) === false ) {
			return;
		}

		// Enqueue admin CSS.
		wp_enqueue_style(
			'wtg-admin',
			WTG2_PLUGIN_URL . 'assets/css/wtg-admin.css',
			array(),
			WTG2_VERSION
		);

		// Enqueue dashboard scripts on dashboard page.
		if ( strpos( $hook, 'wine-tours' ) !== false ) {
			wp_enqueue_script(
				'wtg-admin-dashboard',
				WTG2_PLUGIN_URL . 'assets/js/wtg-admin-dashboard.js',
				array( 'jquery' ),
				WTG2_VERSION,
				true
			);
		}

		// Enqueue booking scripts on bookings page.
		if ( strpos( $hook, 'wtg-bookings' ) !== false ) {
			wp_enqueue_script(
				'wtg-admin-bookings',
				WTG2_PLUGIN_URL . 'assets/js/wtg-admin-bookings.js',
				array( 'jquery' ),
				WTG2_VERSION,
				true
			);
		}

		// Enqueue calendar scripts on date overrides page.
		if ( strpos( $hook, 'wtg-date-overrides' ) !== false ) {
			wp_enqueue_script(
				'wtg-admin-calendar',
				WTG2_PLUGIN_URL . 'assets/js/wtg-admin-calendar.js',
				array( 'jquery' ),
				WTG2_VERSION,
				true
			);
		}

		// Localize scripts with AJAX data.
		$ajax_data = array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wtg_admin' ),
		);

		wp_localize_script( 'wtg-admin-dashboard', 'wtgAdmin', $ajax_data );
		wp_localize_script( 'wtg-admin-bookings', 'wtgAdmin', $ajax_data );
		wp_localize_script( 'wtg-admin-calendar', 'wtgAdmin', $ajax_data );
	}

	/**
	 * AJAX handler to toggle date override.
	 */
	public function ajax_toggle_date_override() {
		check_ajax_referer( 'wtg_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
		}

		$date = isset( $_POST['date'] ) ? sanitize_text_field( $_POST['date'] ) : '';
		$time_slot = isset( $_POST['time_slot'] ) ? sanitize_text_field( $_POST['time_slot'] ) : '';
		$reason = isset( $_POST['reason'] ) ? sanitize_text_field( $_POST['reason'] ) : '';

		if ( empty( $date ) || empty( $time_slot ) ) {
			wp_send_json_error( array( 'message' => 'Date and time slot required.' ) );
		}

		// Check if override exists.
		$is_full = WTG_Date_Override::is_slot_full( $date, $time_slot );

		if ( $is_full ) {
			// Remove override.
			$result = WTG_Date_Override::remove_override( $date, $time_slot );
			$message = 'Slot unmarked as full.';
		} else {
			// Add override.
			$result = WTG_Date_Override::mark_slot_full( $date, $time_slot, $reason );
			$message = 'Slot marked as full.';
		}

		if ( $result ) {
			wp_send_json_success( array( 'message' => $message, 'is_full' => ! $is_full ) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to update override.' ) );
		}
	}

	/**
	 * AJAX handler for quick edit booking.
	 */
	public function ajax_quick_edit_booking() {
		check_ajax_referer( 'wtg_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
		}

		$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
		$payment_status = isset( $_POST['payment_status'] ) ? sanitize_text_field( $_POST['payment_status'] ) : '';

		if ( ! $booking_id || ! $payment_status ) {
			wp_send_json_error( array( 'message' => 'Invalid data.' ) );
		}

		$booking = WTG_Booking::get_by_id( $booking_id );
		if ( ! $booking ) {
			wp_send_json_error( array( 'message' => 'Booking not found.' ) );
		}

		$booking['payment_status'] = $payment_status;
		$result = WTG_Booking::update( $booking_id, $booking );

		if ( $result ) {
			wp_send_json_success( array( 'message' => 'Booking updated.' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to update booking.' ) );
		}
	}

	/**
	 * AJAX handler to search gift certificates.
	 */
	public function ajax_search_gift_certs() {
		check_ajax_referer( 'wtg_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
		}

		$search = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';

		if ( empty( $search ) ) {
			wp_send_json_error( array( 'message' => 'Search term required.' ) );
		}

		// Search by code.
		$cert = WTG_Gift_Certificate::get_by_code( $search );

		if ( $cert ) {
			wp_send_json_success( array( 'certificate' => $cert ) );
		} else {
			wp_send_json_error( array( 'message' => 'Certificate not found.' ) );
		}
	}

	/**
	 * AJAX handler to get calendar month data.
	 */
	public function ajax_get_calendar_month() {
		check_ajax_referer( 'wtg_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
		}

		$year = isset( $_POST['year'] ) ? absint( $_POST['year'] ) : date( 'Y' );
		$month = isset( $_POST['month'] ) ? absint( $_POST['month'] ) : date( 'n' );

		// Get calendar data for the month.
		$calendar_data = WTG_Admin_Date_Overrides::get_month_data( $year, $month );

		wp_send_json_success( array( 'data' => $calendar_data ) );
	}

	/**
	 * AJAX handler to manually send balance invoice.
	 */
	public function ajax_send_balance_invoice() {
		check_ajax_referer( 'wtg_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
		}

		$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;

		if ( ! $booking_id ) {
			wp_send_json_error( array( 'message' => 'Invalid booking ID.' ) );
		}

		// Get booking data.
		$booking = WTG_Booking::get_by_id( $booking_id );
		if ( ! $booking ) {
			wp_send_json_error( array( 'message' => 'Booking not found.' ) );
		}

		// Check if balance invoice already exists.
		if ( ! empty( $booking['balance_square_id'] ) ) {
			wp_send_json_error( array( 'message' => 'Balance invoice already sent.' ) );
		}

		// Check payment status.
		if ( 'deposit_paid' !== $booking['payment_status'] ) {
			wp_send_json_error( array( 'message' => 'Booking must have deposit paid status.' ) );
		}

		// Check if balance is zero (gift cert covered everything).
		if ( floatval( $booking['balance_due'] ) <= 0 ) {
			wp_send_json_error( array( 'message' => 'Balance is $0.00 — no invoice needed (gift certificate applied).' ) );
		}

		// Create balance invoice via Square.
		$invoice_result = WTG_Square_Invoice::create_balance_invoice( $booking_id, $booking );

		if ( $invoice_result['success'] ) {
			// Update booking with Square invoice ID.
			WTG_Booking::update(
				$booking_id,
				array( 'balance_square_id' => $invoice_result['invoice_id'] )
			);

			// Send balance invoice email to customer.
			WTG_Email_Templates::send_balance_invoice( $booking, $invoice_result['invoice_url'] );

			wp_send_json_success( array(
				'message' => 'Balance invoice created and sent successfully.',
				'invoice_id' => $invoice_result['invoice_id'],
			) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to create invoice: ' . $invoice_result['error'] ) );
		}
	}

	/**
	 * AJAX handler to resend invoice email.
	 */
	public function ajax_resend_invoice_email() {
		check_ajax_referer( 'wtg_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
		}

		$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
		$email_type = isset( $_POST['email_type'] ) ? sanitize_text_field( $_POST['email_type'] ) : '';

		if ( ! $booking_id || ! $email_type ) {
			wp_send_json_error( array( 'message' => 'Invalid data.' ) );
		}

		// Get booking data.
		$booking = WTG_Booking::get_by_id( $booking_id );
		if ( ! $booking ) {
			wp_send_json_error( array( 'message' => 'Booking not found.' ) );
		}

		// Send appropriate email based on type.
		switch ( $email_type ) {
			case 'deposit-confirmation':
				if ( in_array( $booking['payment_status'], array( 'deposit_paid', 'paid_full' ), true ) ) {
					WTG_Email_Templates::send_deposit_confirmation( $booking );
					$message = 'Deposit confirmation email sent successfully.';
				} else {
					wp_send_json_error( array( 'message' => 'Booking must have deposit paid or paid full status.' ) );
					return;
				}
				break;

			case 'balance-confirmation':
				if ( 'paid_full' === $booking['payment_status'] ) {
					WTG_Email_Templates::send_balance_confirmation( $booking );
					$message = 'Full payment confirmation email sent successfully.';
				} else {
					wp_send_json_error( array( 'message' => 'Booking must have paid full status.' ) );
					return;
				}
				break;

			default:
				wp_send_json_error( array( 'message' => 'Invalid email type.' ) );
				return;
		}

		wp_send_json_success( array( 'message' => $message ) );
	}
}
