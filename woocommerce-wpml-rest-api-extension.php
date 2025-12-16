<?php
/**
 * Plugin Name: WooCommerce WPML REST API Extension
 * Plugin URI: https://github.com/nuobit/woocommerce-wpml-api-rest-extension
 * Description: Extends WPML’s REST API integration by adding missing multilingual support, enhancing compatibility, and providing fixes for WooCommerce and other REST endpoints.
 * Version: 1.0.1
 * Author: NuoBiT Solutions, S.L.
 * Author URI: https://www.nuobit.com/
 * License: GPLv3 or later
 * Text Domain: woocommerce-wpml-api-rest-extension
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html 
 */

defined( 'ABSPATH' ) || exit;

// Only load the WPML-related fixes if WPML exists
if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
    require_once __DIR__ . '/includes/class-wcwpml-term-language.php';
    WCWPML_Term_Language::init();
}