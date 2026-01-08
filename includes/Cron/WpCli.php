<?php

namespace TwgPluginName\Cron;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use TwgPluginName\Api;
use TwgPluginName\Config;

/**
 * WP-CLI commands for real Linux cron jobs.
 *
 * Usage: wp twg-plugin-name sync
 * Linux cron example: 0 * * * * cd /var/www/html && wp twg-plugin-name sync
 */
class WpCli {

    /**
     * Register CLI commands. Only runs when WP-CLI is active.
     * Check if wp-cli / wp is runable by cron before setting it up.
     */
    public function register(): void {
        if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
            return;
        }

        \WP_CLI::add_command( Config::SLUG . ' sync', [ $this, 'sync' ] );
    }

    /**
     * Sync data from external API.
     *
     * ## EXAMPLES
     *
     *     wp twg-plugin-name sync
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Named arguments.
     */
    public function sync( array $args, array $assoc_args ): void {
        $client = new Api\Client( Config::API_URL );
        $data   = $client->get( '/items' );

        // Process $data as needed.

        \WP_CLI::success( 'Synced ' . count( $data ) . ' items.' );
    }
}
