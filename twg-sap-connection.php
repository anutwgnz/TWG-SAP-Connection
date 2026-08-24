<?php
/**
 * Plugin Name: TWG SAP Connection
 * Description: Connect to SAP systems seamlessly from WordPress.
 * Version:     1.0.0
 * Author:      Anu Ranasinghe
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Tested up to: 6.8
 * Text Domain: twg-sap-connection
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TWG_PLUGIN_NAME_VERSION', '1.0.0' );
define( 'TWG_PLUGIN_NAME_FILE', __FILE__ );

require_once __DIR__ . '/vendor/autoload.php';

register_activation_hook( __FILE__, [ 'TwgSapConnection\\Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'TwgSapConnection\\Deactivator', 'deactivate' ] );

use TwgSapConnection\Admin\Common;

add_action( 'plugins_loaded', function() {
    ( new TwgSapConnection\Plugin() )->run();
} );

add_action('init', function() {
    $sap_database  = Common::get_setting('sap_database');
    $sap_username   = Common::get_setting('sap_username');
    $sap_password  = Common::get_setting('sap_password');
    $sap_host  = Common::get_setting('sap_host');
    $sap_host_path  = Common::get_setting('sap_host_path');
    $sap_port  = Common::get_setting('sap_port');

    if ($sap_database && $sap_username && $sap_password && $sap_host && $sap_host_path && $sap_port) {
        $GLOBALS['twg_sap_connection_client'] = new TwgSapConnection\Api\Client();
    }
});
