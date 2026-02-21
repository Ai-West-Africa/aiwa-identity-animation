<?php
/**
 * SPARXSTAR Photon VCard
 * @version           0.5.0
 * @package           sparxstar-photon-vcard
 * @author            Starisian Technologies (Max Barrett) <support@starisian.com>
 * @copyright         2025 Starisian Technologies. All rights reserved.
 * @license           Starisian Technologies Proprietary
 *
 * @wordpress-plugin
 * Plugin Name:       SPARXSTAR Photon VCard
 * Plugin URI:        https://starisian.com/sparxstar/sparxstar-photon-vcard
 * Description:       roduction-grade digital business card with motion/touch triggers. Finalized for accessibility, security, and legacy hardware resilience.
 * Version:           0.5.0
 * Requires at least: 6.8
 * Requires PHP:      8.2
 * Author:            Starisian Technologies (Max Barrett) <support@starisian.com>
 * Author URI:        https://starisian.com
 * Text Domain:       sparxstar-photon-vcard
 * License:           Starisian Technologies Proprietary
 * License URI: 	  https://github.com/Starisian-Technologies/sparxstar-photon-vcard/blob/main/LICENSE.md
 * Update URI:        https://starisian.com/sparxstar/sparxstar-photon-vcard/update
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Current plugin version.
 */
define( 'SPARXSTAR_PHOTON_VCARD_VERSION', '1.0.0' );

/**
 * Minimum PHP version required.
 */
define( 'SPARXSTAR_PHOTON_VCARD_MIN_PHP_VERSION', '8.2' );

/**
 * Minimum WordPress version required.
 */
define( 'SPARXSTAR_PHOTON_VCARD_MIN_WP_VERSION', '6.8' );

/**
 * Plugin base path.
 */
define( 'SPARXSTAR_PHOTON_VCARD_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Plugin base URL.
 */
define( 'SPARXSTAR_PHOTON_VCARD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check if the system meets the minimum requirements.
 *
 * @return bool True if requirements are met, false otherwise.
 */
function sparxstar_photon_vcard_check_requirements() {
	$errors = array();

	// Check PHP version.
	if ( version_compare( PHP_VERSION, SPARXSTAR_PHOTON_VCARD_MIN_PHP_VERSION, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Current PHP version, 2: Required PHP version */
			__( 'SPARXSTAR Photon VCard requires PHP version %2$s or higher. You are running version %1$s.', 'sparxstar-photon-vcard' ),
			PHP_VERSION,
			SPARXSTAR_PHOTON_VCARD_MIN_PHP_VERSION
		);
	}

	// Check WordPress version.
	global $wp_version;
	if ( version_compare( $wp_version, SPARXSTAR_PHOTON_VCARD_MIN_WP_VERSION, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Current WordPress version, 2: Required WordPress version */
			__( 'SPARXSTAR Photon VCard requires WordPress version %2$s or higher. You are running version %1$s.', 'sparxstar-photon-vcard' ),
			$wp_version,
			SPARXSTAR_PHOTON_VCARD_MIN_WP_VERSION
		);
	}

	// Display errors if any.
	if ( ! empty( $errors ) ) {
		// Ensure admin notice hooks are only registered once per request.
		static $requirements_notices_added = false;

		if ( ! $requirements_notices_added ) {
			foreach ( $errors as $error ) {
				add_action(
					'admin_notices',
					function () use ( $error ) {
						?>
						<div class="notice notice-error">
							<p><?php echo esc_html( $error ); ?></p>
						</div>
						<?php
					}
				);
			}

			$requirements_notices_added = true;
		}
		return false;
	}

	return true;
}

/**
 * Activation hook callback.
 * Runs when the plugin is activated.
 *
 * @return void
 */
function sparxstar_photon_vcard_activate() {
	// Check requirements before activation.
	if ( ! sparxstar_photon_vcard_check_requirements() ) {
		// Deactivate the plugin if requirements are not met.
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			sprintf(
				/* translators: 1: Required PHP version, 2: Required WordPress version */
				esc_html__( 'SPARXSTAR Photon VCard could not be activated. Please ensure you are running PHP %1$s or higher and WordPress %2$s or higher.', 'sparxstar-photon-vcard' ),
				SPARXSTAR_PHOTON_VCARD_MIN_PHP_VERSION,
				SPARXSTAR_PHOTON_VCARD_MIN_WP_VERSION
			),
			esc_html__( 'Plugin Activation Error', 'sparxstar-photon-vcard' ),
			array( 'back_link' => true )
		);
	}

	// Set default options.
	$default_options = array(
		'version'    => SPARXSTAR_PHOTON_VCARD_VERSION,
		'activated'  => time(),
		'configured' => false,
	);
	add_option( 'sparxstar_photon_vcard_options', $default_options );

	// Create necessary database tables if needed (placeholder for future use).
	// sparxstar_photon_vcard_create_tables();

	// Set a transient to trigger a welcome notice.
	set_transient( 'sparxstar_photon_vcard_activation_notice', true, 60 );

	// Flush rewrite rules.
	flush_rewrite_rules();
}

/**
 * Deactivation hook callback.
 * Runs when the plugin is deactivated.
 *
 * @return void
 */
function sparxstar_photon_vcard_deactivate() {
	// Clean up transients.
	delete_transient( 'sparxstar_photon_vcard_activation_notice' );

	// Flush rewrite rules.
	flush_rewrite_rules();

	// Note: We don't remove options or data on deactivation.
	// Data cleanup only happens during uninstall.
}

/**
 * Initialize the plugin.
 * Only runs if requirements are met.
 *
 * @return void
 */
function sparxstar_photon_vcard_init() {
	// Check requirements.
	if ( ! sparxstar_photon_vcard_check_requirements() ) {
		return;
	}

	// Load plugin text domain for translations.
	load_plugin_textdomain(
		'sparxstar-photon-vcard',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);

	// Display activation notice if transient is set.
	if ( get_transient( 'sparxstar_photon_vcard_activation_notice' ) ) {
		add_action(
			'admin_notices',
			function () {
				?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'SPARXSTAR Photon VCard has been activated successfully!', 'sparxstar-photon-vcard' ); ?></p>
				</div>
				<?php
			}
		);
	}

	// Load the main card class.
	require_once SPARXSTAR_PHOTON_VCARD_PLUGIN_PATH . 'src/SparxstarPhotonVCard.php';
}

// Register activation hook.
register_activation_hook( __FILE__, 'sparxstar_photon_vcard_activate' );

// Register deactivation hook.
register_deactivation_hook( __FILE__, 'sparxstar_photon_vcard_deactivate' );

// Initialize the plugin.
add_action( 'plugins_loaded', 'sparxstar_photon_vcard_init' );
