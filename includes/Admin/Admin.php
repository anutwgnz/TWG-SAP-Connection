<?php

namespace TwgPluginName\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin {

    public function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_menu', [ $this, 'create_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }
    public function enqueue_assets(): void {
        // Enqueue admin CSS/JS here.

    }

    /**
     * Create admin menu page here call "SAP Connection Settings", Under this need to have sub menu items as "General Settings", "Connection Settings", "Logs"
     * On General Settings page, need to have options to set the following:
     * - DATABASE 
     * - USERNAME
     * - PASSWORD
     * - HOST
     * - PORT
     * Then need to create another sub menu page called "Full Sync" under the "SAP Connection Settings" menu
     * "Full Sync" page should have a button to "Sync Products from SAP", This button when clicked should trigger a function to fetch products from SAP and store them in WordPress database, 
     * Then need to create another sub menu page called "Delete Products" under the "SAP Connection Settings" menu
     * This "Delete Products" page should have a button to "Delete All Synced Products", This button when clicked should trigger a function to delete all products that were synced from SAP on WordPress database.
     */

    public function create_admin_menu(): void {
        add_menu_page(
            'SAP Connection Settings',
            'SAP Connection',
            'manage_options',
            'sap-connection-settings',
            [ $this, 'render_general_settings_page' ],
            'dashicons-admin-generic',
            81
        );

        add_submenu_page(
            'sap-connection-settings',
            'General Settings',
            'General Settings',
            'manage_options',
            'sap-connection-settings',
            [ $this, 'render_general_settings_page' ]
        );

        add_submenu_page(
            'sap-connection-settings',
            'Full Sync',
            'Full Sync',
            'manage_options',
            'sap-full-sync',
            [ $this, 'render_full_sync_page' ]
        );

        add_submenu_page(
            'sap-connection-settings',
            'Delete Products',
            'Delete Products',
            'manage_options',
            'sap-delete-products',
            [ $this, 'render_delete_products_page' ]
        );
    }

    public function register_settings(): void {
        register_setting( 'sap_connection_settings_group', 'sap_database' );
        register_setting( 'sap_connection_settings_group', 'sap_username' );
        register_setting( 'sap_connection_settings_group', 'sap_password' );
        register_setting( 'sap_connection_settings_group', 'sap_host' );
        register_setting( 'sap_connection_settings_group', 'sap_port' );
        register_setting( 'sap_connection_settings_group', 'product_codes_json_url' );
        register_setting( 'sap_connection_settings_group', 'product_images_json_url' );
        register_setting( 'sap_connection_settings_group', 'product_brand_json_url' );
        register_setting( 'sap_connection_settings_group', 'product_type_json_url' );
        register_setting( 'sap_connection_settings_group', 'product_sector_json_url' );

        add_settings_section(
            'sap_connection_main_section',
            'SAP Connection Settings',
            null,
            'sap-connection-settings'
        );
        add_settings_section(
            'sap_connection_main_section',
            'SAP Settings',
            null,
            'sap-connection-settings'
        );
        add_settings_field(
            'sap_database',
            'Database',
            [ $this, 'render_database_field' ],
            'sap-connection-settings',
            'sap_connection_main_section'
        );
        add_settings_field(
            'sap_username',
            'Username',
            [ $this, 'render_username_field' ],
            'sap-connection-settings',
            'sap_connection_main_section'
        );
        add_settings_field(
            'sap_password',
            'Password',
            [ $this, 'render_password_field' ],     
            'sap-connection-settings',
            'sap_connection_main_section'
        );
        add_settings_field(
            'sap_host',
            'Host',
            [ $this, 'render_host_field' ],
            'sap-connection-settings',
            'sap_connection_main_section'
        );
        add_settings_field(
            'sap_port',
            'Port',
            [ $this, 'render_port_field' ],
            'sap-connection-settings',
            'sap_connection_main_section'
        ); 
        
        add_settings_field(
            'twg_delete_products_button',
            'Delete All Synced Products',
            [ $this, 'render_delete_products_button' ],
            'sap-delete-products',
            'sap_connection_main_section'
        );

        add_settings_field(
            'product_codes_json_url',
            'Product Codes JSON URL',
            [ $this, 'render_product_codes_field' ],
            'sap-connection-settings',
            'sap_connection_main_section'
        );

        add_settings_field(
            'product_images_json_url',
            'Product Images JSON URL',
            [ $this, 'render_product_images_field' ],
            'sap-connection-settings',
            'sap_connection_main_section'
        );

        add_settings_field(
            'product_brand_json_url',
            'Product Brand JSON URL',
            [ $this, 'render_product_brand_field' ],
            'sap-connection-settings',
            'sap_connection_main_section'
        );

        add_settings_field(
            'product_type_json_url',
            'Product Type JSON URL',
            [ $this, 'render_product_type_field' ],
            'sap-connection-settings',
            'sap_connection_main_section'
        );

        add_settings_field(
            'product_sector_json_url',
            'Product Sector JSON URL',
            [ $this, 'render_product_sector_field' ],
            'sap-connection-settings',
            'sap_connection_main_section'
        );
    }

    public function render_general_settings_page(): void {
        // Render the General Settings page content here.
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'SAP Connection Settings', 'twg-plugin-name' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'sap_connection_settings_group' );
                do_settings_sections( 'sap-connection-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render field callbacks   
     */
    public function render_database_field(): void {
        $value = get_option( 'sap_database', '' );
        printf(
            '<input type="text" name="sap_database" value="%s" />',
            esc_attr( $value )
        );
    }
    public function render_username_field(): void {
        $value = get_option( 'sap_username', '' );
        printf(
            '<input type="text" name="sap_username" value="%s" />',
            esc_attr( $value )
        );
    }
    public function render_password_field(): void {
        $value = get_option( 'sap_password', '' );
        printf(
            '<input type="password" name="sap_password" value="%s" />',
            esc_attr( $value )
        );
    }
    public function render_host_field(): void {
        $value = get_option( 'sap_host', '' );
        printf(
            '<input type="text" name="sap_host" value="%s" />',
            esc_attr( $value )
        );
    }
    public function render_port_field(): void {
        $value = get_option( 'sap_port', '' );
        printf(
            '<input type="text" name="sap_port" value="%s" />',
            esc_attr( $value )
        );      
    }

    public function render_product_codes_field(): void {
        $value = get_option( 'product_codes_json_url', '' );
        ?>
        <input type="text" name="product_codes_json_url" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
        <p class="description">Enter the URL for the Product Codes JSON file.</p>
        <?php
    }

    public function render_product_images_field(): void {
        $value = get_option( 'product_images_json_url', '' );
        ?>
        <input type="text" name="product_images_json_url" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
        <p class="description">Enter the URL for the Product Images JSON file.</p>
        <?php
    }

    public function render_product_brand_field(): void {
        $value = get_option( 'product_brand_json_url', '' );
        ?>
        <input type="text" name="product_brand_json_url" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
        <p class="description">Enter the URL for the Product Brand JSON file.</p>
        <?php
    }
    public function render_product_type_field(): void {
        $value = get_option( 'product_type_json_url', '' );
        ?>
        <input type="text" name="product_type_json_url" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
        <p class="description">Enter the URL for the Product Type JSON file.</p>
        <?php
    }
    public function render_product_sector_field(): void {
        $value = get_option( 'product_sector_json_url', '' );
        ?>
        <input type="text" name="product_sector_json_url" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
        <p class="description">Enter the URL for the Product Sector JSON file.</p>
        <?php
    }
    public function render_full_sync_page(): void {
        // Render the Full Sync page content here.
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
         if ( isset( $_POST['twg_full_sync'] ) && check_admin_referer( 'twg_full_sync_action', 'twg_full_sync_nonce' ) ) {
            echo '<div id="sync-log" style="background: #f9f9f9; padding: 10px; border: 1px solid #ddd; height: 300px; overflow-y: auto;">';
            //$this->log_and_execute('TwgPluginName\\Cron\\Common', 'sap_manual_run', 'Manual Run completed.');
            echo '</div>';
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Full Sync', 'twg-plugin-name' ); ?></h1>
            <form method="post" action="">
                <?php
                submit_button( 'Sync Products from SAP', 'primary', 'twg_sync_products_button' );
                ?>
            </form>
        </div>
        <?php
    }

        private function log_and_execute($class_name, $function_name, $success_message) {
        if ( class_exists( $class_name ) && method_exists( $class_name, $function_name ) ) {
            $result = call_user_func( [ $class_name, $function_name ] );
            if ( $result ) {
                echo '<p>' . esc_html( $success_message ) . '</p>';
            } else {
                echo '<p>' . esc_html( $function_name . ' failed.' ) . '</p>';
            }
        } else {
            echo '<p>' . esc_html( $class_name . '::' . $function_name . ' does not exist.' ) . '</p>';
        }
    }

    public function render_delete_products_page(): void {
        // Render the Delete Products page content here.

    }
}