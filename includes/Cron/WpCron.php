<?php

namespace TwgPluginName\Cron;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use TwgPluginName\Api;
use TwgPluginName\Config;

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
    }

    /**
     * Schedule the cron event. Called once on plugin activation.
     */
    public function schedule(): void {
        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( time(), 'hourly', self::HOOK );
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
        $client = new Api\Client( Config::API_URL );
        $data   = $client->get( '/items' );

        // Process $data as needed.
    }
}
