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
        $filename = Common::get_setting('product_brand_json_url') ?: 'product_brand_json_url.json';
        Common::write_json($filename, $keyed_brand_list);
        
        // All Types Listing
        $typeList = $client->sap_sql_query('AllItemGroups'); // AllItemGroups == types
        $typeListDatas = json_decode($typeList);
        $typeLists = $typeListDatas->value;
        $keyed_type_list = array();
        foreach ($typeLists as $tlist) {
            $keyed_type_list[$tlist->Code] = array('name'=>$tlist->Name,'content'=>$tlist->U_Content);
        }
        
        // Write JSON with only codes
        $filename = Common::get_setting('product_type_json_url') ?: 'product_type_json_url.json';
        Common::write_json($filename, $keyed_type_list);
        
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
        $filename = Common::get_setting('product_sector_json_url') ?: 'product_sector_json_url.json';
        Common::write_json($filename, $sector_codes);
        
        //Update Taxonomies
        // self::update_taxonomy('brand', $keyed_brand_list);
        // self::update_taxonomy('product-type', $keyed_type_list);
        // self::update_taxonomy('sector', $keyed_sector_list);
    } 
    
    
    /**
     * Fetch products from SAP Business One
     */
    public static function fetch_products() {
        //Get sector codes from JSON   
        $sector_file = Common::get_setting('product_sector_json_url') ?: 'product_sectors.json';
        $sector_json = Common::read_json($sector_file);
        $client = new Client();
        $prodArr = array();
	    $endLoop = false;
        $intIgnore = 0;
        $intPage = 1;
        $intPerPage = 20;
        $bytypefilter[] = urlencode("U_PRX_WbAv eq 'Y' and Valid eq 'tYES'");
	    $filterby = implode('+and+', $bytypefilter);

        try {
            $numProducts = $client->get_sap_data('Items/$count', '', '$filter=' . $filterby . '&$skip=' . $intIgnore);

            $numProductsArray = json_decode(json_encode($numProducts), true);
            $countProducts = $numProductsArray[0];
            /**
                {
                    "@odata.etag": "W/\"356A192B7913B04C54574D18C28D46E6395428AB\"",
                    "ItemCode": "01547330",
                    "ItemName": "PARAGON GOLD ISO 1500 15.9 L",
                    "Picture": "Pail AB.jpg",
                    "Valid": "tYES",
                    "U_BRAND": "03",
                    "U_ITMPRGRP": "104",
                    "U_PRX_FFlg": "N",
                    "U_PRX_WbAv": "Y",
                    "U_PRX_Desc": "Semi-synthetic gear oil suitable for use in heavily loaded gear-boxes. Especially suitable for linear screw drives in CNC machining centres.",
                    "U_Pub_Flg": "Y",
                    "U_TIM": null,
                    "U_MAR": null,
                    "U_FOOD": null,
                    "U_RAIL": null,
                    "U_POW": null,
                    "U_FLEET": null,
                    "U_REFR": null,
                    "U_IND": null,
                    "U_METAL": null,
                    "U_PACK": null,
                    "U_Variant": null
                  },
            **/
            
            while (!$endLoop) {
                try {
                    $Products = $client->get_sap_data('Items', '', '$select=ItemCode,ItemName,U_PRX_Desc,Picture,Valid,U_Variant,U_BRAND,U_ITMPRGRP,U_Pub_Flg,U_PRX_WbAv,Valid,U_PRX_FFlg,' . implode(',', $sector_json) . '&$filter=' . $filterby . '&$skip=' . $intIgnore);

                    $arrProducts = (array) $Products->value;
                    
                    if( count($arrProducts) > 0 ) {
                        $arrProductsFiltered = array_filter($arrProducts, function ($arr_item) { 
                            return ($arr_item->U_PRX_WbAv === 'Y' && $arr_item->Valid === 'tYES'); // && $arr_item->U_Pub_Flg === 'Y'
                        });
                        $prodArr = array_merge($prodArr, $arrProductsFiltered);
                    }
                    //$prodArr = array_merge($prodArr, $arrProducts);
                    if($intIgnore === $countProducts){
                        $endLoop = true;
                    }

                    if(($intIgnore + $intPerPage) > $countProducts) {
                        $intIgnore += $intIgnore + $intPerPage - $countProducts;
                        $endLoop = true;
                    } else {
                        $intIgnore += $intPerPage;
                    }
                } catch (\Exception $e) {
                    Common::add_log('Error', 'Failed to log product fetching: ' . $e->getMessage());
                }
                
            }
            $filename = Common::get_setting('product_codes_json_url') ?: 'product_codes.json';
            Common::write_json($filename, $prodArr);
        } catch (\Exception $e) {
            Common::add_log('Error', 'Failed to login to SAP: ' . $e->getMessage());
            return;
        }
        // Get total number of products
        
    }
    
    /**
     * Update products from SAP Business One
     */
    
    public static function sap_sync_products() {

        $product_file = Common::get_setting('product_codes_json_url') ?: 'product_codes.json';
        $brandList_file = Common::get_setting('product_brand_json_url') ?: 'product_brands.json';
        $typeList_file = Common::get_setting('product_type_json_url') ?: 'product_types.json';
        $sectorList_file = Common::get_setting('product_sector_json_url') ?: 'product_sectors.json';
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
                            
                            //
                            if($product['ItemCode'] == 3363690){
                                Common::add_log('Woo-Product-Special', 'Product SKU: ' . $product['ItemCode'] . ' | Product: ' . json_encode($product));
                            }
                            
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
        
                            //$status = ($product['U_Pub_Flg'] === 'N' && $product['U_PRX_WbAv'] === 'Y') ? 'private' : 'publish';
                            
                            $status = 'private';
                            
                            if($product['U_Pub_Flg'] === 'N' && $product['U_PRX_WbAv'] === 'N'){
                                $status = 'private';
                            }
                            
                            if($product['U_Pub_Flg'] === 'Y' && $product['U_PRX_WbAv'] === 'N'){
                                $status = 'private';
                            }
                            
                            if($product['U_Pub_Flg'] === 'Y' && $product['U_PRX_WbAv'] === 'Y'){
                                $status = 'publish';
                            }
                            
                            if($product['U_Pub_Flg'] === 'N' && $product['U_PRX_WbAv'] === 'Y'){
                                $status = 'private';
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