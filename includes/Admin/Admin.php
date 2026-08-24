<?php

namespace TwgSapConnection\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin {

    public function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_menu', [ $this, 'create_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_init', [$this, 'init_admin_columns']);
        add_action('wp_ajax_twg_get_logs', [ $this, 'ajax_get_logs' ]);
        add_action( 'wp_ajax_sap_sync_single_order', [ $this, 'wp_ajax_sap_sync_single_order' ] );
        
        //add_action( 'admin_init', [ $this, 'maybe_bootstrap_order_sync' ] );
    }
    public function enqueue_assets(): void {
        wp_enqueue_script(
            'sweetalert2',
            'https://cdn.jsdelivr.net/npm/sweetalert2@11',
            [],
            null,
            true
        );
        wp_enqueue_script(
            'datatables',
            'https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js',
            [],
            null,
            true
        );
        wp_enqueue_style(
            'datatables',
            'https://cdn.datatables.net/1.10.21/css/jquery.dataTables.min.css',
            [],
            null
        );
        wp_localize_script( 'datatables', 'twgSap', [
            'nonce' => wp_create_nonce( 'sap_sync_order_nonce' ),
        ] );
    }
    
    // Hook into the admin area to add custom columns   
    public function add_updated_time_column( $columns ) {
        $columns['updated_time'] = __( 'Updated Time', 'twg-sap-connection' );
        return $columns;
    }

    public function render_updated_time_column( $column, $post_id ) {
        if ( $column !== 'updated_time' ) {
            return;
        }

        $modified = get_post_modified_time( 'Y-m-d H:i', false, $post_id );
        echo esc_html( $modified );
    }

    public function init_admin_columns(): void {
        add_filter('manage_edit-product_columns', [$this, 'add_updated_time_column']);
        add_action('manage_product_posts_custom_column', [$this, 'render_updated_time_column'], 10, 2);

        // Make the updated_time column sortable
        add_filter('manage_edit-product_sortable_columns', function($sortable_columns) {
            $sortable_columns['updated_time'] = 'updated_time';
            return $sortable_columns;
        });

        // Handle the sorting logic for the updated_time column
        add_action('pre_get_posts', function($query) {
            if (!is_admin() || !$query->is_main_query()) {
                return;
            }

            if ('updated_time' === $query->get('orderby')) {
                $query->set('orderby', 'modified');
            }
        });
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

        // add_submenu_page(
        //     'sap-connection-settings',
        //     'Full Product Sync',
        //     'Full Product Sync',
        //     'manage_options',
        //     'sap-full-sync',
        //     [ $this, 'render_full_sync_page' ]
        // );

        add_submenu_page(
            'sap-connection-settings',
            'Delete Products',
            'Delete Products',
            'manage_options',
            'sap-delete-products',
            [ $this, 'render_delete_products_page' ]
        );
        add_submenu_page(
            'sap-connection-settings',
            'History Logs',
            'History Logs',
            'manage_options',
            'sap-history-logs',
            [ $this, 'render_logs_page' ]
        );
        add_submenu_page(
            'sap-connection-settings',
            'Order Sync',
            'Order Sync',
            'manage_options',
            'sap-order-sync',
            [ $this, 'render_sync_order_page' ]
        );
    }

    public function register_settings(): void { 
        register_setting( 'sap_connection_settings_group', 'sap_database' );
        register_setting( 'sap_connection_settings_group', 'sap_username' );
        register_setting( 'sap_connection_settings_group', 'sap_password' );
        register_setting( 'sap_connection_settings_group', 'sap_host' );
        register_setting( 'sap_connection_settings_group', 'sap_host_path' );
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
            'sap_host_path',
            'Host Path',
            [ $this, 'render_host_path_field' ],
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
            <h1><?php esc_html_e( 'SAP Connection Settings', 'twg-sap-connection' ); ?></h1>
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
    public function render_host_path_field(): void {
        $value = get_option( 'sap_host_path', '' );
        printf(
            '<input type="text" name="sap_host_path" value="%s" />',
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
    public function render_full_sync_page(){
        // Render the Full Sync page content here.
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
         if ( isset( $_POST['twg_full_sync'] ) && check_admin_referer( 'twg_full_sync_action', 'twg_full_sync_nonce' ) ) {
            
            echo '<div id="sync-log" style="background: #f9f9f9; padding: 10px; border: 1px solid #ddd; height: 300px; overflow-y: auto;">';
            //$this->log_and_execute('TwgSapConnection\\Admin\\Product', 'sap_sync_products', 'Manual Run completed.'); //sap_sync_products
            $this->log_and_execute('TwgSapConnection\\Admin\\Common', 'cron_job_function_one_am', 'Manual Run completed.'); //sap_sync_products
            echo '</div>';
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Full Product Sync', 'twg-sap-connection' ); ?></h1>
            <form method="post">
                <?php wp_nonce_field( 'twg_full_sync_action', 'twg_full_sync_nonce' ); ?>
                <p>
                    <input type="submit" name="twg_full_sync" class="button button-primary" value="<?php esc_attr_e( 'Run Full Product Sync', 'twg-unleashed-connection' ); ?>">
                </p>
            </form>
        </div>
        <?php
    }

        private function log_and_execute($class_name, $function_name, $success_message) {
        if ( class_exists( $class_name ) && method_exists( $class_name, $function_name ) ) {
            $instance = new $class_name(); // Create an instance of the class
            $result = call_user_func( [ $instance, $function_name ] ); // Call the method on the instance
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
    
    public function render_sync_order_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
    //     $args = [
    //     'limit' => 100, // batch size
    //     'status' => array_keys(wc_get_order_statuses()),
    //     'paginate' => true,
    //     'page' => 1,
    // ];

    // do {
    //     $orders = wc_get_orders($args);

    //     foreach ($orders->orders as $order) {
    //         update_post_meta($order->get_id(), '_sap_sync_status', true);
    //     }

    //     $args['page']++;

    // } while ($args['page'] <= $orders->max_num_pages);
        echo '<div class="wrap"><h1>' . esc_html__( 'Single Product Sync', 'sap-connection-settings' ) . '</h1>';
        echo '</div>';
        $args = array(
            'limit'     => -1,                              // Number of orders to return (use -1 for all, but be cautious with performance)
              'orderby' => 'ID',
            'order'   => 'DESC',                       // Sort descending
        );
        $orders = wc_get_orders( $args );
        
        echo '<table id="sap-single-prod-table" class="display">';
            echo '<thead>';
                echo '<tr>';
                    echo '<th>'. esc_html__( 'Order ID', 'sap-connection-settings' ).'</th>';
                    echo '<th>'. esc_html__( 'SAP Sync Status', 'sap-connection-settings' ).'</th>';
                    echo '<th>'. esc_html__( 'Total', 'sap-connection-settings' ).'</th>';
                    echo '<th>'. esc_html__( 'Order', 'sap-connection-settings' ).'</th>';
                    echo '<th>'. esc_html__( 'Sync', 'sap-connection-settings' ).'</th>';
                    echo '<th>'. esc_html__( 'Date', 'sap-connection-settings' ).'</th>';
                echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
                foreach ( $orders as $order ) {
                    $all_meta_data = $order->get_meta_data();
                    $sync_status = get_post_meta( $order->get_id(), '_sap_sync_status', true );
                    $status = 'Not SYNC';
                    if($sync_status){
                        $status = 'SYNC';
                    }
                    echo '<tr>';
                        echo '<td>#'. esc_html( $order->get_id() ).'</td>';
                        echo '<td>'. esc_html( $status ).'</td>';
                        echo '<td>$'. esc_html( $order->total ).'</td>';
                        echo '<td><a class="button" href="' . esc_url( admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ) ) . '">View Order</a></td>';
                        if(!$sync_status){
                            echo '<td><button type="button" class="button sync-button" data-order-id="'.$order->get_id() .'">Sync</button></td>';
                        }else{
                            echo '<td>Successfully SYNC</td>';
                            //echo '<td><button type="button" class="button sync-button" data-order-id="'.$order->get_id() .'">Sync</button></td>';
                        }    
                        echo '<td>'. esc_html( $order->get_date_created()->date_i18n( wc_date_format() )).'</td>';
                    echo '</tr>';
                }
            echo '</tbody>';
        echo '</table>';
        echo '<div id="sap-sync-result"></div>';
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#sap-single-prod-table').DataTable({
                     order: [[0, 'desc']]
                });
                
                $('#sap-single-prod-table').on('click', '.sync-button', function(e) {
                    e.preventDefault();
                    var orderId = $(this).data('order-id'); // FIXED variable name
                    var btn = $(this);
                    btn.prop('disabled', true).text('Syncing...');
                    console.log('Starting sync for product ID:', orderId);
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'sap_sync_single_order',
                            order_id: orderId,
                            nonce: twgSap.nonce
                        },
            
                        success: function(response) {
                            location.reload();
                            btn.prop('disabled', false).text('Sync');
                            $('#twg-sync-result').html('<pre>' + response.data + '</pre>');
                        },
            
                        error: function() {
                            location.reload();
                            btn.prop('disabled', false).text('Sync');
                            $('#twg-sync-result').html('<div class="notice notice-error">AJAX error occurred.</div>');
                        }
                    });
                });
            });
            </script>
            <?php
                if(isset($_POST['start_single_prod_sync']) && $_POST['start_single_prod_sync'] == 'Start Single Product Sync') {
                    // Here you would implement the logic to sync a single product with MYOB API
                    // For demonstration, we'll just show a success message
                    echo '<div class="notice notice-success"><p>' . esc_html__( 'Single order sync completed successfully.', 'sap-connection-settings' ) . '</p></div>';
                }
    }  
    
    public function maybe_bootstrap_order_sync($order_id): void {

        $order = new Order();
  
        $order_data = $order->sync_single_order( $order_id );
        Common::add_log("Order Data Prepared", json_encode($order_data));  


    }
    
    public function wp_ajax_sap_sync_single_order() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        check_ajax_referer( 'sap_sync_order_nonce', 'nonce' );

        $order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;

        if ( ! $order_id ) {
            wp_send_json_error( 'Invalid order ID' );
        }
        
        
        
        $this->maybe_bootstrap_order_sync($order_id);

        wp_send_json_success( 
            array( 'success' => true )
            );
        
    }
    
    public function ajax_get_logs() {

        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error('Unauthorized');
        }
    
        $file = isset($_POST['file']) ? sanitize_text_field($_POST['file']) : '';
    
        if (empty($file)) {
            wp_send_json_error('No file selected');
        }
    
        $logs = Common::get_logs_from_json($file);
    
        wp_send_json_success($logs);
    }
    
    public function render_logs_page(): void {
       // Render the Logs page content here.
        $logs_dir = ABSPATH . 'sap_sync_logs/';
        
        $log_files = [];
        if ( is_dir( $logs_dir ) ) {
            $sap_logs = preg_grep('/^sap_logs_.*\.log$/', scandir($logs_dir));
            
            foreach ( $sap_logs as $file ) {
                if ( strpos( $file, 'sap_logs_' ) === 0 && substr( $file, -4 ) === '.log' ) {
                    $log_files[] = $file;
                }
            }
        }

        $selected_file = isset( $_POST['twg_render_logs_file'] ) ? sanitize_text_field( $_POST['twg_render_logs_file'] ) : 'sap_logs_' . date('Y_m_d') . '.log';
        $logs = [];

        if ( $selected_file && in_array( $selected_file, $log_files ) ) {
            $logs = Common::get_logs_from_json( $selected_file );

        }
        ?>
        <div class="wrap">
        <h1>History Logs</h1>
        <form id="log-form" style="margin-bottom:20px;">
            <label for="log_file">Select log file:</label>
            <select name="log_file" id="log_file">
                <?php foreach ( $log_files as $file ): ?>
                    <option value="<?php echo esc_attr( $file ); ?>" <?php selected( $selected_file, $file ); ?>>
                        <?php echo esc_html( $file ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="submit" class="button" value="View Logs">
        </form>
        <div id="log-output" style="
            background: #111;
            color: #0f0;
            font-family: 'Courier New', Courier, monospace;
            padding: 20px;
            border-radius: 6px;
            min-height: 300px;
            max-height: 600px;
            overflow-y: auto;
            box-shadow: 0 0 10px #222;
        ">
            <?php
            if ( ! empty( $logs ) ) {
                foreach ( $logs as $log ) {
                    echo esc_html( $log ) . "<br>";
                }
            } else {
                echo '<span style="color:#888;">No logs found.</span>';
            }
            ?>
        </div>
        </div>
        <script>
            jQuery(document).ready(function($){
            
                $('#log-form').on('submit', function(e){
                    e.preventDefault();
            
                    let file = $('#log_file').val();
            
                    $('#log-output').html('<span style="color:#888;">Loading...</span>');
            
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'twg_get_logs',
                            file: file
                        },
                        success: function(response){
            
                            if(response.success){
            
                                let logs = response.data;
                                let html = '';
            
                                if(logs.length > 0){
                                    logs.forEach(function(line){
                                        html += line + '<br>';
                                    });
                                } else {
                                    html = '<span style="color:#888;">No logs found.</span>';
                                }
            
                                $('#log-output').html(html);
            
                            } else {
                                $('#log-output').html('<span style="color:red;">Error loading logs</span>');
                            }
            
                        }
                    });
            
                });
            
            });
            </script>
        <?php
    }
}