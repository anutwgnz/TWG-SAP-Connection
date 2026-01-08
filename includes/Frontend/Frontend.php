<?php

namespace TwgPluginName\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Frontend {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function enqueue_assets(): void {
        // Enqueue public CSS/JS here.
    }
}
