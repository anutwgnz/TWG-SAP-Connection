<?php

namespace TwgSapConnection\Cron;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use TwgSapConnection\Config;
use TwgSapConnection\Admin\Common;
use TwgSapConnection\Jobs\SchedulerSupport;

/**
 * Legacy WP-Cron callbacks.
 *
 * Production scheduling uses Action Scheduler (midnight download, 1 AM sync).
 * Legacy hooks remain registered for backward compatibility if old events still exist.
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
     * Legacy schedule method — production jobs use Action Scheduler.
     */
    public function schedule(): void {
        SchedulerSupport::clear_legacy_wp_cron();
    }

    /**
     * Remove legacy WP-Cron events.
     */
    public function unschedule(): void {
        SchedulerSupport::clear_legacy_wp_cron();
    }

    /**
     * Legacy midnight download hook (delegates to scheduled download orchestrator).
     */
    public function run(): void {
        Common::add_log( 'Cron-Job', 'Legacy WP-Cron download hook fired. Use Action Scheduler nightly download instead.' );
        Common::cron_job_function_scheduled_download();
    }

    public function run_eleven_pm(): void {
        Common::cron_job_function();
    }

    public function schedule_one_am(): void {
        // Legacy — sync is scheduled via Action Scheduler at 1 AM.
    }

    /**
     * Legacy 1 AM sync hook (delegates to scheduled sync orchestrator).
     */
    public function run_one_am(): void {
        Common::add_log( 'Cron-Job', 'Legacy WP-Cron sync hook fired. Use Action Scheduler nightly sync instead.' );
        Common::cron_job_function_scheduled_sync();
    }
}
