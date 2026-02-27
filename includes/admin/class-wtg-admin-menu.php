<?php
/**
 * Admin Menu Registration
 *
 * Registers the Wine Tours admin menu and subpages.
 *
 * @package WTG2
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Admin menu class.
 */
class WTG_Admin_Menu {

	/**
	 * The single instance of the class.
	 *
	 * @var WTG_Admin_Menu
	 */
	protected static $instance = null;

	/**
	 * Main WTG_Admin_Menu Instance.
	 *
	 * @return WTG_Admin_Menu - Main instance.
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
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Register admin menu and subpages.
	 */
	public function register_menu() {
		// Add top-level menu.
		add_menu_page(
			__( 'Wine Tours', 'wtg2' ),           // Page title
			__( 'Wine Tours', 'wtg2' ),           // Menu title
			'manage_options',                      // Capability
			'wine-tours',                          // Menu slug
			array( $this, 'render_dashboard' ),    // Callback
			'dashicons-tickets-alt',               // Icon
			30                                     // Position
		);

		// Dashboard submenu (same as parent).
		add_submenu_page(
			'wine-tours',
			__( 'Dashboard', 'wtg2' ),
			__( 'Dashboard', 'wtg2' ),
			'manage_options',
			'wine-tours',
			array( $this, 'render_dashboard' )
		);

		// Bookings submenu.
		add_submenu_page(
			'wine-tours',
			__( 'Bookings', 'wtg2' ),
			__( 'Bookings', 'wtg2' ),
			'manage_options',
			'wtg-bookings',
			array( $this, 'render_bookings' )
		);

		// Gift Certificates submenu.
		add_submenu_page(
			'wine-tours',
			__( 'Gift Certificates', 'wtg2' ),
			__( 'Gift Certificates', 'wtg2' ),
			'manage_options',
			'wtg-gift-certificates',
			array( $this, 'render_gift_certificates' )
		);

		// Date Overrides submenu.
		add_submenu_page(
			'wine-tours',
			__( 'Date Overrides', 'wtg2' ),
			__( 'Date Overrides', 'wtg2' ),
			'manage_options',
			'wtg-date-overrides',
			array( $this, 'render_date_overrides' )
		);

		// Email Log submenu.
		add_submenu_page(
			'wine-tours',
			__( 'Email Log', 'wtg2' ),
			__( 'Email Log', 'wtg2' ),
			'manage_options',
			'wtg-email-log',
			array( $this, 'render_email_log' )
		);

		// Settings submenu.
		add_submenu_page(
			'wine-tours',
			__( 'Settings', 'wtg2' ),
			__( 'Settings', 'wtg2' ),
			'manage_options',
			'wtg-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Render dashboard page.
	 */
	public function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'wtg2' ) );
		}

		WTG_Admin_Dashboard::render();
	}

	/**
	 * Render bookings page.
	 */
	public function render_bookings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'wtg2' ) );
		}

		WTG_Admin_Bookings::render();
	}

	/**
	 * Render gift certificates page.
	 */
	public function render_gift_certificates() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'wtg2' ) );
		}

		WTG_Admin_Gift_Certificates::render();
	}

	/**
	 * Render date overrides page.
	 */
	public function render_date_overrides() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'wtg2' ) );
		}

		WTG_Admin_Date_Overrides::render();
	}

	/**
	 * Render email log page.
	 */
	public function render_email_log() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'wtg2' ) );
		}

		WTG_Admin_Email_Log::render();
	}

	/**
	 * Render settings page.
	 */
	public function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'wtg2' ) );
		}

		WTG_Admin_Settings::render();
	}
}
