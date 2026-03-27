<?php

declare(strict_types=1);

namespace Starisian\Sparxstar\Photon;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Asset loader / front-end orchestrator.
 *
 * Enqueues the plugin stylesheet, main script, and QR library, and passes
 * per-user card data to JavaScript via wp_localize_script.
 *
 * Instantiated as a singleton by {@see Bootloader::init()} — do not call
 * get_instance() directly in production code.
 *
 * @package Starisian\Sparxstar\Photon
 * @since   1.0.0
 * @version 1.0.0
 */
final class AssetLoader {

	/**
	 * Singleton instance.
	 *
	 * @var AssetLoader|null
	 */
	private static ?AssetLoader $instance = null;

	/**
	 * Private constructor — registers the wp_enqueue_scripts hook.
	 */
	private function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Return (or create) the singleton instance.
	 *
	 * @return AssetLoader
	 */
	public static function get_instance(): AssetLoader {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Enqueue plugin CSS, JS, and pass card data via wp_localize_script.
	 *
	 * All auth / permission checks are performed here so nothing is enqueued
	 * for visitors who are not entitled to the card.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		// 1. FAIL FAST
		if ( ! is_user_logged_in() || ! is_singular() ) {
			return;
		}

		$user_id  = get_current_user_id();
		$post_obj = get_queried_object();

		// 2. AUTHORSHIP GUARD
		if ( ! $post_obj instanceof \WP_Post || (int) $post_obj->post_author !== $user_id ) {
			return;
		}

		$user = get_userdata( $user_id );

		// 3. TYPE SAFETY
		if ( ! $user instanceof \WP_User ) {
			return;
		}

		// 4. PERMISSION CHECK
		$allowed_roles = [ 'administrator', 'vip_business_user', 'editor' ];
		if ( empty( array_intersect( $allowed_roles, (array) $user->roles ) ) ) {
			return;
		}

		$debug    = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
		$css_file = $debug ? 'sparxstar-photon-vcard.css' : 'sparxstar-photon-vcard.min.css';
		$js_file  = $debug ? 'sparxstar-photon-vcard.js'  : 'sparxstar-photon-vcard.min.js';

		// 5. REGISTER & ENQUEUE QR LIBRARY (local asset, no CDN dependency).
		//    Build the dependency list dynamically so the main script is never
		//    silently dropped by WordPress when the QR file is missing.
		$script_deps = [];
		$qr_path     = SPARXSTAR_PHOTON_VCARD_PLUGIN_PATH . 'assets/js/qrcode.min.js';
		if ( file_exists( $qr_path ) ) {
			wp_register_script(
				'spx-photon-qrcode',
				SPARXSTAR_PHOTON_VCARD_PLUGIN_URL . 'assets/js/qrcode.min.js',
				[],
				SPARXSTAR_PHOTON_VCARD_VERSION,
				true
			);
			wp_enqueue_script( 'spx-photon-qrcode' );
			$script_deps[] = 'spx-photon-qrcode';
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			trigger_error(
				'SPARXSTAR Photon VCard: qrcode.min.js not found in assets/js/. The QR code feature will be unavailable.',
				E_USER_WARNING
			);
		}

		// 6. ENQUEUE STYLESHEET
		wp_enqueue_style(
			'spx-photon-vcard',
			SPARXSTAR_PHOTON_VCARD_PLUGIN_URL . 'assets/css/' . $css_file,
			[],
			SPARXSTAR_PHOTON_VCARD_VERSION
		);

		// 7. ENQUEUE MAIN SCRIPT
		wp_enqueue_script(
			'spx-photon-vcard',
			SPARXSTAR_PHOTON_VCARD_PLUGIN_URL . 'assets/js/' . $js_file,
			$script_deps,
			SPARXSTAR_PHOTON_VCARD_VERSION,
			true
		);

		// 8. ENTERPRISE CONFIGURATION (Filter Hook)
		$disable_sensors = apply_filters(
			'sparxstar_photon_vcard_disable_sensors',
			false,
			$user_id,
			$post_obj->ID
		);

		// Backwards compatibility: legacy filter name (deprecated alias).
		$disable_sensors = apply_filters(
			'vip_motion_disable_sensors',
			$disable_sensors,
			$user_id,
			$post_obj->ID
		);

		// 9. PASS CARD DATA (replaces inline JSON — no XSS risk).
		//    The JS global is SPX_PHOTON_VCARD (spx_ prefix per WP VIP standards).
		wp_localize_script(
			'spx-photon-vcard',
			'SPX_PHOTON_VCARD',
			[
				'name'     => get_user_meta( $user_id, 'scf_full_name', true ) ?: $user->display_name,
				'title'    => get_user_meta( $user_id, 'scf_job_title', true ) ?: __( 'Business Associate', 'sparxstar-photon-vcard' ),
				'phone'    => get_user_meta( $user_id, 'scf_phone_number', true ) ?: '',
				'email'    => $user->user_email,
				'logo'     => get_user_meta( $user_id, 'scf_business_logo_url', true ) ?: '',
				'noSensor' => (bool) $disable_sensors,
			]
		);
	}
}
