<?php

namespace TwgSapConnection;

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
        /**
         * WooCommerce-dependent modules — hooked late to ensure all plugins are loaded.
         */
        add_action( 'plugins_loaded', [ $this, 'load_woocommerce_modules' ], 20 );
    }
    
    /**
     * Load WooCommerce-dependent modules once all plugins have been initialised.
     */
    public function load_woocommerce_modules(): void {
        if ( $this->is_woocommerce_active() ) {
            //( new WooCommerce\Loader() )->register();
            new \WooCommerce();
        }
    }

    /**
     * Check whether WooCommerce is active.
     *
     * @return bool
     */
    private function is_woocommerce_active(): bool {
        return class_exists( 'WooCommerce' );
    }
    
}
