<?php

namespace TwgPluginName;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin configuration constants.
 * Edit these values when setting up a new plugin.
 */
class Config {

    /** Plugin slug, used in WP-CLI commands and hook prefixes. */
    public const SLUG = 'twg-plugin-name';

    /** Cron hook prefix. */
    public const CRON_HOOK = 'twg_plugin_name_cron';

    /** External API base URL. */
    public const API_URL = 'https://api.example.com';
}
