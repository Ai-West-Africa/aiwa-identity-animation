<?php

declare(strict_types=1);

namespace Starisian\Sparxstar\Photon;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PWA Controller — Stealth PWA for owner-only home-screen installation.
 *
 * Implements a "Stealth PWA": the Web App Manifest and Service Worker are
 * gated behind WordPress authentication.  Public visitors see a standard,
 * fast website with no install prompt.  When the card owner views their own
 * card while logged in, their device receives the manifest, and the browser
 * offers to add the card to the home screen.  Once installed, the owner opens
 * the card in standalone mode (no browser chrome) directly from their icon.
 *
 * Virtual routes handled via WordPress rewrite rules:
 *   /spx-pwa-manifest.json  — Owner-only Web App Manifest (auth-gated JSON)
 *   /spx-pwa-sw.js          — Dynamic Service Worker script
 *
 * Cookie persistence:
 *   When the installed PWA opens with ?spx_app=1, the auth-cookie expiration
 *   is extended to one year so the owner stays logged in on their device.
 *
 * Multisite / Mercator-aware:
 *   All URLs are derived from home_url() so they resolve correctly against
 *   each site's mapped domain, never the network root.
 *
 * Nginx complement (add to the site's server block):
 * {@code
 * location ~ ^/spx-pwa-(manifest\.json|sw\.js)$ {
 *     try_files $uri $uri/ /index.php?$args;
 *     add_header Service-Worker-Allowed "/";
 *     add_header Cache-Control "no-store, no-cache, must-revalidate, proxy-revalidate, max-age=0";
 *     include fastcgi_params;
 *     fastcgi_param SCRIPT_FILENAME $document_root/index.php;
 *     fastcgi_pass unix:/run/php/php-fpm.sock;
 * }
 * }
 *
 * @package Starisian\Sparxstar\Photon
 * @since   0.5.0
 */
final class PwaController {

	/**
	 * Maximum character length for the PWA short_name field.
	 *
	 * The Web App Manifest spec recommends keeping short_name under 12
	 * characters so it fits beneath the icon on most home-screen launchers.
	 */
	private const PWA_SHORT_NAME_MAX_LENGTH = 12;

	/**
	 * Register all WordPress hooks for the PWA controller.
	 *
	 * Called once from {@see Bootloader::init()}.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', [ self::class, 'register_rewrite_rules' ] );
		add_filter( 'query_vars', [ self::class, 'add_query_vars' ] );
		add_action( 'template_redirect', [ self::class, 'handle_virtual_routes' ], 1 );
		add_action( 'wp_head', [ self::class, 'inject_pwa_head' ] );
		add_filter( 'auth_cookie_expiration', [ self::class, 'extend_cookie_for_pwa' ], 10, 3 );
		add_action( 'profile_update', [ self::class, 'bump_profile_version' ] );
		add_action( 'acf/save_post', [ self::class, 'bump_profile_version_acf' ] );
	}

	/**
	 * Register WordPress rewrite rules for the PWA virtual files.
	 *
	 * Maps the two PWA file paths to WordPress's front controller so that
	 * PHP (and our authentication checks) can handle the response.
	 *
	 * @return void
	 */
	public static function register_rewrite_rules(): void {
		add_rewrite_rule( '^spx-pwa-manifest\.json$', 'index.php?spx_pwa_action=manifest', 'top' );
		add_rewrite_rule( '^spx-pwa-sw\.js$', 'index.php?spx_pwa_action=sw', 'top' );
	}

	/**
	 * Register the spx_pwa_action query variable with WordPress.
	 *
	 * @param  string[] $vars Registered query variable names.
	 * @return string[]
	 */
	public static function add_query_vars( array $vars ): array {
		$vars[] = 'spx_pwa_action';
		return $vars;
	}

	/**
	 * Route virtual PWA file requests and handle the PWA session redirect.
	 *
	 * Fires on template_redirect at priority 1 (before default handlers).
	 *
	 * Behaviour:
	 *   • If ?spx_app=1 is present and the user is not logged in, redirect
	 *     once to wp-login.php with the card URL as the return destination,
	 *     preserving the ?spx_app=1 parameter so cookie persistence activates
	 *     on successful login.
	 *   • If spx_pwa_action=manifest, serve the Web App Manifest.
	 *   • If spx_pwa_action=sw, serve the Service Worker script.
	 *
	 * @return void
	 */
	public static function handle_virtual_routes(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$spx_app = isset( $_GET['spx_app'] ) ? sanitize_key( $_GET['spx_app'] ) : '';
		// phpcs:enable

		// Session persistence: if the installed PWA opens with ?spx_app=1 and
		// the cookie has expired, send the owner to wp-login once.
		if ( '1' === $spx_app && ! is_user_logged_in() ) {
			$return_url = esc_url_raw(
				add_query_arg( 'spx_app', '1', home_url( '/' ) )
			);
			wp_safe_redirect( wp_login_url( $return_url ), 302 );
			exit;
		}

		$action = (string) get_query_var( 'spx_pwa_action', '' );

		if ( 'manifest' === $action ) {
			self::serve_manifest();
		} elseif ( 'sw' === $action ) {
			self::serve_sw();
		}
	}

	/**
	 * Serve the Web App Manifest.
	 *
	 * Returns HTTP 404 for unauthenticated visitors (the "stealth" layer).
	 * For a logged-in card owner, returns a full JSON manifest that triggers
	 * the browser's "Add to Home Screen" prompt.
	 *
	 * @return never
	 */
	private static function serve_manifest(): never {
		if ( ! is_user_logged_in() ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}

		$uid  = get_current_user_id();
		$user = get_userdata( $uid );

		if ( ! $user instanceof \WP_User ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}

		// ── App name ─────────────────────────────────────────────────────────────
		// Priority: spx_org_name → billing_company (WooCommerce) → spx_company → display_name.
		$app_name = '';
		if ( function_exists( 'get_field' ) ) {
			$app_name = (string) ( get_field( 'spx_org_name', 'user_' . $uid ) ?: '' );
		}
		if ( '' === $app_name && class_exists( 'WooCommerce' ) ) {
			$app_name = (string) ( get_user_meta( $uid, 'billing_company', true ) ?: '' );
		}
		if ( '' === $app_name && function_exists( 'get_field' ) ) {
			$app_name = (string) ( get_field( 'spx_company', 'user_' . $uid ) ?: '' );
		}
		if ( '' === $app_name ) {
			$app_name = $user->display_name;
		}
		$app_name  = sanitize_text_field( $app_name );
		$short_name = mb_strimwidth( $app_name, 0, self::PWA_SHORT_NAME_MAX_LENGTH, '…' );

		// ── Icon ─────────────────────────────────────────────────────────────────
		// Priority: spx_img_brand_blob (ACF image field) → scf_business_logo_url → Gravatar.
		$icon_url = '';
		if ( function_exists( 'get_field' ) ) {
			$blob = get_field( 'spx_img_brand_blob', 'user_' . $uid );
			if ( is_array( $blob ) && ! empty( $blob['url'] ) ) {
				$icon_url = esc_url_raw( (string) $blob['url'] );
			} elseif ( is_string( $blob ) && '' !== $blob ) {
				$icon_url = esc_url_raw( $blob );
			}
		}
		if ( '' === $icon_url ) {
			$icon_url = esc_url_raw( (string) ( get_user_meta( $uid, 'scf_business_logo_url', true ) ?: '' ) );
		}
		if ( '' === $icon_url ) {
			$icon_url = esc_url_raw(
				(string) get_avatar_url( $uid, [ 'size' => 512, 'default' => '404' ] )
			);
		}

		// ── start_url ─────────────────────────────────────────────────────────────
		// The ?spx_app=1 parameter triggers the 1-year cookie extension on login,
		// ensuring the owner stays logged in on their device indefinitely.
		$start_url = esc_url_raw( add_query_arg( 'spx_app', '1', home_url( '/' ) ) );

		// ── Icons array ──────────────────────────────────────────────────────────
		$icons = [];
		if ( '' !== $icon_url ) {
			foreach ( [ '192x192', '512x512' ] as $size ) {
				$icons[] = [
					'src'     => $icon_url,
					'sizes'   => $size,
					'type'    => 'image/png',
					'purpose' => 'any maskable',
				];
			}
		}

		$manifest = [
			'name'             => $app_name,
			'short_name'       => $short_name,
			'description'      => sprintf(
				/* translators: %s: Card owner's app/organisation name */
				__( '%s — Digital Business Card', 'sparxstar-photon-vcard' ),
				$app_name
			),
			'start_url'        => $start_url,
			'scope'            => '/',
			'display'          => 'standalone',
			'orientation'      => 'portrait',
			'background_color' => '#1c1c1e',
			'theme_color'      => '#1c1c1e',
			'icons'            => $icons,
		];

		status_header( 200 );
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		header( 'Service-Worker-Allowed: /' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate, proxy-revalidate, max-age=0' );
		header( 'X-Content-Type-Options: nosniff' );

		$json = wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		if ( false === $json ) {
			status_header( 500 );
			exit;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $json;
		exit;
	}

	/**
	 * Serve the Service Worker script.
	 *
	 * Outputs a personalized vanilla-JavaScript service worker.  The SW
	 * implements:
	 *
	 *  – Install: pre-caches the plugin CSS, JS, and the owner's profile photo.
	 *  – Fetch (static assets):  cache-first strategy for CSS/JS/images.
	 *  – Fetch (HTML / data):    network-first strategy so the QR code and
	 *    contact data are always current; falls back to cache on failure.
	 *  – Offline: returns a minimal embedded offline page when both the
	 *    network and cache are unavailable.
	 *
	 * The SW is served without auth check so already-installed PWAs can
	 * update the worker file transparently.  The precache list is
	 * personalised to the logged-in user when a session exists.
	 *
	 * @return never
	 */
	private static function serve_sw(): never {
		$uid     = is_user_logged_in() ? get_current_user_id() : 0;
		$version = SPARXSTAR_PHOTON_VCARD_VERSION;

		$debug    = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
		$css_file = $debug ? 'sparxstar-photon-vcard.css' : 'sparxstar-photon-vcard.min.css';
		$js_file  = $debug ? 'sparxstar-photon-vcard.js'  : 'sparxstar-photon-vcard.min.js';

		$css_url = esc_url_raw( SPARXSTAR_PHOTON_VCARD_PLUGIN_URL . 'assets/css/' . $css_file );
		$js_url  = esc_url_raw( SPARXSTAR_PHOTON_VCARD_PLUGIN_URL . 'assets/js/' . $js_file );

		$photo_url = '';
		if ( $uid > 0 ) {
			$photo_url = esc_url_raw(
				(string) get_avatar_url( $uid, [ 'size' => 200, 'default' => '404' ] )
			);
		}

		$precache_urls = array_values( array_filter( [ $css_url, $js_url, $photo_url ] ) );
		$precache_json = (string) wp_json_encode( $precache_urls, JSON_UNESCAPED_SLASHES );
		$cache_name    = 'spx-vcard-v' . $version;

		$sw = self::build_sw_script( $cache_name, $precache_json );

		status_header( 200 );
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate, proxy-revalidate, max-age=0' );
		header( 'Service-Worker-Allowed: /' );
		header( 'X-Content-Type-Options: nosniff' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $sw;
		exit;
	}

	/**
	 * Build the Service Worker JavaScript string.
	 *
	 * @param  string $cache_name     Cache storage key (versioned).
	 * @param  string $precache_json  JSON-encoded array of URLs to pre-cache.
	 * @return string                 Complete SW JavaScript source.
	 */
	private static function build_sw_script( string $cache_name, string $precache_json ): string {
		$cache_name_js    = wp_json_encode( $cache_name );
		$offline_html_js  = wp_json_encode( self::offline_html() );

		// Ensure json_encode failures do not produce invalid JS.
		if ( false === $cache_name_js || false === $offline_html_js ) {
			$cache_name_js   = '"spx-vcard"';
			$offline_html_js = '"<html><body>Offline</body></html>"';
		}

		return <<<JS
/* SPARXSTAR Photon VCard — Service Worker
 * Auto-generated by PwaController. Do not edit directly.
 */
'use strict';

var CACHE_NAME   = {$cache_name_js};
var PRECACHE     = {$precache_json};
var OFFLINE_HTML = {$offline_html_js};

var STATIC_EXTS = /\.(css|js|png|jpg|jpeg|svg|gif|webp|woff2?|ttf|ico)(\?.*)?$/i;

/* ── Install: pre-cache static assets ───────────────────────────────── */
self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function (cache) {
      return cache.addAll(PRECACHE.filter(Boolean));
    }).then(function () {
      return self.skipWaiting();
    })
  );
});

/* ── Activate: purge stale caches ────────────────────────────────────── */
self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(
        keys.filter(function (key) { return key !== CACHE_NAME; })
            .map(function (key) { return caches.delete(key); })
      );
    }).then(function () {
      return self.clients.claim();
    })
  );
});

