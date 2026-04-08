<?php

declare(strict_types=1);

namespace Starisian\Sparxstar\Photon;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Asset loader / front-end orchestrator.
 *
 * Enqueues the plugin stylesheet, main script, and QR library, then passes
 * per-user card data to JavaScript via wp_localize_script.
 *
 * Data priority order for each field:
 *  – ACF custom fields (spx_*)          — always checked when ACF is active
 *  – WooCommerce billing meta           — used for address, company, email
 *  – WordPress core user fields         — user_url, user_email, display_name
 *  – Gravatar                           — photo via get_avatar_url()
 *
 * Instantiated as a singleton by {@see Bootloader::init()}.  External code
 * (e.g. the [spx_photon_vcard] shortcode) calls the public static helper
 * {@see AssetLoader::enqueue_for_user()} directly.
 *
 * @package Starisian\Sparxstar\Photon
 * @since   1.0.0
 * @version 1.1.0
 */
final class AssetLoader {

	/**
	 * Singleton instance.
	 *
	 * @var AssetLoader|null
	 */
	private static ?AssetLoader $instance = null;

	/**
	 * Tracks whether wp_localize_script has already been called so that
	 * shortcode + automatic enqueue paths don't overwrite each other.
	 *
	 * @var bool
	 */
	private static bool $localized = false;

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
	 * Automatic enqueue on singular pages — driven by the post author.
	 *
	 * Skipped when the page contains the [spx_photon_vcard] shortcode,
	 * because the shortcode callback calls enqueue_for_user() directly.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post_obj = get_queried_object();

		if ( ! $post_obj instanceof \WP_Post ) {
			return;
		}

		// Shortcode path takes priority — it handles its own enqueue.
		if ( has_shortcode( $post_obj->post_content, 'spx_photon_vcard' ) ) {
			return;
		}

		$author_id = (int) $post_obj->post_author;
		if ( $author_id <= 0 ) {
			return;
		}

