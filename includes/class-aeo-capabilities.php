<?php
/**
 * Central capability helpers for plugin access control.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AEOCAS_Capabilities {

	const VIEW_REPORTS  = 'edit_posts';
	const MANAGE_PLUGIN = 'manage_options';

	/**
	 * Capability required to view the plugin workspace.
	 *
	 * @return string
	 */
	public static function view_reports_capability() {
		return sanitize_key( apply_filters( 'aeocas_view_reports_capability', self::VIEW_REPORTS ) );
	}

	/**
	 * Capability required to manage the plugin connection and billing actions.
	 *
	 * @return string
	 */
	public static function manage_plugin_capability() {
		return sanitize_key( apply_filters( 'aeocas_manage_plugin_capability', self::MANAGE_PLUGIN ) );
	}

	/**
	 * Check whether the current user can access read-only reporting.
	 *
	 * @return bool
	 */
	public static function can_view_reports() {
		return current_user_can( self::view_reports_capability() );
	}

	/**
	 * Check whether the current user can manage the plugin connection.
	 *
	 * @return bool
	 */
	public static function can_manage_plugin() {
		return current_user_can( self::manage_plugin_capability() );
	}
}
