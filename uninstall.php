<?php
/**
 * Uninstall script for AiWA Identity Animation.
 *
 * WordPress executes this file directly when the plugin is deleted via the
 * admin UI.  All cleanup logic lives in {@see \AiWA\IdentityAnimation\Uninstaller}
 * so no business logic runs in global scope here.
 *
 * @package AiWA\IdentityAnimation
 */

declare(strict_types=1);

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
exit;
}

require_once __DIR__ . '/src/includes/class-aiwa-uninstaller.php';

\AiWA\IdentityAnimation\Uninstaller::run();
