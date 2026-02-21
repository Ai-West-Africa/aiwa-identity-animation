<?php

declare(strict_types=1);

namespace Starian\Sparxstar\Photon;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SparxstarPhotonVCard {

	/**
	 * Initialize hooks.
	 */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'maybe_enqueue_assets' ], 99 );
	}

	/**
	 * Conditionally enqueue assets for the card owner on singular posts/pages.
	 */
	public static function maybe_enqueue_assets(): void {
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

		// 5. ENTERPRISE CONFIGURATION (Filter Hook)
		$disable_sensors = apply_filters( 'spax_photon_disable_sensors', false, $user_id, $post_obj->ID );

		// 6. DATA PREPARATION
		$card_data = [
			'name'     => get_user_meta( $user_id, 'scf_full_name', true ) ?: $user->display_name,
			'title'    => get_user_meta( $user_id, 'scf_job_title', true ) ?: 'Business Associate',
			'phone'    => get_user_meta( $user_id, 'scf_phone_number', true ) ?: '',
			'email'    => $user->user_email,
			'logo'     => get_user_meta( $user_id, 'scf_business_logo_url', true ) ?: '',
			'noSensor' => (bool) $disable_sensors,
		];

		// 7. ENQUEUE ASSETS
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style(
			'spax-photon-vcard',
			SPARXSTAR_PHOTON_VCARD_PLUGIN_URL . 'assets/css/sparxstar-photon-vcard' . $suffix . '.css',
			[],
			SPARXSTAR_PHOTON_VCARD_VERSION
		);

		wp_enqueue_script(
			'spax-photon-vcard',
			SPARXSTAR_PHOTON_VCARD_PLUGIN_URL . 'assets/js/sparxstar-photon-vcard' . $suffix . '.js',
			[],
			SPARXSTAR_PHOTON_VCARD_VERSION,
			true
		);

		// 8. PASS DATA TO JS (keeps JS "pure" — no PHP inside JS file)
		wp_localize_script(
			'spax-photon-vcard',
			'SPAX_PHOTON_VCARD_DATA',
			$card_data
		);
	}
}

SparxstarPhotonVCard::init();
