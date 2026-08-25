<?php

namespace TwgSapConnection;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin configuration constants.
 * Edit these values when setting up a new plugin.
 */
class Config {

    /** Plugin slug, used in WP-CLI commands and hook prefixes. */
    public const SLUG = 'twg-sap-connection';

    /** Cron hook prefix. */
    public const CRON_HOOK = 'twg_sap_connection_cron';

    /** Action Scheduler hook for dry-run SAP product download. */
    public const DRY_RUN_HOOK = 'twg_sap_dry_run_download';

    /** Action Scheduler group for plugin background jobs. */
    public const ACTION_SCHEDULER_GROUP = 'twg-sap-connection';

    /** WP option key storing the last dry-run download run summary. */
    public const DRY_RUN_LAST_RUN_OPTION = 'twg_sap_dry_run_last_run';

    /** Action Scheduler hook for nightly SAP → JSON download. */
    public const SCHEDULED_DOWNLOAD_HOOK = 'twg_sap_scheduled_download';

    /** Action Scheduler hook for nightly JSON → WooCommerce sync. */
    public const SCHEDULED_SYNC_HOOK = 'twg_sap_scheduled_sync';

    /** WP option key storing the last scheduled download run summary. */
    public const SCHEDULED_LAST_DOWNLOAD_OPTION = 'twg_sap_scheduled_last_download';

    /** WP option key storing the last scheduled sync run summary. */
    public const SCHEDULED_LAST_SYNC_OPTION = 'twg_sap_scheduled_last_sync';

    /** External API base URL. */
    //public const API_URL = 'https://api.example.com';
}
