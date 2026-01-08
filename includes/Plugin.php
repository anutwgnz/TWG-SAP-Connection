<?php

namespace TwgPluginName;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin {

    public function run(): void {
        ( new Cron\WpCron() )->register();
        ( new Cron\WpCli() )->register();

        if ( is_admin() ) {
            new Admin\Admin();
        } else {
            new Frontend\Frontend();
        }
    }
}
