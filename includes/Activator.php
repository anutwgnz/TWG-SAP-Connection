<?php

namespace TwgPluginName;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Activator {

    public static function activate(): void {
        ( new Cron\WpCron() )->schedule();
        self::create_sap_connection_folder(); // Ensure the folder is created during activation
    }
    /**
     * Create folder called "SAP_Connection" in the uploads directory upon plugin activation
     */
    public static function create_sap_connection_folder(): void {
        $upload_dir = wp_upload_dir();
        $sap_connection_dir = trailingslashit( $upload_dir['basedir'] ) . 'SAP_Connection';

        if ( ! file_exists( $sap_connection_dir ) ) {
            wp_mkdir_p( $sap_connection_dir );
        }
    }
}