/* ── Fetch ───────────────────────────────────────────────────────────── */
self.addEventListener('fetch', function (event) {
  var req = event.request;

  // Only handle GET requests for http(s) URLs.
  if (req.method !== 'GET' || !req.url.startsWith('http')) return;

  if (STATIC_EXTS.test(req.url)) {
    // Cache-first: CSS, JS, images, fonts.
    event.respondWith(cacheFirst(req));
  } else {
    // Network-first: HTML pages, QR data, contact endpoints.
    event.respondWith(networkFirst(req));
  }
});

/* ── Strategies ──────────────────────────────────────────────────────── */
function cacheFirst(req) {
  return caches.match(req).then(function (cached) {
    if (cached) return cached;
    return fetch(req).then(function (response) {
      if (response && response.status === 200) {
        var clone = response.clone();
        caches.open(CACHE_NAME).then(function (cache) { cache.put(req, clone); });
      }
      return response;
    }).catch(function () {
      return offlinePage();
    });
  });
}

function networkFirst(req) {
  return fetch(req).then(function (response) {
    if (response && response.status === 200) {
      var clone = response.clone();
      caches.open(CACHE_NAME).then(function (cache) { cache.put(req, clone); });
    }
    return response;
  }).catch(function () {
    return caches.match(req).then(function (cached) {
      return cached || offlinePage();
    });
  });
}

