<?php
/**
 * Plugin Name: TWG SAP Connection
 * Description: Connect to SAP systems seamlessly from WordPress.
 * Version:     1.0.0
 * Author:      Anu Ranasinghe
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TWG_PLUGIN_NAME_VERSION', '1.0.0' );
define( 'TWG_PLUGIN_NAME_FILE', __FILE__ );

require_once __DIR__ . '/vendor/autoload.php';

register_activation_hook( __FILE__, [ 'TwgPluginName\\Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'TwgPluginName\\Deactivator', 'deactivate' ] );

( new TwgPluginName\Plugin() )->run();
