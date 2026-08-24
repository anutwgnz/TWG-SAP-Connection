<?php

namespace TwgSapConnection\Admin;
use TwgSapConnection\Admin\Common;
use TwgSapConnection\Api\Client;
use TwgSapConnection\Api\Shipping;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Order {
    private $sapbConn;
    private $shippingConn;
    private $entity = "Orders";
	private $shipping_methods = [
            "Pack" => "PA",
            "Courier" => "CO",
            "Pump Over" => "PO"
        ];

    public function __construct() {
        $this->sapbConn = new Client();
        $this->shippingConn = new Shipping();
    }

        /**
        * Example: Sync a single order with SAP
        */
    public function sync_single_order( $order_id ) {
        $order = $this->get_wc_order( $order_id );

        if ( is_wp_error( $order ) ) {
            return $order;
        }

        $user_id = $order->get_user_id();
        $organisation = $user_id ? get_field('organisation', 'user_' . $user_id) : '';
        $customer_code = $organisation ? get_field('customer_code', $organisation->ID) : '';
        $line_items = array();
        $shipping_line_items = array();
		$PO_number = "";

		if (isset(get_post_meta($order->get_id(), 'PO Number')[0])) {
			$PO_number = get_post_meta($order->get_id(), 'PO Number')[0];
		}
		$shipping_method = $order->get_shipping_method();
		$shipping_method_code = $this->shipping_methods[$shipping_method];
		$shipping_codes = null;
		$route = "";
        
        foreach ($order->get_items() as $item_id => $item ) {
            $product = $item->get_product();   
            $sku = $product->get_sku();
            $quantity = strval($item->get_quantity());
            $unit_price = $item->get_total();
			$string_price = strval($unit_price)/$quantity;

            
            $line_items[] = array(
              "ItemCode" => $sku,
              "Quantity" => $quantity,
              "UnitPrice" => $string_price
            );
            
            $shipping_line_items[] = array(
			    "ItemCode" => $sku,
			    "Description" => $product->get_title(),
			    "Quantity" => $quantity,
            );
        }
    			
		$shipping_request = array(
			"CustomerCode" => $customer_code,
			"FreightMethod" => $shipping_method_code ?? "",
			"ProductLines" => $shipping_line_items,
		);
		
		$shipping_response = $this->shippingConn->calculate_freight_amount($shipping_request);
		
        $shippingLineItemsLength = count($shipping_response->productLines);

        for ($i = 0; $i < $shippingLineItemsLength; $i++) {
            $shipping_item = $shipping_response->productLines[$i];

        	if ($shipping_method == "Pump Over") { //pumpOver
                $shipping_cost = $shipping_item->pumpOver;
            } else if ($shipping_method == "Pack") { //pack
                $shipping_cost = $shipping_item->pack;
            } else if ($shipping_method == "Courier") { //courier
                $shipping_cost = $shipping_item->courier;
            }else{
                $shipping_cost = 0.000001;
            }
            
        	$line_items[$i]["DocumentLineAdditionalExpenses"] = array(
				  array(
			  		"ExpenseCode" => "65",
					"LineTotal" => strval($shipping_cost)
			  	)
			  );
        }
        
    	$pack_code = $shipping_response->pack;
		$pumpover_code = $shipping_response->pumpOver;
		$courier_code = $shipping_response->courier;

		$shipping_codes = array(
			"Pack" => $pack_code,
			"Courier" => $courier_code,
			"Pump Over" => $pumpover_code
		);
		
		$route = $shipping_codes[$shipping_method];
		
		$address_id = null;
		$address_id = get_post_meta( $order_id, '_billing_address_id',true);
// 		if(!$order->get_meta('_billing_address_id')){
// 		    $address_id = $order->get_meta('_billing_address_id');
// 		}
        
        
        // date_default_timezone_set("Pacific/Auckland");
        
        $order_data = array(
            "CardCode" => $customer_code,
			"DocDueDate" => date("Y-m-d"),
			"U_OnHold" => "Y",
			"NumAtCard" => $PO_number,
			"U_Route" => $route,
			"ShipToCode" => $address_id,
            "DocumentLines" => $line_items,
        );
        
      
        Common::add_log( 'Order Client Sent', wp_json_encode( $order_data ) );

        $response = $this->sapbConn->insert_sap_data($this->entity, $order_data);
        Common::add_log("Order Client", $response['api_status']);
        //Adding order status to db, then we can use for resync 
        if($response['api_status'] == 201){
            Common::add_log("Order Client Status", json_encode($response));
            update_post_meta($order_id, '_sap_sync_status', true);
        }else{
            Common::add_log("Order Client Status", json_encode($response));
            update_post_meta($order_id, '_sap_sync_status', false);
        }
        
        return $response;

    }

    /**
     * Load a WooCommerce order, with guards for missing WC or invalid IDs.
     *
     * @param int $order_id
     * @return \WC_Order|\WP_Error
     */
    private function get_wc_order( $order_id ) {
        $order_id = absint( $order_id );

        if ( $order_id <= 0 ) {
            Common::add_log( 'Order load failed', 'Invalid order ID' );
            return new \WP_Error( 'invalid_order_id', 'Invalid order ID' );
        }

        if ( ! function_exists( 'wc_get_order' ) ) {
            Common::add_log( 'Order load failed', 'WooCommerce not loaded' );
            return new \WP_Error( 'woocommerce_missing', 'WooCommerce is not active' );
        }

        if ( ! did_action( 'woocommerce_after_register_post_type' ) ) {
            Common::add_log( 'Order load failed', 'Called before WooCommerce post types registered' );
            return new \WP_Error( 'woocommerce_not_ready', 'WooCommerce is not ready yet' );
        }

        $order = wc_get_order( $order_id );

        if ( ! $order instanceof \WC_Order ) {
            Common::add_log( 'Order load failed', 'Order not found: ' . $order_id );
            return new \WP_Error( 'invalid_order', 'Order not found' );
        }

        return $order;
    }

}