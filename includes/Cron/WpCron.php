<?php

namespace TwgSapConnection\Cron;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use TwgSapConnection\Api;
use TwgSapConnection\Config;
use TwgSapConnection\Admin\Product;
use TwgSapConnection\Admin\Common;
/**
 * WP-Cron scheduled tasks.
 *
 * WordPress cron is "pseudo-cron" - it runs on page load, not real server time.
 * For time-critical tasks, use WpCli.php with a real Linux cron instead.
 */
class WpCron {

    /** Unique hook name for this cron job. */
    private const HOOK = Config::CRON_HOOK;

    /**
     * Register the cron callback. Called on every page load.
     */
    public function register(): void {
        add_action( self::HOOK, [ $this, 'run' ] );
        add_action( self::HOOK . '_eleven_pm', [ $this, 'run_eleven_pm' ] );
        add_action( self::HOOK . '_one_am', [ $this, 'run_one_am' ] );
    }

    /**
     * Schedule the cron event. Called once on plugin activation.
     */
    public function schedule(): void {
        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( strtotime('midnight'), 'daily', self::HOOK );
        }
    }

    /**
     * Remove the cron event. Called on plugin deactivation.
     */
    public function unschedule(): void {
        wp_clear_scheduled_hook( self::HOOK );
    }

    /**
     * The actual task that runs on schedule.
     */
    public function run(): void {
        //Common::add_log( 'Cron-Job','TWG SAP Connection cron job executed at ' . date( 'Y-m-d H:i:s' ) );
        
        //Common::add_log( 'Cron-Job','TWG SAP Connection cron job Meta to JSON executed at ' . date( 'Y-m-d H:i:s' ) );
        Product::fetch_meta_sap();
        
        //Common::add_log( 'Cron-Job','TWG SAP Connection cron job Products to JSON executed at ' . date( 'Y-m-d H:i:s' ) );
        Product::fetch_products();
        
    }
    
    
    public function run_eleven_pm(): void {
        Common::cron_job_function();
    }

    public function schedule_one_am(): void {

        $hook_one_am = self::HOOK . '_one_am';
    
        if ( ! wp_next_scheduled( $hook_one_am ) ) {
            wp_schedule_event( strtotime('tomorrow 1am'), 'daily', $hook_one_am );
        }
        add_action( $hook_one_am, [ $this, 'run_one_am' ] );
    }
    
    public function run_one_am(): void {
        // Common::add_log(
        //     'Cron-Job',
        //     'TWG SAP Connection 1 AM cron job executed at ' . date('Y-m-d H:i:s')
       //);
    
        //Common::cron_job_function();
        //Common::add_log( 'Cron-Job','TWG SAP Connection cron job Products JSON to DB executed at ' . date( 'Y-m-d H:i:s' ) );
        Product::sap_sync_products();
        //Product::fetch_products();
    }
}
