<?php

namespace TwgPluginName\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin {

    public function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function enqueue_assets(): void {
        // Enqueue admin CSS/JS here.
    }
}
