<?php


declare(strict_types=1);

namespace Starisian\Sparxstar\Photon;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SparxstarPhotonVCard {

	/**
	 * Singleton instance.
	 */
	private static ?self $instance = null;

	/**
	 * Private constructor — registers hooks.
	 */
	private function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Return (or create) the singleton instance.
	 */
	public static function get_instance(): self {
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

		// 5. ENQUEUE QR LIBRARY (local asset, no CDN dependency)
		$qr_path = SPARXSTAR_PHOTON_VCARD_PLUGIN_PATH . 'assets/js/qrcode.min.js';
		if ( file_exists( $qr_path ) ) {
			wp_enqueue_script(
				'spax-photon-qrcode',
				SPARXSTAR_PHOTON_VCARD_PLUGIN_URL . 'assets/js/qrcode.min.js',
				[],
				SPARXSTAR_PHOTON_VCARD_VERSION,
				true
			);
		}

		// 6. ENQUEUE STYLESHEET
		wp_enqueue_style(
			'spax-photon-vcard',
			SPARXSTAR_PHOTON_VCARD_PLUGIN_URL . 'assets/css/' . $css_file,
			[],
			SPARXSTAR_PHOTON_VCARD_VERSION
		);

		// 7. ENQUEUE MAIN SCRIPT (QR library must load first)
		wp_enqueue_script(
			'spax-photon-vcard',
			SPARXSTAR_PHOTON_VCARD_PLUGIN_URL . 'assets/js/' . $js_file,
			[ 'spax-photon-qrcode' ],
			SPARXSTAR_PHOTON_VCARD_VERSION,
			true
		);

		// 8. ENTERPRISE CONFIGURATION (Filter Hook)
		$disable_sensors = apply_filters( 'vip_motion_disable_sensors', false, $user_id, $post_obj->ID );

		// 9. PASS CARD DATA (replaces inline JSON — no XSS risk)
		wp_localize_script(
			'spax-photon-vcard',
			'SPAX_PHOTON_VCARD_DATA',
			[
				'name'     => get_user_meta( $user_id, 'scf_full_name', true ) ?: $user->display_name,
				'title'    => get_user_meta( $user_id, 'scf_job_title', true ) ?: 'Business Associate',
				'phone'    => get_user_meta( $user_id, 'scf_phone_number', true ) ?: '',
				'email'    => $user->user_email,
				'logo'     => get_user_meta( $user_id, 'scf_business_logo_url', true ) ?: '',
				'noSensor' => (bool) $disable_sensors,
			]
		);
	}
}

SparxstarPhotonVCard::get_instance();