		self::enqueue_for_user( $author_id, $post_obj->ID );
	}

	/**
	 * Enqueue all card assets for the given user.
	 *
	 * Idempotent: subsequent calls within the same request are no-ops.
	 * Safe to call from shortcode callbacks after wp_enqueue_scripts has fired,
	 * as WordPress defers script/style output to wp_footer/wp_head.
	 *
	 * @param  int $user_id   Author / card owner user ID.
	 * @param  int $post_id   Associated post ID (used for filter hooks). 0 for shortcode context.
	 * @return void
	 */
	public static function enqueue_for_user( int $user_id, int $post_id = 0 ): void {
		if ( self::$localized ) {
			return;
		}

		if ( $user_id <= 0 ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return;
		}

		// Permission check — only allowed roles receive a card.
		$allowed_roles = apply_filters(
			'sparxstar_photon_vcard_allowed_roles',
			[ 'administrator', 'vip_business_user', 'editor' ],
			$user_id
		);

		if ( empty( array_intersect( $allowed_roles, (array) $user->roles ) ) ) {
			return;
		}

		// Honour the spx_display_business_card ACF toggle.
		if ( function_exists( 'get_field' ) ) {
			$display = get_field( 'spx_display_business_card', 'user_' . $user_id );
			// Explicit false means "hide the card"; null / unset means default on.
			if ( $display === false ) {
				return;
			}
		}

		$debug    = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
		$css_file = $debug ? 'sparxstar-photon-vcard.css' : 'sparxstar-photon-vcard.min.css';
		$js_file  = $debug ? 'sparxstar-photon-vcard.js'  : 'sparxstar-photon-vcard.min.js';

		// QR library (local asset — no CDN dependency).
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

		wp_enqueue_style(
			'spx-photon-vcard',
			SPARXSTAR_PHOTON_VCARD_PLUGIN_URL . 'assets/css/' . $css_file,
			[],
			SPARXSTAR_PHOTON_VCARD_VERSION
		);

		wp_enqueue_script(
			'spx-photon-vcard',
			SPARXSTAR_PHOTON_VCARD_PLUGIN_URL . 'assets/js/' . $js_file,
			$script_deps,
			SPARXSTAR_PHOTON_VCARD_VERSION,
			true
		);

		// Enterprise sensor override filter.
		$disable_sensors = apply_filters( 'sparxstar_photon_vcard_disable_sensors', false, $user_id, $post_id );
		// Backwards-compat alias.
		$disable_sensors = apply_filters( 'vip_motion_disable_sensors', $disable_sensors, $user_id, $post_id );

		wp_localize_script(
			'spx-photon-vcard',
			'SPX_PHOTON_VCARD',
			self::build_card_data( $user, $disable_sensors )
		);

		self::$localized = true;
	}

	/**
	 * Assemble the card data array passed to JavaScript.
	 *
	 * Data is sourced (in priority order) from ACF custom fields, WooCommerce
	 * billing meta, and core WordPress user fields.
	 *
	 * @param  \WP_User $user            The card owner.
	 * @param  bool     $disable_sensors Whether motion triggers are disabled.
	 * @return array<string,mixed>       Sanitized card data for wp_localize_script.
	 */
	private static function build_card_data( \WP_User $user, bool $disable_sensors ): array {
		$uid = (int) $user->ID;

		// ── Name ────────────────────────────────────────────────────────────────
		$name = '';
		if ( class_exists( 'WooCommerce' ) ) {
			$first = (string) get_user_meta( $uid, 'billing_first_name', true );
			$last  = (string) get_user_meta( $uid, 'billing_last_name', true );
			$name  = trim( $first . ' ' . $last );
		}
		if ( '' === $name ) {
			$name = $user->display_name;
		}

		// ── Job title (ACF only — no WooCommerce equivalent) ────────────────────
		$title = '';
		if ( function_exists( 'get_field' ) ) {
			$title = (string) ( get_field( 'spx_title', 'user_' . $uid ) ?: '' );
		}

		// ── Company ─────────────────────────────────────────────────────────────
		$company = '';
		if ( class_exists( 'WooCommerce' ) ) {
			$company = (string) ( get_user_meta( $uid, 'billing_company', true ) ?: '' );
		}
		if ( '' === $company && function_exists( 'get_field' ) ) {
			$company = (string) ( get_field( 'spx_company', 'user_' . $uid ) ?: '' );
		}

		// ── Phone numbers ────────────────────────────────────────────────────────
		$phones = [];
		if ( function_exists( 'get_field' ) ) {
			$mobile = (string) ( get_field( 'spx_mobile', 'user_' . $uid ) ?: '' );
			if ( '' !== $mobile ) {
				$phones[] = [ 'type' => 'CELL', 'number' => $mobile ];
			}

			$work_phone = (string) ( get_field( 'spx_work_phone', 'user_' . $uid ) ?: '' );
			if ( '' !== $work_phone ) {
				$phones[] = [ 'type' => 'WORK', 'number' => $work_phone ];
			}

			$fax = (string) ( get_field( 'spx_fax', 'user_' . $uid ) ?: '' );
			if ( '' !== $fax ) {
				$phones[] = [ 'type' => 'FAX', 'number' => $fax ];
			}
		}

		// Fallback: WooCommerce billing phone when no ACF phones configured.
		if ( empty( $phones ) && class_exists( 'WooCommerce' ) ) {
			$billing_phone = (string) ( get_user_meta( $uid, 'billing_phone', true ) ?: '' );
			if ( '' !== $billing_phone ) {
				$phones[] = [ 'type' => 'CELL', 'number' => $billing_phone ];
			}
		}

		// ── WhatsApp ─────────────────────────────────────────────────────────────
		$whatsapp = '';
		if ( function_exists( 'get_field' ) ) {
			$whatsapp = (string) ( get_field( 'spx_whatsapp_phone', 'user_' . $uid ) ?: '' );
		}

		// ── Email ────────────────────────────────────────────────────────────────
		$email = $user->user_email;
		if ( class_exists( 'WooCommerce' ) ) {
			$billing_email = (string) ( get_user_meta( $uid, 'billing_email', true ) ?: '' );
			if ( '' !== $billing_email ) {
				$email = $billing_email;
			}
		}

		// ── Website ──────────────────────────────────────────────────────────────
		$website = $user->user_url ?: '';
		if ( '' === $website && function_exists( 'get_field' ) ) {
			$website = (string) ( get_field( 'spx_website', 'user_' . $uid ) ?: '' );
		}

		// ── Postal address ───────────────────────────────────────────────────────
		$address = [];
		if ( class_exists( 'WooCommerce' ) ) {
			$address = [
				'street1'  => (string) ( get_user_meta( $uid, 'billing_address_1', true ) ?: '' ),
				'street2'  => (string) ( get_user_meta( $uid, 'billing_address_2', true ) ?: '' ),
				'city'     => (string) ( get_user_meta( $uid, 'billing_city', true ) ?: '' ),
				'state'    => (string) ( get_user_meta( $uid, 'billing_state', true ) ?: '' ),
				'postcode' => (string) ( get_user_meta( $uid, 'billing_postcode', true ) ?: '' ),
				'country'  => (string) ( get_user_meta( $uid, 'billing_country', true ) ?: '' ),
			];
		} elseif ( function_exists( 'get_field' ) ) {
			$address = [
				'street1'  => (string) ( get_field( 'spx_address_1', 'user_' . $uid ) ?: '' ),
				'street2'  => (string) ( get_field( 'spx_address_2', 'user_' . $uid ) ?: '' ),
				'city'     => (string) ( get_field( 'spx_city', 'user_' . $uid ) ?: '' ),
				'state'    => (string) ( get_field( 'spx_state', 'user_' . $uid ) ?: '' ),
				'postcode' => (string) ( get_field( 'spx_postcode', 'user_' . $uid ) ?: '' ),
				'country'  => (string) ( get_field( 'spx_country', 'user_' . $uid ) ?: '' ),
			];
		}

		// ── Photo (Gravatar) ─────────────────────────────────────────────────────
		$photo = (string) get_avatar_url( $uid, [ 'size' => 200, 'default' => '404' ] );
		// If Gravatar returns the 404 placeholder, treat as absent.
		if ( str_contains( $photo, 'd=404' ) || str_contains( $photo, 'd%3D404' ) ) {
			$photo = '';
		}

		// ── Business logo (legacy SCF / custom meta) ─────────────────────────────
		$logo = (string) ( get_user_meta( $uid, 'scf_business_logo_url', true ) ?: '' );

		return [
			'name'      => sanitize_text_field( $name ),
			'company'   => sanitize_text_field( $company ),
			'title'     => sanitize_text_field( $title ),
			'phones'    => array_map(
				static fn( array $p ): array => [
					'type'   => sanitize_key( $p['type'] ),
					'number' => sanitize_text_field( $p['number'] ),
				],
				$phones
			),
			'whatsapp'  => sanitize_text_field( $whatsapp ),
			'email'     => sanitize_email( $email ),
			'website'   => esc_url_raw( $website ),
			'photo'     => esc_url_raw( $photo ),
			'logo'      => esc_url_raw( $logo ),
			'address'   => array_map( 'sanitize_text_field', $address ),
			'noSensor'  => (bool) $disable_sensors,
		];
	}
}
