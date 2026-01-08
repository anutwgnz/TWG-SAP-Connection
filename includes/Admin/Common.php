<?php

namespace TwgPluginName\Admin;
use TwgPluginName\Api\Client;

// Ensure the Product class is imported
use TwgPluginName\Admin\Product;

// Ensure the Customer and Price classes are imported
use TwgPluginName\Admin\Customer;
use TwgPluginName\Admin\Price;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Common {
    /**
     * Retrieve a plugin setting from the WordPress options table.
     *
     * @param string $key The setting key to retrieve.
     * @return mixed The setting value or null if not found.
     */
    public static function get_setting( string $key ) {
        $options = get_option( 'twg_sap_connection_settings', [] );
        return $options[ $key ] ?? null;
    }

    public static function set_setting( $key, $value ) {
        return update_option( $key, $value );
    }


    /**
     * Example: Log an admin action
     */
    public static function add_log( string $type, string $message ): void {
        $logDir = self::get_setting('twg_log_dir') ?: ABSPATH . 'unleashed/';
        $file = $logDir . 'unleashed_logs_' . date('Y_m_d') . '.log';
        $time = date('H:i:s.');
        $log = "[$time] $type > $message\n";
        file_put_contents($file, $log, LOCK_EX | FILE_APPEND);
    }
}