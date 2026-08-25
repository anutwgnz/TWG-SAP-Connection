<?php

namespace TwgSapConnection\Cron;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use TwgSapConnection\Admin\Common;
use TwgSapConnection\Config;
use TwgSapConnection\Jobs\ScheduledDownload;
use TwgSapConnection\Jobs\ScheduledSync;
use TwgSapConnection\Jobs\SchedulerSupport;

/**
 * WP-CLI commands for nightly production schedule (manual + async).
 */
class ScheduleCliCommand {

    /**
     * Show scheduled job status and last run summaries.
     *
     * ## EXAMPLES
     *
     *     wp twg-sap-connection schedule status
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Named arguments.
     */
    public function status( array $args, array $assoc_args ): void {
        if ( ! $this->ensure_woocommerce() ) {
            return;
        }

        $download_job = new ScheduledDownload();
        $sync_job     = new ScheduledSync();

        $download_next = SchedulerSupport::get_next_run_time( Config::SCHEDULED_DOWNLOAD_HOOK );
        $sync_next     = SchedulerSupport::get_next_run_time( Config::SCHEDULED_SYNC_HOOK );

        \WP_CLI::log( 'Nightly Action Scheduler jobs:' );
        \WP_CLI::log(
            sprintf(
                '  Download (%s): next run %s',
                Config::SCHEDULED_DOWNLOAD_HOOK,
                $download_next ? gmdate( 'Y-m-d H:i:s', $download_next ) . ' UTC' : 'not scheduled'
            )
        );
        \WP_CLI::log(
            sprintf(
                '  Sync (%s): next run %s',
                Config::SCHEDULED_SYNC_HOOK,
                $sync_next ? gmdate( 'Y-m-d H:i:s', $sync_next ) . ' UTC' : 'not scheduled'
            )
        );

        $this->print_last_run( 'Last scheduled download', $download_job->get_latest_status() );
        $this->print_last_run( 'Last scheduled sync', $sync_job->get_latest_status() );
    }

    /**
     * Run SAP → JSON download immediately.
     *
     * ## EXAMPLES
     *
     *     wp twg-sap-connection schedule run-download-now
     */
    public function run_download_now( array $args, array $assoc_args ): void {
        if ( ! $this->ensure_woocommerce() ) {
            return;
        }

        $started_at = current_time( 'mysql' );
        $result     = Common::cron_job_function_scheduled_download();

        update_option(
            Config::SCHEDULED_LAST_DOWNLOAD_OPTION,
            [
                'status'      => $result['success'] ? 'completed' : 'failed',
                'started_at'  => $started_at,
                'finished_at' => current_time( 'mysql' ),
                'message'     => $result['success']
                    ? 'Scheduled download completed (CLI run-download-now).'
                    : 'Scheduled download failed (CLI run-download-now).',
                'sap_count'   => $result['sap_count'],
                'json_count'  => $result['json_count'],
                'mismatch'    => $result['mismatch'],
            ]
        );

        if ( $result['success'] ) {
            \WP_CLI::success(
                sprintf(
                    'Download complete. SAP count: %s, JSON count: %d, mismatch: %s',
                    null === $result['sap_count'] ? 'unknown' : (string) $result['sap_count'],
                    $result['json_count'],
                    $result['mismatch'] ? 'yes' : 'no'
                )
            );
        } else {
            \WP_CLI::error( 'Scheduled download failed. Check sap_sync_logs for details.' );
        }
    }

    /**
     * Run JSON → WooCommerce sync + reconcile immediately.
     *
     * ## EXAMPLES
     *
     *     wp twg-sap-connection schedule run-sync-now
     */
    public function run_sync_now( array $args, array $assoc_args ): void {
        if ( ! $this->ensure_woocommerce() ) {
            return;
        }

        $started_at = current_time( 'mysql' );
        $result     = Common::cron_job_function_scheduled_sync();

        update_option(
            Config::SCHEDULED_LAST_SYNC_OPTION,
            [
                'status'        => $result['success'] ? 'completed' : 'failed',
                'started_at'    => $started_at,
                'finished_at'   => current_time( 'mysql' ),
                'message'       => $result['message'],
                'json_count'    => $result['json_count'],
                'drafted_count' => $result['drafted_count'],
            ]
        );

        if ( $result['success'] ) {
            \WP_CLI::success(
                sprintf(
                    'Sync complete. JSON SKUs: %d, drafted orphans: %d',
                    $result['json_count'],
                    $result['drafted_count']
                )
            );
        } else {
            \WP_CLI::error( $result['message'] );
        }
    }

    /**
     * Run download then sync in sequence.
     *
     * ## EXAMPLES
     *
     *     wp twg-sap-connection schedule run-full-now
     */
    public function run_full_now( array $args, array $assoc_args ): void {
        if ( ! $this->ensure_woocommerce() ) {
            return;
        }

        \WP_CLI::log( 'Step 1/2: SAP → JSON download...' );
        $this->run_download_now( $args, $assoc_args );

        \WP_CLI::log( 'Step 2/2: JSON → WooCommerce sync...' );
        $this->run_sync_now( $args, $assoc_args );

        \WP_CLI::success( 'Full scheduled pipeline completed.' );
    }

    /**
     * Queue download job via Action Scheduler.
     *
     * ## EXAMPLES
     *
     *     wp twg-sap-connection schedule enqueue-download
     */
    public function enqueue_download( array $args, array $assoc_args ): void {
        if ( ! $this->ensure_action_scheduler() ) {
            return;
        }

        $job = new ScheduledDownload();

        try {
            $action_id = $job->enqueue();
            \WP_CLI::success(
                sprintf(
                    'Download job queued (action ID: %d). Run "wp action-scheduler run --group=%s".',
                    $action_id,
                    Config::ACTION_SCHEDULER_GROUP
                )
            );
        } catch ( \RuntimeException $e ) {
            \WP_CLI::warning( $e->getMessage() );
        }
    }

    /**
     * Queue sync job via Action Scheduler.
     *
     * ## EXAMPLES
     *
     *     wp twg-sap-connection schedule enqueue-sync
     */
    public function enqueue_sync( array $args, array $assoc_args ): void {
        if ( ! $this->ensure_action_scheduler() ) {
            return;
        }

        $job = new ScheduledSync();

        try {
            $action_id = $job->enqueue();
            \WP_CLI::success(
                sprintf(
                    'Sync job queued (action ID: %d). Run "wp action-scheduler run --group=%s".',
                    $action_id,
                    Config::ACTION_SCHEDULER_GROUP
                )
            );
        } catch ( \RuntimeException $e ) {
            \WP_CLI::warning( $e->getMessage() );
        }
    }

    /**
     * @param array<string, mixed>|null $status
     */
    private function print_last_run( string $label, ?array $status ): void {
        if ( null === $status ) {
            \WP_CLI::log( $label . ': no run recorded yet.' );
            return;
        }

        \WP_CLI::log( $label . ':' );
        foreach ( $status as $key => $value ) {
            \WP_CLI::log( sprintf( '  %s: %s', $key, null === $value ? 'null' : (string) $value ) );
        }
    }

    private function ensure_woocommerce(): bool {
        if ( ! class_exists( 'WooCommerce' ) ) {
            \WP_CLI::error( 'WooCommerce must be active for schedule commands.' );
            return false;
        }

        return true;
    }

    private function ensure_action_scheduler(): bool {
        if ( ! $this->ensure_woocommerce() ) {
            return false;
        }

        if ( ! SchedulerSupport::is_available() ) {
            \WP_CLI::error( 'Action Scheduler is not available. WooCommerce must be active.' );
            return false;
        }

        return true;
    }
}
