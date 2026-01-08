<?php

namespace TwgPluginName;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Activator {

    public static function activate(): void {
        ( new Cron\WpCron() )->schedule();
    }
}
