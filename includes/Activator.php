<?php

namespace TwgSapConnection;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use TwgSapConnection\Jobs\SchedulerSupport;

class Activator {

    public static function activate(): void {
        self::create_sap_connection_folder();
        SchedulerSupport::clear_legacy_wp_cron();
        SchedulerSupport::ensure_scheduled();
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
