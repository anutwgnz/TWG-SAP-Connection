<?php

namespace TwgSapConnection\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use TwgSapConnection\Admin\Common;
use TwgSapConnection\Config;

/**
 * Background dry-run SAP product download via Action Scheduler.
 *
 * Fetches SAP metadata and products into JSON files without syncing WooCommerce.
 */
class DryRunDownload {

    /**
     * Register the Action Scheduler callback.
     */
    public function register(): void {
        add_action( Config::DRY_RUN_HOOK, [ $this, 'handle' ] );
    }

    /**
     * Whether Action Scheduler is available.
     */
    public static function is_available(): bool {
        return function_exists( 'as_enqueue_async_action' ) && function_exists( 'as_get_scheduled_actions' );
    }

    /**
     * Queue an async dry-run download job.
     *
     * @return int Action Scheduler action ID.
     * @throws \RuntimeException When Action Scheduler is unavailable or a job is already queued.
     */
    public function enqueue(): int {
        if ( ! self::is_available() ) {
            throw new \RuntimeException( 'Action Scheduler is not available. WooCommerce must be active.' );
        }

        if ( $this->is_running() ) {
            throw new \RuntimeException( 'A dry-run download job is already pending or in progress.' );
        }

        $action_id = as_enqueue_async_action(
            Config::DRY_RUN_HOOK,
            [],
            Config::ACTION_SCHEDULER_GROUP
        );

        if ( ! $action_id ) {
            throw new \RuntimeException( 'Failed to enqueue dry-run download job.' );
        }

        return (int) $action_id;
    }

    /**
     * Check for pending or in-progress dry-run download actions.
     */
    public function is_running(): bool {
        if ( ! self::is_available() ) {
            return false;
        }

        $statuses = [
            \ActionScheduler_Store::STATUS_PENDING,
            \ActionScheduler_Store::STATUS_RUNNING,
        ];

        foreach ( $statuses as $status ) {
            $actions = as_get_scheduled_actions(
                [
                    'hook'     => Config::DRY_RUN_HOOK,
                    'group'    => Config::ACTION_SCHEDULER_GROUP,
                    'status'   => $status,
                    'per_page' => 1,
                ]
            );

            if ( ! empty( $actions ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get pending/in-progress action IDs for status reporting.
     *
     * @return int[]
     */
    public function get_pending_action_ids(): array {
        if ( ! self::is_available() ) {
            return [];
        }

        $action_ids = [];
        $statuses   = [
            \ActionScheduler_Store::STATUS_PENDING,
            \ActionScheduler_Store::STATUS_RUNNING,
        ];

        foreach ( $statuses as $status ) {
            $actions = as_get_scheduled_actions(
                [
                    'hook'     => Config::DRY_RUN_HOOK,
                    'group'    => Config::ACTION_SCHEDULER_GROUP,
                    'status'   => $status,
                    'per_page' => 10,
                ]
            );

            foreach ( $actions as $action_id => $action ) {
                $action_ids[] = (int) $action_id;
            }
        }

        return array_values( array_unique( $action_ids ) );
    }

    /**
     * Read the last dry-run run summary from the options table.
     *
     * @return array<string, mixed>|null
     */
    public function get_latest_status(): ?array {
        $status = get_option( Config::DRY_RUN_LAST_RUN_OPTION );

        return is_array( $status ) ? $status : null;
    }

    /**
     * Action Scheduler callback: SAP download to JSON only.
     */
    public function handle(): void {
        $started_at = current_time( 'mysql' );

        update_option(
            Config::DRY_RUN_LAST_RUN_OPTION,
            [
                'status'      => 'running',
                'started_at'  => $started_at,
                'finished_at' => null,
                'message'     => 'Dry-run download in progress.',
            ]
        );

        $result = Common::cron_job_function_dry_run_download();

        update_option(
            Config::DRY_RUN_LAST_RUN_OPTION,
            [
                'status'      => $result['success'] ? 'completed' : 'failed',
                'started_at'  => $started_at,
                'finished_at' => current_time( 'mysql' ),
                'message'     => $result['success']
                    ? 'Dry-run download completed successfully.'
                    : 'Dry-run download failed. Check sap_sync_logs for details.',
                'sap_count'   => $result['sap_count'],
                'json_count'  => $result['json_count'],
                'mismatch'    => $result['mismatch'],
            ]
        );
    }
}
