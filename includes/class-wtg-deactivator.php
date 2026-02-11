<?php
/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @package WTG2
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Plugin deactivation class.
 */
class WTG_Deactivator {

	/**
	 * Deactivate the plugin.
	 *
	 * Clears scheduled cron events. Does NOT drop database tables to preserve data.
	 */
	public static function deactivate() {
		self::clear_scheduled_events();
	}

	/**
	 * Clear all scheduled WP Cron events.
	 */
	private static function clear_scheduled_events() {
		// Clear invoice automation cron.
		$timestamp = wp_next_scheduled( 'wtg_send_pending_invoices' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'wtg_send_pending_invoices' );
		}
	}
}
