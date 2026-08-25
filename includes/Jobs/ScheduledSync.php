<?php

namespace TwgSapConnection\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use TwgSapConnection\Admin\Common;
use TwgSapConnection\Config;

/**
 * Nightly production JSON → WooCommerce sync via Action Scheduler.
 */
class ScheduledSync {

    public function register(): void {
        add_action( Config::SCHEDULED_SYNC_HOOK, [ $this, 'handle' ] );
    }

    public function schedule(): void {
        if ( ! SchedulerSupport::is_available() ) {
            return;
        }

        if ( as_next_scheduled_action( Config::SCHEDULED_SYNC_HOOK, [], Config::ACTION_SCHEDULER_GROUP ) ) {
            return;
        }

        as_schedule_recurring_action(
            strtotime( 'tomorrow 1:00am' ),
            DAY_IN_SECONDS,
            Config::SCHEDULED_SYNC_HOOK,
            [],
            Config::ACTION_SCHEDULER_GROUP
        );
    }

    /**
     * Queue a one-off sync job.
     *
     * @throws \RuntimeException
     */
    public function enqueue(): int {
        if ( ! SchedulerSupport::is_available() ) {
            throw new \RuntimeException( 'Action Scheduler is not available. WooCommerce must be active.' );
        }

        if ( SchedulerSupport::is_hook_running( Config::SCHEDULED_SYNC_HOOK ) ) {
            throw new \RuntimeException( 'A scheduled sync job is already pending or in progress.' );
        }

        $action_id = as_enqueue_async_action(
            Config::SCHEDULED_SYNC_HOOK,
            [],
            Config::ACTION_SCHEDULER_GROUP
        );

        if ( ! $action_id ) {
            throw new \RuntimeException( 'Failed to enqueue scheduled sync job.' );
        }

        return (int) $action_id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get_latest_status(): ?array {
        $status = get_option( Config::SCHEDULED_LAST_SYNC_OPTION );

        return is_array( $status ) ? $status : null;
    }

    public function handle(): void {
        $started_at = current_time( 'mysql' );

        update_option(
            Config::SCHEDULED_LAST_SYNC_OPTION,
            [
                'status'      => 'running',
                'started_at'  => $started_at,
                'finished_at' => null,
                'message'     => 'Scheduled sync in progress.',
            ]
        );

        $result = Common::cron_job_function_scheduled_sync();

        update_option(
            Config::SCHEDULED_LAST_SYNC_OPTION,
            [
                'status'         => $result['success'] ? 'completed' : 'failed',
                'started_at'     => $started_at,
                'finished_at'    => current_time( 'mysql' ),
                'message'        => $result['success']
                    ? 'Scheduled sync completed successfully.'
                    : ( $result['message'] ?? 'Scheduled sync failed. Check sap_sync_logs for details.' ),
                'json_count'     => $result['json_count'],
                'drafted_count'  => $result['drafted_count'],
            ]
        );
    }
}
