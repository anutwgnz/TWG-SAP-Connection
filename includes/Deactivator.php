<?php

namespace TwgSapConnection;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use TwgSapConnection\Jobs\SchedulerSupport;

class Deactivator {

    public static function deactivate(): void {
        SchedulerSupport::unschedule_all();
    }
}
