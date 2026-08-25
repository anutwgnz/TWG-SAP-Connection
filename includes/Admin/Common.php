<?php

namespace TwgSapConnection\Admin;
use TwgSapConnection\Api\Client;

// Ensure the Product class is imported
use TwgSapConnection\Admin\Product;


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Common {
       /**
     * Example: Get a setting value by key
     */
    public static function get_setting( $key ) {
        return get_option( $key );
    }

    /**
     * Example: Set a setting value by key
     */
    public static function set_setting( $key, $value ) {
        return update_option( $key, $value );
    }


    /**
     * Example: Log an admin action
     */
    public static function add_log( string $type, string $message ): void {
        $upload_dir = ABSPATH;
        $logDir = trailingslashit( $upload_dir ) . 'sap_sync_logs/';
        $file = $logDir . 'sap_logs_' . date('Y_m_d') . '.log';
        $time = date('H:i:s.');
        $log = "[$time] $type > $message\n";
        file_put_contents($file, $log, LOCK_EX | FILE_APPEND);
    }
    
     /**
     * Example: Write data to a JSON file
     */
    public static function write_json(string $filename, array $data) {
        $upload_dir = wp_upload_dir();
        $location = trailingslashit( $upload_dir['basedir'] ) . 'SAP_Connection';

        // Ensure the directory exists
        if (!file_exists($location)) {
            if (!mkdir($location, 0755, true) && !is_dir($location)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $location));
            }
        }

        $filepath = $location .'/'. $filename;
        try {
            // Encode data to JSON
            $json_data = json_encode($data, JSON_THROW_ON_ERROR);

            // Write JSON string to file
            file_put_contents($filepath, $json_data, LOCK_EX);

            // Verify the file was created
            if (!file_exists($filepath)) {
                throw new \RuntimeException(sprintf('File "%s" was not created', $filepath));
            }
        } catch (\JsonException $e) {
            error_log("TWG SAP: JSON encoding error: " . $e->getMessage());
            return false;
        } catch (\ErrorException $e) {
            error_log("TWG SAP: File writing error: " . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            error_log("TWG SAP: write_json error: " . $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Example: Read data from a JSON file
     */
    public static function read_json(string $filename){
        $upload_dir = wp_upload_dir();
        $location = trailingslashit( $upload_dir['basedir'] ) . 'SAP_Connection';
        
        $filename = $location .'/'. $filename;
        try {
            // Attempt to read the file content
            $jsonString = file_get_contents($filename);
        
            // Check if file_get_contents failed (e.g., file not found or permission issue)
            if ($jsonString === false) {
                throw new \Exception("Failed to read file: {$filename}");
            }
        
            // Attempt to decode the JSON string with error throwing enabled
            $data = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
        
            return $data;

        } catch (\JsonException $e) {
            error_log("TWG SAP: JSON decoding error: " . $e->getMessage());
        } catch (\Exception $e) {
            error_log("TWG SAP: read_json error: " . $e->getMessage());
        }
    }

    /**
     * Full sync    
     */

    public static function full_sync() {
        $product = new Product();
        return $product->login();
    }
  
    /**
     * Save log entry to JSON file
     */
    public static function read_logs(string $filename){
        // $upload_dir = ABSPATH;
        // $location = trailingslashit( $upload_dir ) . 'sap_sync_logs/';
        $location = ABSPATH . 'sap_sync_logs/';
         // Ensure the directory exists
        $filepath = $location . $filename;
        if (!file_exists($filepath)) {
            return [];
        }
        // Read file lines into array, remove empty lines
        $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return $lines ?: [];
    }

    public static function get_logs_from_json($filename) {
        return self::read_logs( $filename );
    }
    
    
    
    public static function cron_job_function(){
        Common::add_log('Debug', 'Cron job function started.');
        try {
            $product = new Product();
            Common::add_log('Info', 'Product instance created successfully.');
            $meta = $product->fetch_meta_sap();
            Common::add_log('Info', 'Meta data fetched from SAP successfully.');
            $products = $product->fetch_products();
            Common::add_log('Info', 'Products data fetched from SAP successfully.');
        } catch (\Exception $e) {
            Common::add_log('Error', 'Cron job failed: ' . $e->getMessage());
        }
        Common::add_log('Debug', 'Cron job function ended.');
    }
    
    public static function cron_job_function_one_am(){
        Common::add_log('Debug', '1 AM Cron job function started.');
        try {
            Common::cron_job_function_scheduled_sync();
            Common::add_log('Info', 'Products synced to DB successfully.');
        } catch (\Exception $e) {
            Common::add_log('Error', '1 AM Cron job failed: ' . $e->getMessage());
        }
        Common::add_log('Debug', '1 AM Cron job function ended.');
    }

    /**
     * Production scheduled download: SAP metadata and products to JSON.
     *
     * @return array{success: bool, sap_count: int|null, json_count: int, mismatch: bool}
     */
    public static function cron_job_function_scheduled_download(): array {
        Common::add_log( 'Cron-Job', 'Scheduled download started.' );

        $default_result = [
            'success'    => false,
            'sap_count'  => null,
            'json_count' => 0,
            'mismatch'   => false,
        ];

        try {
            Product::fetch_meta_sap();
            Common::add_log( 'Cron-Job', 'Meta data fetched from SAP successfully.' );

            $fetch_result = Product::fetch_products();

            if ( ! $fetch_result['success'] ) {
                Common::add_log( 'Cron-Job', 'Scheduled download failed during product fetch.' );
                return array_merge( $default_result, $fetch_result );
            }

            Common::add_log(
                'Cron-Job',
                sprintf(
                    'Scheduled download complete. SAP count: %s, JSON count: %d, mismatch: %s',
                    null === $fetch_result['sap_count'] ? 'unknown' : (string) $fetch_result['sap_count'],
                    $fetch_result['json_count'],
                    $fetch_result['mismatch'] ? 'yes' : 'no'
                )
            );

            return [
                'success'    => true,
                'sap_count'  => $fetch_result['sap_count'],
                'json_count' => $fetch_result['json_count'],
                'mismatch'   => $fetch_result['mismatch'],
            ];
        } catch ( \Exception $e ) {
            Common::add_log( 'Cron-Job', 'Scheduled download failed: ' . $e->getMessage() );
            return $default_result;
        }
    }

    /**
     * Production scheduled sync: JSON → WooCommerce + draft orphans.
     *
     * @return array{success: bool, json_count: int, drafted_count: int, message: string}
     */
    public static function cron_job_function_scheduled_sync(): array {
        Common::add_log( 'Cron-Job', 'Scheduled sync started.' );

        $default_result = [
            'success'       => false,
            'json_count'    => 0,
            'drafted_count' => 0,
            'message'       => 'Scheduled sync failed.',
        ];

        try {
            $json_skus = Product::get_json_skus();

            if ( empty( $json_skus ) ) {
                Common::add_log( 'Cron-Job', 'Scheduled sync skipped: product JSON is empty.' );
                return array_merge(
                    $default_result,
                    [
                        'message' => 'Scheduled sync skipped: product JSON is empty. Run download first.',
                    ]
                );
            }

            $json_count = count( $json_skus );
            Common::add_log( 'Cron-Job', 'JSON SKU snapshot count: ' . $json_count );

            Product::sap_sync_products();

            $reconcile = Product::reconcile_products_not_in_json( $json_skus, false );

            Common::add_log(
                'Cron-Job',
                sprintf(
                    'Scheduled sync complete. JSON SKUs: %d, drafted orphans: %d',
                    $json_count,
                    $reconcile['count']
                )
            );

            return [
                'success'       => true,
                'json_count'    => $json_count,
                'drafted_count' => $reconcile['count'],
                'message'       => 'Scheduled sync completed successfully.',
            ];
        } catch ( \Exception $e ) {
            Common::add_log( 'Cron-Job', 'Scheduled sync failed: ' . $e->getMessage() );
            return array_merge(
                $default_result,
                [
                    'message' => 'Scheduled sync failed: ' . $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Dry-run SAP download: fetch metadata and products to JSON only (no WooCommerce sync).
     *
     * @return array{success: bool, sap_count: int|null, json_count: int, mismatch: bool}
     */
    public static function cron_job_function_dry_run_download(): array {
        Common::add_log( 'Dry-Run', 'Download job started.' );

        $default_result = [
            'success'    => false,
            'sap_count'  => null,
            'json_count' => 0,
            'mismatch'   => false,
        ];

        try {
            Product::fetch_meta_sap();
            Common::add_log( 'Dry-Run', 'Meta data fetched from SAP successfully.' );

            $fetch_result = Product::fetch_products();

            if ( ! $fetch_result['success'] ) {
                Common::add_log( 'Dry-Run', 'Products data fetch failed.' );
                return array_merge( $default_result, $fetch_result );
            }

            Common::add_log(
                'Dry-Run',
                sprintf(
                    'Products fetched. SAP count: %s, JSON count: %d, mismatch: %s',
                    null === $fetch_result['sap_count'] ? 'unknown' : (string) $fetch_result['sap_count'],
                    $fetch_result['json_count'],
                    $fetch_result['mismatch'] ? 'yes' : 'no'
                )
            );
            Common::add_log( 'Dry-Run', 'Download job completed.' );

            return [
                'success'    => true,
                'sap_count'  => $fetch_result['sap_count'],
                'json_count' => $fetch_result['json_count'],
                'mismatch'   => $fetch_result['mismatch'],
            ];
        } catch ( \Exception $e ) {
            Common::add_log( 'Dry-Run', 'Download job failed: ' . $e->getMessage() );
            return $default_result;
        }
    }
}