function offlinePage() {
  return new Response(OFFLINE_HTML, {
    status: 200,
    headers: { 'Content-Type': 'text/html; charset=utf-8' }
  });
}
JS;
	}

	/**
	 * Return the minimal offline fallback HTML string.
	 *
	 * Matches the plugin's neon-dark card aesthetic so the offline state
	 * feels intentional rather than broken.
	 *
	 * @return string Plain HTML (no closing </html> tag required by spec).
	 */
	private static function offline_html(): string {
		return '<!DOCTYPE html>'
			. '<html lang="en">'
			. '<head>'
			. '<meta charset="utf-8">'
			. '<meta name="viewport" content="width=device-width,initial-scale=1">'
			. '<title>Offline</title>'
			. '<style>'
			. '*{margin:0;padding:0;box-sizing:border-box}'
			. 'body{background:#1c1c1e;color:#f5f5f7;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;'
			. 'min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;'
			. 'gap:16px;padding:24px;text-align:center}'
			. '.icon{font-size:48px}'
			. '.title{font-size:22px;font-weight:600}'
			. '.msg{font-size:15px;color:#aeaeb2;max-width:280px;line-height:1.5}'
			. '</style>'
			. '</head>'
			. '<body>'
			. '<div class="icon">&#x1F4F5;</div>'
			. '<div class="title">You&#8217;re Offline</div>'
			. '<div class="msg">Your business card will be available again once you&#8217;re back online.</div>'
			. '</body></html>';
	}

	/**
	 * Inject the PWA manifest link and Apple web-app meta tags into wp_head.
	 *
	 * Only fires when all conditions are true:
	 *   1. The current visitor is logged in.
	 *   2. The logged-in user's card has been enqueued for this page
	 *      (i.e. the card owner is viewing their own card page).
	 *
	 * The manifest href includes a ?v= cache-buster derived from the user's
	 * last profile-update timestamp so stale manifests are never served after
	 * a profile change.
	 *
	 * @return void
	 */
	public static function inject_pwa_head(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$uid = get_current_user_id();

		if ( ! AssetLoader::has_card_enqueued( $uid ) ) {
			return;
		}

		$user = get_userdata( $uid );
		if ( ! $user instanceof \WP_User ) {
			return;
		}

		// Version: prefer the profile-update timestamp; fall back to registration.
		$profile_version = (int) get_user_meta( $uid, 'spx_pwa_profile_version', true );
		if ( $profile_version <= 0 ) {
			$profile_version = (int) strtotime( $user->user_registered );
		}

		$manifest_url = esc_url(
			add_query_arg( 'v', $profile_version, home_url( '/spx-pwa-manifest.json' ) )
		);

		?>
		<link rel="manifest" href="<?php echo esc_url( $manifest_url ); ?>">
		<meta name="mobile-web-app-capable" content="yes">
		<meta name="apple-mobile-web-app-capable" content="yes">
		<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
		<meta name="theme-color" content="#1c1c1e">
		<script>
		if ('serviceWorker' in navigator) {
			navigator.serviceWorker.register(<?php echo wp_json_encode( home_url( '/spx-pwa-sw.js' ) ); ?>, { scope: '/' })
				.catch(function(){});
		}
		</script>
		<?php
	}

	/**
	 * Extend the auth-cookie lifetime to one year for PWA sessions.
	 *
	 * Fires on the auth_cookie_expiration filter during wp_set_auth_cookie().
	 * Extends the expiration to YEAR_IN_SECONDS when:
	 *   • ?spx_app=1 is present in the current GET request, OR
	 *   • The login form's redirect_to parameter contains spx_app=1
	 *     (the owner just logged in from the PWA's redirect loop).
	 *
	 * @param  int  $expiration Default expiration in seconds.
	 * @param  int  $user_id    The user ID being authenticated (unused here).
	 * @param  bool $remember   Whether "remember me" was checked.
	 * @return int              Extended or default expiration.
	 */
	public static function extend_cookie_for_pwa( int $expiration, int $user_id, bool $remember ): int {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		$in_get = isset( $_GET['spx_app'] ) && '1' === sanitize_key( $_GET['spx_app'] );

		$in_redirect = false;
		if ( isset( $_POST['redirect_to'] ) ) {
			$redirect_to = sanitize_text_field( wp_unslash( (string) $_POST['redirect_to'] ) );
			$in_redirect = false !== strpos( $redirect_to, 'spx_app=1' );
		}
		// phpcs:enable

		if ( $in_get || $in_redirect ) {
			return YEAR_IN_SECONDS;
		}

		return $expiration;
	}

	/**
	 * Bump the profile version meta when WordPress saves a user profile.
	 *
	 * Triggers on the profile_update action so the manifest's ?v= cache-buster
	 * changes whenever the owner updates their profile, forcing browsers to
	 * re-fetch the manifest and pick up any new app name or icon.
	 *
	 * @param  int $user_id The user whose profile was saved.
	 * @return void
	 */
	public static function bump_profile_version( int $user_id ): void {
		update_user_meta( $user_id, 'spx_pwa_profile_version', time() );
	}

	/**
	 * Bump the profile version meta when an ACF user field group is saved.
	 *
	 * Fires on acf/save_post.  Only processes user post IDs (formatted as
	 * "user_{id}" by ACF) to avoid unnecessary updates for other post types.
	 *
	 * @param  int|string $post_id The ACF post identifier.
	 * @return void
	 */
	public static function bump_profile_version_acf( int|string $post_id ): void {
		$post_id_str = (string) $post_id;
		if ( str_starts_with( $post_id_str, 'user_' ) ) {
			$uid = (int) substr( $post_id_str, 5 );
			if ( $uid > 0 ) {
				update_user_meta( $uid, 'spx_pwa_profile_version', time() );
			}
		}
	}
}
