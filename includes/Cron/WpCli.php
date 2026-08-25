<?php

namespace TwgSapConnection\Cron;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use TwgSapConnection\Config;
use TwgSapConnection\Jobs\DryRunDownload;

/**
 * WP-CLI commands for real Linux cron jobs.
 *
 * Usage: wp twg-sap-connection dry-run enqueue
 */
class WpCli {

    /**
     * Register CLI commands. Only runs when WP-CLI is active.
     */
    public function register(): void {
        if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
            return;
        }

        \WP_CLI::add_command( Config::SLUG . ' sync', [ $this, 'sync' ] );
        \WP_CLI::add_command( Config::SLUG . ' dry-run', DryRunCliCommand::class );
        \WP_CLI::add_command( Config::SLUG . ' audit', AuditCliCommand::class );
        \WP_CLI::add_command( Config::SLUG . ' reconcile-products', ReconcileCliCommand::class );
        \WP_CLI::add_command( Config::SLUG . ' schedule', ScheduleCliCommand::class );
    }

    /**
     * Legacy sync entry point (use dry-run commands for product download).
     *
     * ## EXAMPLES
     *
     *     wp twg-sap-connection sync
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Named arguments.
     */
    public function sync( array $args, array $assoc_args ): void {
        \WP_CLI::warning( 'Use "wp twg-sap-connection schedule run-full-now" for production sync, or "wp twg-sap-connection dry-run enqueue" for test download.' );
    }
}

/**
 * WP-CLI dry-run product download commands (background job via Action Scheduler).
 */
class DryRunCliCommand {

    /**
     * Queue a background dry-run SAP product download.
     *
     * ## EXAMPLES
     *
     *     wp twg-sap-connection dry-run enqueue
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Named arguments.
     */
    public function enqueue( array $args, array $assoc_args ): void {
        if ( ! $this->ensure_action_scheduler() ) {
            return;
        }

        $job = new DryRunDownload();

        try {
            $action_id = $job->enqueue();
            \WP_CLI::success(
                sprintf(
                    'Dry-run download job queued (action ID: %d). Run "wp action-scheduler run --group=%s" to process it.',
                    $action_id,
                    Config::ACTION_SCHEDULER_GROUP
                )
            );
        } catch ( \RuntimeException $e ) {
            \WP_CLI::warning( $e->getMessage() );
        }
    }

    /**
     * Show dry-run download job status.
     *
     * ## EXAMPLES
     *
     *     wp twg-sap-connection dry-run status
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Named arguments.
     */
    public function status( array $args, array $assoc_args ): void {
        if ( ! $this->ensure_action_scheduler() ) {
            return;
        }

        $job             = new DryRunDownload();
        $pending_actions = $job->get_pending_action_ids();
        $latest          = $job->get_latest_status();

        if ( ! empty( $pending_actions ) ) {
            \WP_CLI::log(
                sprintf(
                    'Pending/in-progress action IDs: %s',
                    implode( ', ', array_map( 'strval', $pending_actions ) )
                )
            );
        } else {
            \WP_CLI::log( 'No pending or in-progress dry-run download jobs.' );
        }

        if ( null === $latest ) {
            \WP_CLI::log( 'No dry-run download has been recorded yet.' );
            return;
        }

        \WP_CLI::log( 'Last dry-run download run:' );
        foreach ( $latest as $key => $value ) {
            \WP_CLI::log( sprintf( '  %s: %s', $key, null === $value ? 'null' : (string) $value ) );
        }
    }

    /**
     * Run dry-run SAP product download immediately in CLI (no browser, no queue).
     *
     * ## EXAMPLES
     *
     *     wp twg-sap-connection dry-run run-now
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Named arguments.
     */
    public function run_now( array $args, array $assoc_args ): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            \WP_CLI::error( 'WooCommerce must be active to run dry-run product download.' );
            return;
        }

        $job        = new DryRunDownload();
        $started_at = current_time( 'mysql' );

        update_option(
            Config::DRY_RUN_LAST_RUN_OPTION,
            [
                'status'      => 'running',
                'started_at'  => $started_at,
                'finished_at' => null,
                'message'     => 'Dry-run download in progress (CLI run-now).',
            ]
        );

        $result = \TwgSapConnection\Admin\Common::cron_job_function_dry_run_download();

        update_option(
            Config::DRY_RUN_LAST_RUN_OPTION,
            [
                'status'      => $result['success'] ? 'completed' : 'failed',
                'started_at'  => $started_at,
                'finished_at' => current_time( 'mysql' ),
                'message'     => $result['success']
                    ? 'Dry-run download completed successfully (CLI run-now).'
                    : 'Dry-run download failed. Check sap_sync_logs for details.',
                'sap_count'   => $result['sap_count'],
                'json_count'  => $result['json_count'],
                'mismatch'    => $result['mismatch'],
            ]
        );

        if ( $result['success'] ) {
            \WP_CLI::success(
                sprintf(
                    'Dry-run download completed. JSON files updated under uploads/SAP_Connection/. SAP count: %s, JSON count: %d, mismatch: %s',
                    null === $result['sap_count'] ? 'unknown' : (string) $result['sap_count'],
                    $result['json_count'],
                    $result['mismatch'] ? 'yes' : 'no'
                )
            );
        } else {
            \WP_CLI::error( 'Dry-run download failed. Check sap_sync_logs for details.' );
        }
    }

    /**
     * @return bool False when Action Scheduler is unavailable.
     */
    private function ensure_action_scheduler(): bool {
        if ( ! class_exists( 'WooCommerce' ) ) {
            \WP_CLI::error( 'WooCommerce must be active for dry-run background jobs.' );
            return false;
        }

        if ( ! DryRunDownload::is_available() ) {
            \WP_CLI::error( 'Action Scheduler is not available. WooCommerce must be active.' );
            return false;
        }

        return true;
    }
}
