<?php

namespace TwgPluginName;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Deactivator {

    public static function deactivate(): void {
        ( new Cron\WpCron() )->unschedule();
    }
}
