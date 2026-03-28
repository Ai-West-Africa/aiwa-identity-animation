<?php
/**
 * Uninstall script for SPARXSTAR Photon VCard.
 *
 * WordPress executes this file directly when the plugin is deleted via the
 * admin UI.  All cleanup logic lives in {@see \Starisian\Sparxstar\Photon\Uninstaller}
 * so no business logic runs in global scope here.
 *
 * @package Starisian\Sparxstar\Photon
 */

declare(strict_types=1);

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-spx-uninstaller.php';

\Starisian\Sparxstar\Photon\Uninstaller::run();
