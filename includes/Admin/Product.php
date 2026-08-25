<?php

namespace TwgSapConnection\Admin;
use TwgSapConnection\Admin\Common;
use TwgSapConnection\Api\Client;
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Product {
    private $client;

    public function __construct() {
        $this->client = new Client();
    }

    public static function get_product_codes_filename(): string {
        return Common::get_setting( 'product_codes_json_url' ) ?: 'product_codes.json';
    }

    public static function get_product_brand_filename(): string {
        return Common::get_setting( 'product_brand_json_url' ) ?: 'product_brands.json';
    }

    public static function get_product_type_filename(): string {
        return Common::get_setting( 'product_type_json_url' ) ?: 'product_types.json';
    }

    public static function get_product_sector_filename(): string {
        return Common::get_setting( 'product_sector_json_url' ) ?: 'product_sectors.json';
    }

    /**
     * OData filter used for web-available SAP items.
     */
    public static function get_sap_items_filter(): string {
        return urlencode( "U_PRX_WbAv eq 'Y' and Valid eq 'tYES'" );
    }

    /**
     * Resolve WooCommerce post status from SAP product flags.
     *
     * @param array<string, mixed> $product
     */
    public static function resolve_status_from_sap( array $product ): string {
        $pub_flg = $product['U_Pub_Flg'] ?? '';
        $wb_av   = $product['U_PRX_WbAv'] ?? '';

        if ( $pub_flg === 'Y' && $wb_av === 'Y' ) {
            return 'publish';
        }

        return 'private';
    }

    /**
     * Build a list of ItemCode values from the current product_codes JSON file.
     *
     * @return string[]
     */
    public static function get_json_skus(): array {
        $product_json = Common::read_json( self::get_product_codes_filename() );

        if ( ! is_array( $product_json ) ) {
            return [];
        }

        $skus = [];

        foreach ( $product_json as $product ) {
            if ( is_array( $product ) && ! empty( $product['ItemCode'] ) ) {
                $skus[] = trim( (string) $product['ItemCode'] );
            } elseif ( is_object( $product ) && ! empty( $product->ItemCode ) ) {
                $skus[] = trim( (string) $product->ItemCode );
            }
        }

        return array_values( array_unique( array_filter( $skus ) ) );
    }

    /**
     * Fetch SAP item count for the web-available filter (read-only).
     */
    public static function get_sap_product_count(): ?int {
        try {
            $client      = new Client();
            $filterby    = self::get_sap_items_filter();
            $num_products = $client->get_sap_data( 'Items/$count', '', '$filter=' . $filterby );
            $count_array = json_decode( json_encode( $num_products ), true );

            if ( ! isset( $count_array[0] ) ) {
                return null;
            }

            return (int) $count_array[0];
        } catch ( \Exception $e ) {
            Common::add_log( 'Audit', 'Failed to fetch SAP product count: ' . $e->getMessage() );
            return null;
        }
    }

    /**
     * Compare WooCommerce SKUs against JSON authority list.
     *
     * @return array{orphans: array<int, array<string, mixed>>, woo_sku_count: int}
     */
    public static function get_woo_orphans( ?array $json_skus = null, int $limit = 0 ): array {
        if ( null === $json_skus ) {
            $json_skus = self::get_json_skus();
        }

        $json_map = array_fill_keys( $json_skus, true );
        $orphans  = [];
        $woo_skus = 0;

        if ( ! function_exists( 'wc_get_products' ) ) {
            return [
                'orphans'       => [],
                'woo_sku_count' => 0,
            ];
        }

        $product_ids = wc_get_products(
            [
                'limit'  => -1,
                'return' => 'ids',
                'status' => [ 'publish', 'private', 'draft', 'pending' ],
            ]
        );

        foreach ( $product_ids as $product_id ) {
            $wc_product = wc_get_product( $product_id );

            if ( ! $wc_product ) {
                continue;
            }

            $sku = trim( $wc_product->get_sku() );

            if ( $sku === '' ) {
                continue;
            }

            ++$woo_skus;

            if ( isset( $json_map[ $sku ] ) ) {
                continue;
            }

            $orphans[] = [
                'product_id'     => (int) $product_id,
                'sku'            => $sku,
                'name'           => $wc_product->get_name(),
                'current_status' => $wc_product->get_status(),
                'would_become'   => 'draft',
            ];

            if ( $limit > 0 && count( $orphans ) >= $limit ) {
                break;
            }
        }

        return [
            'orphans'       => $orphans,
            'woo_sku_count' => $woo_skus,
        ];
    }

    /**
     * Build a read-only audit report comparing JSON and WooCommerce products.
     *
     * @return array<string, mixed>
     */
    public static function build_audit_report( bool $compare_sap = false, int $orphan_limit = 0 ): array {
        $json_skus  = self::get_json_skus();
        $json_count = count( $json_skus );

        $orphan_data   = self::get_woo_orphans( $json_skus, $orphan_limit );
        $orphans       = $orphan_data['orphans'];
        $woo_sku_count = $orphan_data['woo_sku_count'];

        $woo_skus_present = [];
        $product_ids      = function_exists( 'wc_get_products' )
            ? wc_get_products( [ 'limit' => -1, 'return' => 'ids', 'status' => [ 'publish', 'private', 'draft', 'pending' ] ] )
            : [];

        foreach ( $product_ids as $product_id ) {
            $wc_product = wc_get_product( $product_id );

            if ( ! $wc_product ) {
                continue;
            }

            $sku = trim( $wc_product->get_sku() );

            if ( $sku !== '' ) {
                $woo_skus_present[] = $sku;
            }
        }

        $woo_skus_present = array_values( array_unique( $woo_skus_present ) );
        $json_only_skus     = array_values( array_diff( $json_skus, $woo_skus_present ) );

        $report = [
            'generated_at'     => current_time( 'mysql' ),
            'json_file'        => self::get_product_codes_filename(),
            'json_count'       => $json_count,
            'woo_sku_count'    => $woo_sku_count,
            'orphan_count'     => count( self::get_woo_orphans( $json_skus, 0 )['orphans'] ),
            'json_only_count'  => count( $json_only_skus ),
            'orphans'          => $orphans,
            'json_only_skus'   => $json_only_skus,
            'sap_count'        => null,
            'sap_json_mismatch'=> null,
        ];

        if ( $compare_sap ) {
            $sap_count                    = self::get_sap_product_count();
            $report['sap_count']          = $sap_count;
            $report['sap_json_mismatch']  = ( null !== $sap_count && $sap_count !== $json_count );
        }

        return $report;
    }

    /**
     * Draft WooCommerce products whose SKU is missing from JSON.
     *
     * @param string[]|null $json_skus
     * @return array<string, mixed>
     */
    public static function reconcile_products_not_in_json( ?array $json_skus = null, bool $dry_run = true ): array {
        if ( null === $json_skus ) {
            $json_skus = self::get_json_skus();
        }

        $json_map = array_fill_keys( $json_skus, true );
        $products = [];
        $checked  = 0;

        if ( ! function_exists( 'wc_get_products' ) ) {
            return [
                'dry_run'  => $dry_run,
                'checked'  => 0,
                'count'    => 0,
                'products' => [],
            ];
        }

        $product_ids = wc_get_products(
            [
                'limit'  => -1,
                'return' => 'ids',
                'status' => [ 'publish', 'private', 'draft', 'pending' ],
            ]
        );

        foreach ( $product_ids as $product_id ) {
            $wc_product = wc_get_product( $product_id );

            if ( ! $wc_product ) {
                continue;
            }

            $sku = trim( $wc_product->get_sku() );

            if ( $sku === '' ) {
                continue;
            }

            ++$checked;

            if ( isset( $json_map[ $sku ] ) ) {
                continue;
            }

            $entry = [
                'product_id'     => (int) $product_id,
                'sku'            => $sku,
                'name'           => $wc_product->get_name(),
                'current_status' => $wc_product->get_status(),
                'would_become'   => 'draft',
                'applied'        => false,
            ];

            if ( ! $dry_run ) {
                wp_update_post(
                    [
                        'ID'          => $product_id,
                        'post_status' => 'draft',
                    ]
                );
                Common::add_log(
                    'Woo-Product-Reconcile',
                    sprintf( 'Drafted SKU %s (product ID %d) - not in JSON.', $sku, $product_id )
                );
                $entry['applied'] = true;
            }

            $products[] = $entry;
        }

        return [
            'dry_run'  => $dry_run,
            'checked'  => $checked,
            'count'    => count( $products ),
            'products' => $products,
        ];
    }

    /**
     * Find a product in JSON by SKU for preview.
     *
     * @return array<string, mixed>|null
     */
    public static function get_json_product_by_sku( string $sku ): ?array {
        $sku          = trim( $sku );
        $product_json = Common::read_json( self::get_product_codes_filename() );

        if ( ! is_array( $product_json ) ) {
            return null;
        }

        foreach ( $product_json as $product ) {
            if ( is_array( $product ) && trim( (string) ( $product['ItemCode'] ?? '' ) ) === $sku ) {
                return $product;
            }
            if ( is_object( $product ) && trim( (string) ( $product->ItemCode ?? '' ) ) === $sku ) {
                return (array) $product;
            }
        }

        return null;
    }

    /**
     * Fetch 
     * Type, Brand, Group
     * From SAP
     **/ 
    public static function fetch_meta_sap(){
        $client = new Client();
        
        // All Types Brands    
        $brandList = $client->sap_sql_query('AllBrands'); //AllBrands == Brands
        $brandListDatas = json_decode($brandList);
        
        
        $brandLists = $brandListDatas->value;
        $keyed_brand_list = array();
        foreach ($brandLists as $blist) {
            $keyed_brand_list[$blist->Code] = array('name'=>$blist->Name,'content'=>$blist->U_Content);
        }
        
        // Write JSON with only codes
        Common::write_json( self::get_product_brand_filename(), $keyed_brand_list );
        
        // All Types Listing
        $typeList = $client->sap_sql_query('AllItemGroups'); // AllItemGroups == types
        $typeListDatas = json_decode($typeList);
        $typeLists = $typeListDatas->value;
        $keyed_type_list = array();
        foreach ($typeLists as $tlist) {
            $keyed_type_list[$tlist->Code] = array('name'=>$tlist->Name,'content'=>$tlist->U_Content);
        }
        
        // Write JSON with only codes
        Common::write_json( self::get_product_type_filename(), $keyed_type_list );
        
        // All Sector Listing
        $sectorList = $client->sap_sql_query('AllItemProductTypes'); //AllItemProductTypes == sectors
        $sectorListDatas = json_decode($sectorList);
        $sectorLists = $sectorListDatas->value;
        $keyed_sector_list = array();
        $sector_codes = array();
        foreach ($sectorLists as $slist) {
            $keyed_sector_list[$slist->Code] = array('name'=>$slist->Name,'content'=>$slist->U_Content);
            $sector_codes[] = 'U_' . $slist->Code;
        }
        
        // Write JSON with only codes
        Common::write_json( self::get_product_sector_filename(), $sector_codes );
        
        //Update Taxonomies
        // self::update_taxonomy('brand', $keyed_brand_list);
        // self::update_taxonomy('product-type', $keyed_type_list);
        // self::update_taxonomy('sector', $keyed_sector_list);
    } 
    
    
    /**
     * Fetch products from SAP Business One.
     *
     * @return array{success: bool, sap_count: int|null, json_count: int, mismatch: bool, pages_fetched: int}
     */
    public static function fetch_products() {
        $sector_json = Common::read_json( self::get_product_sector_filename() );

        if ( empty( $sector_json ) || ! is_array( $sector_json ) ) {
            Common::add_log( 'Error', 'Sector JSON is empty or unreadable. Run fetch_meta_sap() first.' );
            return [
                'success'        => false,
                'sap_count'      => null,
                'json_count'     => 0,
                'mismatch'       => false,
                'pages_fetched'  => 0,
            ];
        }

        $client     = new Client();
        $prod_arr   = [];
        $int_skip   = 0;
        $int_per_page = 20;
        $filterby   = self::get_sap_items_filter();
        $sap_count  = null;
        $pages_fetched = 0;

        try {
            $num_products     = $client->get_sap_data( 'Items/$count', '', '$filter=' . $filterby );
            $num_products_arr = json_decode( json_encode( $num_products ), true );
            $sap_count        = isset( $num_products_arr[0] ) ? (int) $num_products_arr[0] : null;

            while ( true ) {
                $query = '$select=ItemCode,ItemName,U_PRX_Desc,Picture,Valid,U_Variant,U_BRAND,U_ITMPRGRP,U_Pub_Flg,U_PRX_WbAv,Valid,U_PRX_FFlg,'
                    . implode( ',', $sector_json )
                    . '&$filter=' . $filterby
                    . '&$top=' . $int_per_page
                    . '&$skip=' . $int_skip;

                try {
                    $products    = $client->get_sap_data( 'Items', '', $query );
                    $arr_products = (array) ( $products->value ?? [] );
                } catch ( \Exception $e ) {
                    Common::add_log( 'Error', 'Failed to fetch SAP products page at skip ' . $int_skip . ': ' . $e->getMessage() );
                    break;
                }

                if ( count( $arr_products ) === 0 ) {
                    break;
                }

                $arr_products_filtered = array_filter(
                    $arr_products,
                    function ( $arr_item ) {
                        return ( $arr_item->U_PRX_WbAv === 'Y' && $arr_item->Valid === 'tYES' );
                    }
                );

                $prod_arr = array_merge( $prod_arr, $arr_products_filtered );
                ++$pages_fetched;

                if ( count( $arr_products ) < $int_per_page ) {
                    break;
                }

                $int_skip += $int_per_page;
            }

            Common::write_json( self::get_product_codes_filename(), $prod_arr );

            $json_count = count( $prod_arr );
            $mismatch   = ( null !== $sap_count && $sap_count !== $json_count );

            Common::add_log(
                'Info',
                sprintf(
                    'Product fetch complete. SAP count: %s, JSON saved: %d, pages: %d, mismatch: %s',
                    null === $sap_count ? 'unknown' : (string) $sap_count,
                    $json_count,
                    $pages_fetched,
                    $mismatch ? 'yes' : 'no'
                )
            );

            return [
                'success'       => true,
                'sap_count'     => $sap_count,
                'json_count'    => $json_count,
                'mismatch'      => $mismatch,
                'pages_fetched' => $pages_fetched,
            ];
        } catch ( \Exception $e ) {
            Common::add_log( 'Error', 'Failed to login to SAP: ' . $e->getMessage() );
            return [
                'success'       => false,
                'sap_count'     => $sap_count,
                'json_count'    => count( $prod_arr ),
                'mismatch'      => false,
                'pages_fetched' => $pages_fetched,
            ];
        }
    }
    
    /**
     * Update products from SAP Business One
     */
    
    public static function sap_sync_products() {

        $product_file = self::get_product_codes_filename();
        $brandList_file = self::get_product_brand_filename();
        $typeList_file = self::get_product_type_filename();
        $sectorList_file = self::get_product_sector_filename();
        $ils_live_baseurl = 'https://apps.ils.co.nz:7020/';
        $brandList= Common::read_json($brandList_file);
        $typeList= Common::read_json($typeList_file);   
        $sectorList= Common::read_json($sectorList_file);

        $defaultPrice = 0; // Define a default price
        $x = 1;
        while (true) {
            // Read the JSON file
            $product_json = Common::read_json($product_file);
            Common::add_log('Woo-Product', 'Loop: ' . $x);
            // If no products are left, break the loop
            if (empty($product_json)) {
                Common::add_log('Woo-Product-Error', 'Json empty');
                break;
            }

            // Process products in chunks
            while (!empty($product_json)) {
                $chunk = array_splice($product_json, 0, 100);

                foreach ($chunk as $product) {

                            // Check if the product exists
                            $product_id = wc_get_product_id_by_sku($product['ItemCode']);
                            Common::add_log('Woo-Product', 'Product SKU: ' . $product['ItemCode'] . ' | Product ID: ' . $product_id);
                            
                            
                            $sku = trim($product['ItemCode']);
                            $title = trim($product['ItemName']);
                            $description = $product['ProductDescription'] ?? '';
                            $img = isset($product['Picture']) ? $ils_live_baseurl . 'images/thumbnail.ashx?size=300&image=parts/' . $product['Picture'] : plugins_url('assets/public/images/woocommerce-placeholder.png.webp', TWG_PLUGIN_NAME_FILE);
                            $productBrandId = $product['U_BRAND'];
                            $productTypeId = $product['U_ITMPRGRP'];
                            $productFeatured = $product['U_PRX_FFlg'];
                        
                            if ($product_id) {
                                $wcProduct = wc_get_product($product_id);
                            } else {
                                if (!class_exists('WC_Product_Simple')) {
                                    Common::add_log('Woo', 'WooCommerce is not active or the WC_Product_Simple class is not available for product code: ' . $sku);
                                    continue;
                                }
                                $wcProduct = new \WC_Product_Simple();
                            }
        
                            $wcProduct->set_name($title);
                            $wcProduct->set_description($description);
                            $wcProduct->set_sku($sku);
                            $wcProduct->set_regular_price($defaultPrice);
                            
                            /**
                             * Featured Products
                             **/
                            $wcProduct->set_featured(0);  
                            if($productFeatured == 'Y'){
                                $wcProduct->set_featured(1); 
                            }
                        
                        
                            $product_id = $wcProduct->save();
        
                            $wcProduct->update_meta_data('_groupId', $product['U_Variant'] ?? '');
                            $wcProduct->update_meta_data('_isInternalOnly', $product['U_Pub_Flg'] ?? '');
                            $wcProduct->update_meta_data('_sapImage', $img);
        
                            $status = self::resolve_status_from_sap( $product );
                            $previous_status = get_post_status( $product_id );

                            if ( $previous_status && $previous_status !== $status ) {
                                Common::add_log(
                                    'Woo-Product',
                                    sprintf(
                                        'SKU %s status changed from %s to %s',
                                        $sku,
                                        $previous_status,
                                        $status
                                    )
                                );
                            }

                            wp_update_post(['ID' => $product_id, 'post_status' => $status]);
        
                            wp_set_object_terms($product_id, [], 'brand');
                            wp_set_object_terms($product_id, [], 'product-type');
                            wp_set_object_terms($product_id, [], 'sector');
        
                            $sectorIds = [];
                            foreach ($sectorList as $sectorCode) {
                                // if (isset($product[$sectorCode]) && $product[$sectorCode] === 'Y' && array_key_exists(str_replace('U_', '', $sectorCode), $sectorList)) {
                                //     $sectorIds[] = str_replace('U_', '', $sectorCode);
                                //}
                                if (isset($product[$sectorCode]) && $product[$sectorCode] === 'Y') {
                                    $sectorIds[] = $sectorCode;
                                }
                            }
                            
                            //Sector 
                            $sectorName = '';
                            if (!empty($sectorIds)) {
                                $sector_term = array();
                                foreach($sectorIds as $sector){
                                    $sector_args = [
                                        'taxonomy'          => 'sector',
                                        'fields'            => 'ids',
                                        'hide_empty'        => false,
                                        'meta_query'	    => array(
                                            array( 
                                                'key'	 	=> 'sap_id', 
                                                'value'	  	=> strtolower(strval($sector)),
                                                'compare' 	=> '='
                                            )
                                        )
                                    ];
                                    $sector_query = new \WP_Term_Query($sector_args);
                                    
                                    if (!empty($sector_query->terms)) {
                                        $sector_term[] = $sector_query->terms[0];
 
                                    }
                                }
                                
                                wp_set_object_terms($product_id, $sector_term, 'sector');
                                Common::add_log('Woo-Product', 'Product Sector Update: ' . $product['ItemCode']);
                                
                            }
                            
                            $brandName = '';
                            if (array_key_exists($productBrandId, $brandList)) {
                                $brandName = $brandList[$productBrandId]["name"];
                            }
                            
                            if (strlen($brandName) > 0) {
                                $brand_args = [
                                    'taxonomy'          => 'brand',
                                    'fields'            => 'ids',
                                    'hide_empty'        => false,
                                    'meta_query'	    => array(
                                        array( 
                                            'key'	 	=> 'sap_id', 
                                            'value'	  	=> strtolower(strval($productBrandId)),
                                            'compare' 	=> '='
                                        )
                                    )
                                ];
                                
                                $brand_query = new \WP_Term_Query($brand_args);
                                
                                $brand_term = null;
                                if (!empty($brand_query->terms)) {
                                    $brand_term = $brand_query->terms[0];
                                    wp_set_object_terms($product_id, $brand_term, 'brand');
                                }
                            }
        
                            $typeName = '';
                            if (array_key_exists($productTypeId, $typeList)) {
                                $typeName = $typeList[$productTypeId]["name"];
                            }
    
                            if (strlen($typeName) > 0) {
                                $type_args = [
                                    'taxonomy'          => 'product-type',
                                    'fields'            => 'ids',
                                    'hide_empty'        => false,
                                    'meta_query'	    => array(
                                        array( 
                                            'key'	 	=> 'sap_id', 
                                            'value'	  	=> strtolower(strval($productTypeId)),
                                            'compare' 	=> '='
                                        )
                                    )
                                ];
                                $type_query = new \WP_Term_Query($type_args);
                                
                                $type_term = null;
                                if (!empty($type_query->terms)) {
                                    $type_term = $type_query->terms[0];
                                    wp_set_object_terms($product_id, $type_term, 'product-type');
                                }
                            }
                            
                        
                    }
    
                    if (!Common::write_json($product_file, $product_json)) {
                        Common::add_log('Error', 'Failed to write remaining products back to the JSON file.');
                        break;
                    }
 
                }
                $x++;
        }
    }
    
    
    public function update_taxonomy($taxonomy, $terms_to_sync) {
        foreach($terms_to_sync as $term_code => $term_name) {
            $args = [
                'taxonomy'          => $taxonomy,
                'fields'            => 'ids',
                'hide_empty'        => false,
                'meta_query'	    => array(
            		array( 
            			'key'	 	=> 'sap_id', 
            			'value'	  	=> strtolower(strval($term_code)),
            			'compare' 	=> '='
            		)
            	)
            ];
            $term_query = new \WP_Term_Query($args);
            
            $existing_term = null;
            if (!empty($term_query->terms)) {
                $existing_term = $term_query->terms[0];
            }
    
            if (!$existing_term) {
                // Term does not exist, add it
                $new_term = wp_insert_term(strval($term_name['name']), $taxonomy, array(
                    'slug' => strval($term_name['name']),
                ));
                update_term_meta( $new_term['term_id'], "sap_id", strtolower(strval($term_code)) );
                update_term_meta( $new_term['term_id'], "content", $term_name['content'] );
            } else {
                wp_update_term($existing_term, $taxonomy, array(
                    'name' => strval($term_name['name']),
                ));
                update_term_meta( $existing_term, "content", $term_name['content'] );
            }
        }
    
        $existing_terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false, // Include terms with no associated posts
        ));
        
        $sanitised_keys = array();
        foreach($terms_to_sync as $term_code => $term_name) {
            $new_code = strval($term_code);
            $new_code = strtolower($new_code);
            $sanitised_keys[] = $new_code;
        }
        
        foreach ($existing_terms as $existing_term) {
            $existing_term_id = $existing_term->term_id;
            $existing_term_meta = get_term_meta($existing_term_id, "sap_id");
            
        
            if (empty($existing_term_meta) || !in_array($existing_term_meta[0], $sanitised_keys)) {
                wp_delete_term($existing_term->term_id, $taxonomy);
            }
        }
    }
}