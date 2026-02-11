<?php
/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @package WTG2
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Plugin activation class.
 */
class WTG_Activator {

	/**
	 * Activate the plugin.
	 *
	 * Creates database tables and sets default options.
	 */
	public static function activate() {
		self::create_tables();
		self::set_default_options();
		self::schedule_cron();

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Create custom database tables.
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$prefix         = $wpdb->prefix;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Table 1: Bookings.
		$table_bookings = $prefix . 'wtg_bookings';
		$sql_bookings   = "CREATE TABLE {$table_bookings} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_code varchar(20) DEFAULT NULL,
			gf_entry_id bigint(20) UNSIGNED NOT NULL,
			tour_date date NOT NULL,
			time_slot enum('fri_am','fri_pm','sat_am','sat_pm') NOT NULL,
			tickets tinyint(3) UNSIGNED NOT NULL,
			first_name varchar(100) DEFAULT NULL,
			last_name varchar(100) DEFAULT NULL,
			email varchar(255) DEFAULT NULL,
			phone varchar(20) DEFAULT NULL,
			deposit_amount decimal(10,2) DEFAULT NULL,
			balance_due decimal(10,2) DEFAULT NULL,
			payment_status enum('pending','deposit_paid','paid_full','refunded') DEFAULT 'pending',
			gift_cert_id bigint(20) UNSIGNED DEFAULT NULL,
			discount_applied decimal(10,2) DEFAULT 0.00,
			invoice_square_id varchar(100) DEFAULT NULL,
			invoice_sent_at datetime DEFAULT NULL,
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY idx_booking_code (booking_code),
			UNIQUE KEY idx_gf_entry (gf_entry_id),
			KEY idx_tour_date_slot (tour_date, time_slot),
			KEY idx_payment_status (payment_status),
			KEY idx_invoice_pending (invoice_sent_at, created_at)
		) {$charset_collate};";

		dbDelta( $sql_bookings );

		// Migration: Add booking_code column if it doesn't exist and backfill.
		self::migrate_booking_codes();

		// Migration: Add missing columns for Square integration.
		self::migrate_square_columns();

		// Table 2: Gift Certificates.
		$table_gift_certs = $prefix . 'wtg_gift_certificates';
		$sql_gift_certs   = "CREATE TABLE {$table_gift_certs} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			code varchar(20) NOT NULL,
			gf_entry_id bigint(20) UNSIGNED NOT NULL,
			purchaser_name varchar(255) DEFAULT NULL,
			purchaser_email varchar(255) DEFAULT NULL,
			recipient_name varchar(255) DEFAULT NULL,
			recipient_email varchar(255) DEFAULT NULL,
			amount decimal(10,2) NOT NULL,
			message text DEFAULT NULL,
			status enum('active','redeemed','expired','cancelled') DEFAULT 'active',
			redeemed_at datetime DEFAULT NULL,
			redeemed_by_booking_id bigint(20) UNSIGNED DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY idx_code (code),
			KEY idx_gf_entry (gf_entry_id),
			KEY idx_status (status),
			KEY idx_purchaser_email (purchaser_email),
			KEY idx_recipient_email (recipient_email)
		) {$charset_collate};";

		dbDelta( $sql_gift_certs );

		// Table 3: Date Overrides.
		$table_overrides = $prefix . 'wtg_date_overrides';
		$sql_overrides   = "CREATE TABLE {$table_overrides} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tour_date date NOT NULL,
			time_slot enum('fri_am','fri_pm','sat_am','sat_pm') NOT NULL,
			is_full tinyint(1) DEFAULT 0,
			reason text DEFAULT NULL,
			created_by bigint(20) UNSIGNED DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY idx_date_slot (tour_date, time_slot),
			KEY idx_tour_date (tour_date)
		) {$charset_collate};";

		dbDelta( $sql_overrides );

		// Store database version for future migrations.
		update_option( 'wtg_db_version', '1.0.0' );
	}

	/**
	 * Set default plugin options.
	 */
	private static function set_default_options() {
		// Square settings (will be set via admin in Week 5).
		if ( ! get_option( 'wtg_square_environment' ) ) {
			add_option( 'wtg_square_environment', 'production' );
		}

		// Invoice timing settings (adjustable 1-5 days, default 2 days/48 hours).
		if ( ! get_option( 'wtg_invoice_hours_before' ) ) {
			add_option( 'wtg_invoice_hours_before', 48 );
		}

		// Capacity and threshold settings.
		if ( ! get_option( 'wtg_seat_capacity' ) ) {
			add_option( 'wtg_seat_capacity', 14 );
		}

		if ( ! get_option( 'wtg_unlock_threshold' ) ) {
			add_option( 'wtg_unlock_threshold', 10 );
		}
	}

	/**
	 * Schedule WP Cron events.
	 */
	private static function schedule_cron() {
		// Schedule hourly cron for invoice automation (Week 6).
		if ( ! wp_next_scheduled( 'wtg_send_pending_invoices' ) ) {
			wp_schedule_event( time(), 'hourly', 'wtg_send_pending_invoices' );
		}
	}

	/**
	 * Migrate existing bookings to add booking codes.
	 */
	private static function migrate_booking_codes() {
		global $wpdb;
		$table = $wpdb->prefix . 'wtg_bookings';

		// Get all bookings without a booking code.
		$bookings = $wpdb->get_results(
			"SELECT id, created_at FROM {$table} WHERE booking_code IS NULL OR booking_code = '' ORDER BY id ASC"
		);

		if ( empty( $bookings ) ) {
			return;
		}

		// Generate and update booking codes.
		foreach ( $bookings as $booking ) {
			$booking_code = self::generate_booking_code( $booking->id, $booking->created_at );

			$wpdb->update(
				$table,
				array( 'booking_code' => $booking_code ),
				array( 'id' => $booking->id ),
				array( '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Generate a unique booking code.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $created_at Created timestamp.
	 * @return string Booking code.
	 */
	private static function generate_booking_code( $booking_id, $created_at ) {
		$year = date( 'Y', strtotime( $created_at ) );
		return sprintf( 'WTG-%s-%04d', $year, $booking_id );
	}

	/**
	 * Add missing Square integration columns.
	 */
	private static function migrate_square_columns() {
		global $wpdb;
		$table = $wpdb->prefix . 'wtg_bookings';

		// Check if total_amount column exists.
		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'total_amount'",
				DB_NAME,
				$table
			)
		);

		if ( empty( $column_exists ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN total_amount decimal(10,2) DEFAULT NULL AFTER tickets" );
		}

		// Check if deposit_square_id column exists.
		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'deposit_square_id'",
				DB_NAME,
				$table
			)
		);

		if ( empty( $column_exists ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN deposit_square_id varchar(100) DEFAULT NULL AFTER discount_applied" );
		}

		// Check if balance_square_id column exists.
		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'balance_square_id'",
				DB_NAME,
				$table
			)
		);

		if ( empty( $column_exists ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN balance_square_id varchar(100) DEFAULT NULL AFTER deposit_square_id" );
		}
	}
}
