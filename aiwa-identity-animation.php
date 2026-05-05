<?php
/**
 * AiWA Identity Animation
 *
 * @version           1.0.0
 * @package           aiwa-identity-animation
 * @author            AiWA – Ai West Africa
 * @copyright         2025 AiWA – Ai West Africa. All rights reserved.
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       AiWA Identity Animation
 * Plugin URI:        https://aiwestafrica.com
 * Description:       Gutenberg block that renders the AiWA brand identity animation with cycling text. Customisable size, colour, font and text. Degrades gracefully on low-end and older mobile devices (Android 4.4+, 2014 era).
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            AiWA – Ai West Africa
 * Author URI:        https://aiwestafrica.com
 * Text Domain:       aiwa-identity-animation
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
die;
}

/** Current plugin version. */
define( 'AIWA_IDENTITY_ANIMATION_VERSION', '1.0.0' );

/** Plugin base path (with trailing slash). */
define( 'AIWA_IDENTITY_ANIMATION_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

/** Plugin base URL (with trailing slash). */
define( 'AIWA_IDENTITY_ANIMATION_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once AIWA_IDENTITY_ANIMATION_PLUGIN_PATH . 'src/includes/class-aiwa-block.php';

\AiWA\IdentityAnimation\Block::register();
