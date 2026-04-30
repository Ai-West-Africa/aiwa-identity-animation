<?php
/**
 * Plugin uninstaller.
 *
 * @package Starisian\Sparxstar\Photon
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Photon;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Handles complete data cleanup when the plugin is uninstalled.
 *
 * Called exclusively from uninstall.php via {@see Uninstaller::run()}.
 * All methods are static — no instance is ever created.
 *
 * @package Starisian\Sparxstar\Photon
 * @since   1.0.0
 */
final class Uninstaller {

	/**
	 * Execute all cleanup routines in sequence.
	 *
	 * Handles both single-site and multisite uninstalls.
	 *
	 * @return void
	 */
	public static function run(): void {
		// User meta is stored in a global table and should be deleted once.
		self::delete_user_meta();

		if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_sites' ) ) {
			$sites = get_sites(
				array(
					'number' => 0,
				)
			);

			if ( ! empty( $sites ) ) {
				if ( function_exists( 'get_current_blog_id' ) ) {
					$original_blog_id = get_current_blog_id();
				} else {
					$original_blog_id = 0;
				}

				foreach ( $sites as $site ) {
					if ( empty( $site->blog_id ) ) {
						continue;
					}

					// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog -- multisite uninstall iteration is the documented use case.
					switch_to_blog( (int) $site->blog_id );
					self::run_for_site();
				}

				if ( 0 !== $original_blog_id ) {
					// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog -- restoring original blog context after multisite uninstall iteration.
					switch_to_blog( (int) $original_blog_id );
				}
			} else {
				// Fallback: no sites found, perform cleanup for the current site context.
				self::run_for_site();
			}
		} else {
			// Single-site installation.
			self::run_for_site();
		}

		wp_cache_flush();
	}

	/**
	 * Execute per-site cleanup routines.
	 *
	 * @return void
	 */
	private static function run_for_site(): void {
		self::delete_options();
		self::delete_transients();
		self::delete_tables();
		self::delete_uploads();
	}

	/**
	 * Delete plugin options.
	 *
	 * @return void
	 */
	private static function delete_options(): void {
		delete_option( 'sparxstar_photon_vcard_options' );
	}

	/**
	 * Delete plugin transients.
	 *
	 * @return void
	 */
	private static function delete_transients(): void {
		delete_transient( 'sparxstar_photon_vcard_activation_notice' );
	}

	/**
	 * Delete plugin user meta rows from the database.
	 *
	 * @return void
	 */
	private static function delete_user_meta(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup requires a direct DELETE query; caching is inappropriate here.
		$deleted_rows = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
				$wpdb->esc_like( 'sparxstar_photon_vcard_' ) . '%'
			)
		);

		if ( false === $deleted_rows ) {
			wp_trigger_error(
				__METHOD__,
				sprintf(
					'SPARXSTAR Photon VCard uninstall: Failed to delete user meta. DB error: %s',
					$wpdb->last_error
				),
				E_USER_WARNING
			);
		}
	}

	/**
	 * Drop custom database tables (placeholder for future tables).
	 *
	 * @return void
	 */
	private static function delete_tables(): void {
		// No custom tables exist in this version; extend here when they are introduced.
	}

	/**
	 * Remove plugin-managed upload files and directory.
	 *
	 * @return void
	 */
	private static function delete_uploads(): void {
		$upload_dir = wp_upload_dir();

		// Guard: wp_upload_dir() sets 'error' to a non-empty string on failure.
		if ( ! empty( $upload_dir['error'] ) ) {
			wp_trigger_error(
				__METHOD__,
				sprintf(
					'SPARXSTAR Photon VCard uninstall: wp_upload_dir() returned an error, skipping upload cleanup. Error: %s',
					$upload_dir['error']
				),
				E_USER_WARNING
			);
			return;
		}

		$plugin_upload_dir = trailingslashit( $upload_dir['basedir'] ) . 'sparxstar-photon-vcard';

		if ( is_dir( $plugin_upload_dir ) ) {
			self::delete_directory( $plugin_upload_dir );
		}
	}

	/**
	 * Recursively delete a directory and its contents via WP_Filesystem.
	 *
	 * Uses the WordPress Filesystem API so that all file operations go through
	 * the approved abstraction layer rather than direct PHP calls — satisfies
	 * WordPress.WP.AlternativeFunctions coding standards requirements.
	 *
	 * @param  string $dir Absolute path to the directory.
	 * @return bool   True on full success, false if any item could not be removed.
	 */
	private static function delete_directory( string $dir ): bool {
		if ( ! is_dir( $dir ) ) {
			return false;
		}

		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( ! ( $wp_filesystem instanceof \WP_Filesystem_Base ) ) {
			wp_trigger_error(
				__METHOD__,
				'SPARXSTAR Photon VCard uninstall: WP_Filesystem unavailable, skipping upload directory cleanup.',
				E_USER_WARNING
			);
			return false;
		}

		$deleted = $wp_filesystem->rmdir( $dir, true );

		if ( ! $deleted ) {
			wp_trigger_error(
				__METHOD__,
				sprintf( 'SPARXSTAR Photon VCard uninstall: failed to remove directory: %s', $dir ),
				E_USER_WARNING
			);
		}

		return (bool) $deleted;
	}
}
