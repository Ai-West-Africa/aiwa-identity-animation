<?php
/**
 * Plugin uninstaller.
 *
 * Removes all data created by the AiWA Identity Animation plugin.
 * This file is loaded directly by WordPress during plugin deletion.
 *
 * @package AiWA\IdentityAnimation
 * @since   1.0.0
 */

declare(strict_types=1);

namespace AiWA\IdentityAnimation;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Handles complete data cleanup when the plugin is uninstalled.
 *
 * All methods are static — no instance is ever created.
 *
 * @package AiWA\IdentityAnimation
 * @since   1.0.0
 */
final class Uninstaller {

	/**
	 * Execute all cleanup routines.
	 *
	 * @return void
	 */
	public static function run(): void {
		delete_option( 'aiwa_identity_animation_version' );
		wp_cache_flush();
	}
}
