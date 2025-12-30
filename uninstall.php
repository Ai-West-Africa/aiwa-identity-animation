<?php
/**
 * Uninstall script for SPARXSTAR Photon VCard.
 *
 * This file is executed when the plugin is uninstalled via the WordPress admin.
 * It cleans up all plugin data, options, and database tables.
 *
 * @package SparxstarPhotonVcard
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete plugin options.
 */
function sparxstar_photon_vcard_delete_options() {
	// Delete main plugin options.
	delete_option( 'sparxstar_photon_vcard_options' );

	// Delete any other options that might have been created.
	// Add more delete_option() calls here as needed.
}

/**
 * Delete plugin transients.
 */
function sparxstar_photon_vcard_delete_transients() {
	// Delete transients.
	delete_transient( 'sparxstar_photon_vcard_activation_notice' );

	// Delete any other transients that might have been created.
	// Add more delete_transient() calls here as needed.
}

/**
 * Delete plugin user meta.
 */
function sparxstar_photon_vcard_delete_user_meta() {
	global $wpdb;

	// Delete user meta keys associated with this plugin.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( 'sparxstar_photon_vcard_' ) . '%'
		)
	);
}

/**
 * Delete plugin database tables.
 */
function sparxstar_photon_vcard_delete_tables() {
	global $wpdb;

	// Example: Drop custom tables if they exist.
	// Uncomment and modify as needed when custom tables are added.
	// $table_name = $wpdb->prefix . 'sparxstar_photon_vcard_data';
	// $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
}

/**
 * Delete plugin uploaded files.
 */
function sparxstar_photon_vcard_delete_uploads() {
	// Get WordPress uploads directory.
	$upload_dir = wp_upload_dir();
	$plugin_upload_dir = $upload_dir['basedir'] . '/sparxstar-photon-vcard';

	// Delete plugin upload directory if it exists.
	if ( is_dir( $plugin_upload_dir ) ) {
		sparxstar_photon_vcard_delete_directory( $plugin_upload_dir );
	}
}

/**
 * Recursively delete a directory and its contents.
 *
 * @param string $dir The directory path to delete.
 * @return bool True on success, false on failure.
 */
function sparxstar_photon_vcard_delete_directory( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return false;
	}

	$items = array_diff( scandir( $dir ), array( '.', '..' ) );

	foreach ( $items as $item ) {
		$path = $dir . DIRECTORY_SEPARATOR . $item;

		if ( is_dir( $path ) ) {
			sparxstar_photon_vcard_delete_directory( $path );
		} else {
			unlink( $path );
		}
	}

	return rmdir( $dir );
}

/**
 * Main uninstall routine.
 * Executes all cleanup functions.
 */
function sparxstar_photon_vcard_uninstall() {
	// Delete options.
	sparxstar_photon_vcard_delete_options();

	// Delete transients.
	sparxstar_photon_vcard_delete_transients();

	// Delete user meta.
	sparxstar_photon_vcard_delete_user_meta();

	// Delete custom database tables.
	sparxstar_photon_vcard_delete_tables();

	// Delete uploaded files.
	sparxstar_photon_vcard_delete_uploads();

	// Clear any cached data.
	wp_cache_flush();
}

// Execute the uninstall routine.
sparxstar_photon_vcard_uninstall();
