<?php

declare(strict_types=1);

namespace Starisian\Sparxstar\Photon;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ACF image field utility.
 *
 * Provides a single authoritative resolver for ACF image field values used
 * throughout this plugin.  Centralizing this logic prevents the two consumers
 * — AssetLoader and PwaController — from drifting as field return formats,
 * sanitization rules, or future resizing requirements evolve.
 *
 * ACF may return an image field in two formats depending on the field's
 * `return_format` setting:
 *   – 'array'  → associative array containing url, mime_type, width, height
 *   – 'url'    → plain URL string
 *
 * @package Starisian\Sparxstar\Photon
 * @since   1.1.0
 */
final class AcfHelper {

	/**
	 * Resolve an ACF image field value to a normalised metadata array.
	 *
	 * @param  mixed $blob  Raw value returned by get_field() for an image field.
	 * @return array{url: string, mime: string, width: int, height: int}
	 *               Normalised image data. All values are sanitized.
	 *               'url' is an empty string when no image is available.
	 */
	public static function resolve_image( mixed $blob ): array {
		if ( is_array( $blob ) && ! empty( $blob['url'] ) ) {
			return [
				'url'    => esc_url_raw( (string) $blob['url'] ),
				'mime'   => sanitize_mime_type( (string) ( $blob['mime_type'] ?? 'image/jpeg' ) ),
				'width'  => absint( $blob['width'] ?? 0 ),
				'height' => absint( $blob['height'] ?? 0 ),
			];
		}

		if ( is_string( $blob ) && '' !== $blob ) {
			$filetype = wp_check_filetype( $blob );
			return [
				'url'    => esc_url_raw( $blob ),
				'mime'   => ! empty( $filetype['type'] )
					? sanitize_mime_type( $filetype['type'] )
					: 'image/jpeg',
				'width'  => 0,
				'height' => 0,
			];
		}

		return [ 'url' => '', 'mime' => 'image/jpeg', 'width' => 0, 'height' => 0 ];
	}
}
