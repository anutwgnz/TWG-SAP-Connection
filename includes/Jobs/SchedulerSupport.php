<?php

namespace TwgSapConnection\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use TwgSapConnection\Config;

/**
 * Shared Action Scheduler helpers for plugin background jobs.
 */
class SchedulerSupport {

    /**
     * Whether Action Scheduler scheduling functions are available.
     */
    public static function is_available(): bool {
        return function_exists( 'as_schedule_recurring_action' )
            && function_exists( 'as_enqueue_async_action' )
            && function_exists( 'as_get_scheduled_actions' );
    }

    /**
     * Ensure nightly download and sync recurring actions exist.
     */
    public static function ensure_scheduled(): void {
        if ( ! self::is_available() || ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        ( new ScheduledDownload() )->schedule();
        ( new ScheduledSync() )->schedule();
    }

    /**
     * Remove all plugin Action Scheduler jobs and legacy WP-Cron hooks.
     */
    public static function unschedule_all(): void {
        if ( function_exists( 'as_unschedule_all_actions' ) ) {
            as_unschedule_all_actions( null, Config::ACTION_SCHEDULER_GROUP );
        }

        wp_clear_scheduled_hook( Config::CRON_HOOK );
        wp_clear_scheduled_hook( Config::CRON_HOOK . '_one_am' );
        wp_clear_scheduled_hook( Config::CRON_HOOK . '_eleven_pm' );
    }

    /**
     * Check whether a hook has pending or in-progress actions.
     */
    public static function is_hook_running( string $hook ): bool {
        if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
            return false;
        }

        $statuses = [
            \ActionScheduler_Store::STATUS_PENDING,
            \ActionScheduler_Store::STATUS_RUNNING,
        ];

        foreach ( $statuses as $status ) {
            $actions = as_get_scheduled_actions(
                [
                    'hook'     => $hook,
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
     * Get pending/in-progress action IDs for a hook.
     *
     * @return int[]
     */
    public static function get_pending_action_ids( string $hook ): array {
        if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
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
                    'hook'     => $hook,
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
     * Next scheduled timestamp for a recurring hook.
     */
    public static function get_next_run_time( string $hook ): ?int {
        if ( ! function_exists( 'as_next_scheduled_action' ) ) {
            return null;
        }

        $next = as_next_scheduled_action( $hook, [], Config::ACTION_SCHEDULER_GROUP );

        return $next ? (int) $next : null;
    }

    /**
     * Clear legacy WP-Cron production hooks (replaced by Action Scheduler).
     */
    public static function clear_legacy_wp_cron(): void {
        wp_clear_scheduled_hook( Config::CRON_HOOK );
        wp_clear_scheduled_hook( Config::CRON_HOOK . '_one_am' );
    }
}
