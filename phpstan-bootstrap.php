<?php
/**
 * PHPStan bootstrap for SPARXSTAR Photon VCard.
 *
 * Provides plugin constants so static analysis can resolve paths/URLs
 * without relying on WordPress runtime bootstrapping.
 */

declare(strict_types=1);

if (!defined('SPARXSTAR_PHOTON_VCARD_VERSION')) {
    define('SPARXSTAR_PHOTON_VCARD_VERSION', '0.5.0');
}

if (!defined('SPARXSTAR_PHOTON_VCARD_PLUGIN_PATH')) {
    define('SPARXSTAR_PHOTON_VCARD_PLUGIN_PATH', __DIR__ . '/');
}

if (!defined('SPARXSTAR_PHOTON_VCARD_PLUGIN_URL')) {
    define('SPARXSTAR_PHOTON_VCARD_PLUGIN_URL', 'https://example.test/wp-content/plugins/sparxstar-photon-vcard/');
}