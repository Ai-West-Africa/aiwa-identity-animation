<?php
/**
 * SPARXSTAR Photon VCard
 * @version           0.5.0
 * @package           sparxstar-photon-vcard
 * @author            Starisian Technologies (Max Barrett) <support@starisian.com>
 * @copyright         2025 Starisian Technologies. All rights reserved.
 * @license           Starisian Technologies Proprietary
 *
 * @wordpress-plugin
 * Plugin Name:       SPARXSTAR Photon VCard
 * Plugin URI:        https://starisian.com/sparxstar/sparxstar-photon-vcard
 * Description:       roduction-grade digital business card with motion/touch triggers. Finalized for accessibility, security, and legacy hardware resilience.
 * Version:           0.5.0
 * Requires at least: 6.8
 * Requires PHP:      8.2
 * Author:            Starisian Technologies (Max Barrett) <support@starisian.com>
 * Author URI:        https://starisian.com
 * Text Domain:       sparxstar-photon-vcard
 * License:           Starisian Technologies Proprietary
 * License URI:       https://starisian.com/license/starisian-technology-proprietary
 * Update URI:        https://starisian.com/sparxstar/sparxstar-photon-vcard/update
 */

if(! defined(ABSPATH){
   exit;
}

define( 'SPX_PHOTON_PLUGIN_PREFIX', 'SPX_PHOTON');
/**
 * Absolute path to the plugin directory.
 */
define( 'SPX_PHOTON_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

/**
 * URL to the plugin directory.
 */
define( 'SPX_PHOTON_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Current plugin version.
 */
define( 'SPX_PHOTON_VERSION', '0.5.0' );

if(! defined( 'SPX_PHOTON_DELETE_ON_UNINSTALL' ) {
   define( 'SPX_PHOTON_DELETE_ON_UNINSTALL', false );
}


function spx_phoron_activation() :void {
  flush_rewrite_rules();
}
/**
 * Perform actions on plugin deactivation.
 *
 * @return void
 */
function spx_photon_deactivate() :void {
  // Perform actions on plugin deactivation (e.g., remove options).
  flush_rewrite_rules();
}

function spx_photon_uninstall() :voic {
    $file = SPX_PHOTON_PLUGIN_PATH . "uninstall.php";
    if(file_exists($file)){
          require_once $file;
    }
}

function spx_photon_init():void {
  $class = SPX_PHOTON_PLUGIN_PATH . "src/SparxstarPhotonVCard.php";
  if(file_exists(class) && Class_exists($class::CLASS){
     require_once $class;
     class_allas($class as Orchestrator);
  }
}

add_action('plugins_loaded', 'spx_photon_init');
