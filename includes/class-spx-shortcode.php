<?php

declare(strict_types=1);

namespace Starisian\Sparxstar\Photon;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode: [spx_photon_vcard]
 *
 * Renders a button (or inline link) that opens the Photon digital business
 * card for the relevant user.  Placing this shortcode in any post, page, or
 * widget removes the need for the default floating "View Business Card"
 * button that is otherwise auto-injected.
 *
 * Usage:
 *   [spx_photon_vcard]
 *   [spx_photon_vcard text="Show My Card"]
 *   [spx_photon_vcard text="Contact Me" class="my-btn" id="hero-card-btn"]
 *   [spx_photon_vcard user_id="42" text="Meet the Author"]
 *
 * Attributes:
 *   text     (string)  Button label.  Default: "View My Card".
 *   class    (string)  Extra CSS classes to append.
 *   id       (string)  Optional HTML id for the button element.
 *   user_id  (int)     Explicit user whose card to show.  Defaults to the
 *                      current post author, then the logged-in user.
 *
 * @package Starisian\Sparxstar\Photon
 * @since   1.1.0
 */
final class Shortcode {

	/**
	 * Register the [spx_photon_vcard] shortcode.
	 *
	 * Called once from {@see Bootloader::init()}.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_shortcode( 'spx_photon_vcard', [ self::class, 'render' ] );
	}

	/**
	 * Shortcode callback.
	 *
	 * Ensures card assets are enqueued for the target user and outputs a
	 * trigger button with the `data-spx-vcard-trigger` attribute that the
	 * front-end JS binds to.
	 *
	 * @param  array<string,string>|string $atts Raw shortcode attributes.
	 * @return string                            HTML button markup, or empty string on failure.
	 */
	public static function render( array|string $atts ): string {
		$atts = shortcode_atts(
			[
				'text'    => __( 'View My Card', 'sparxstar-photon-vcard' ),
				'class'   => '',
				'id'      => '',
				'user_id' => '0',
			],
			$atts,
			'spx_photon_vcard'
		);

		$user_id = (int) $atts['user_id'];
		$post_id = 0;

		$post = get_post();
		if ( $post instanceof \WP_Post ) {
			$post_id = (int) $post->ID;
		}

		// Resolve user: explicit → post author → logged-in user.
		if ( $user_id <= 0 && $post instanceof \WP_Post && $post->post_author ) {
			$user_id = (int) $post->post_author;
		}

		if ( $user_id <= 0 ) {
			$user_id = get_current_user_id();
		}

		if ( $user_id <= 0 ) {
			return '';
		}

		// Enqueue card assets for this user; bail silently when not permitted.
		// Pass the resolved post ID so filter hooks (e.g. sparxstar_photon_vcard_disable_sensors)
		// receive full context even when the shortcode is the only enqueue path.
		if ( ! AssetLoader::enqueue_for_user( $user_id, $post_id ) ) {
			return '';
		}

		$id_attr    = $atts['id'] ? ' id="' . esc_attr( $atts['id'] ) . '"' : '';
		$class_attr = 'spax-photon-trigger-btn';
		if ( ! empty( $atts['class'] ) ) {
			$class_attr .= ' ' . esc_attr( $atts['class'] );
		}

		return sprintf(
			'<button type="button"%s class="%s" data-spx-vcard-trigger="1" data-spx-vcard-uid="%d">%s</button>',
			$id_attr,
			esc_attr( $class_attr ),
			$user_id,
			esc_html( $atts['text'] )
		);
	}
}
