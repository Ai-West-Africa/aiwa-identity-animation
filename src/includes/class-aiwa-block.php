<?php
/**
 * Block registration for the AiWA Identity Animation block.
 *
 * Calls register_block_type() pointing at the compiled build/ directory so
 * WordPress picks up block.json and all associated script/style handles
 * automatically.
 *
 * @package AiWA\IdentityAnimation
 * @since   1.0.0
 */

declare(strict_types=1);

namespace AiWA\IdentityAnimation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles Gutenberg block registration.
 *
 * @package AiWA\IdentityAnimation
 * @since   1.0.0
 */
final class Block {

	/**
	 * Register the WordPress init hook.
	 *
	 * Called once from the main plugin file.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', [ self::class, 'register_block' ] );
	}

	/**
	 * Register the block type from the compiled build/ directory.
	 *
	 * WordPress reads build/block.json and enqueues all declared scripts and
	 * styles automatically.  Bails silently when the build directory is absent
	 * (e.g. development checkout before running npm run build).
	 *
	 * @return void
	 */
	public static function register_block(): void {
		$build_dir = AIWA_IDENTITY_ANIMATION_PLUGIN_PATH . 'build';

		if ( ! is_dir( $build_dir ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				trigger_error(
					'AiWA Identity Animation: build/ directory not found. Run `npm run build` to compile block assets.',
					E_USER_WARNING
				);
			}
			return;
		}

		register_block_type( $build_dir );
	}
}
