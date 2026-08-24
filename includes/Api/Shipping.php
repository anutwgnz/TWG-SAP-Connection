<?php 
namespace TwgSapConnection\Api;
use TwgSapConnection\Admin\Common;

class Shipping 
{
    private $authKey = 'YcygiEgxmFnNApzyfmCAsN33VJF965ENk2MUKbJL5bU=';
    private $API_BASEURL = "";
    private $PORT = 7055;
	private $STANDARD_TAX_RATE = 0.15;
    
    public function __construct()
    {
        $this->API_BASEURL = "https://apps.ils.co.nz:" . $this->PORT . "/freightapi";
    }
	
	public function get_tax_rate() 
	{
		return $this->STANDARD_TAX_RATE;
	}
    
    public function get_options()
    {
        return [
            "PA" => "Pack",
            "CO" => "Courier",
            "PO" => "Pump Over"
        ];
    }
    
    // Get values from Pack, Courier, or Pump Over
    public function get_values( $option ) {
        $options = array_keys( $this->get_options() );
        
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->API_BASEURL . "/getroutes?route=" . $option,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false, //SSL false
            CURLOPT_SSL_VERIFYHOST => false, //SSL false
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Authorization: '. $this->authKey,
            ),
        ));
        
        $response = json_decode( curl_exec($curl) );
        
        curl_close($curl);
        
        /*
        If no error, response returns an array of objects with 2 properties: code & name
        */
        return $response;
    }
    
    // Calculate freight amounts
    public function calculate_freight_amount( $request ) 
    {
        $requestArr = (array) $request;
        if(!$requestArr['CustomerCode']) {
            return new \WP_Error( 'missing_customer', 'Missing customer code field' );
        }
        
        if(!$requestArr['FreightMethod']) {
            return new \WP_Error( 'freight_method', 'Missing freight method field' );
        }
        
        $json_data = json_encode( $request );
        
        $curl = curl_init();
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->API_BASEURL . "/calc",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false, //SSL false
            CURLOPT_SSL_VERIFYHOST => false, //SSL false
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => array(
                'Authorization: '. $this->authKey,
                'Content-Type: application/json',
            ),
            CURLOPT_POSTFIELDS => $json_data,
        ));
        
        $response = json_decode( curl_exec($curl) );
        
        curl_close($curl);

        return $response;
    }
	
		
	public function get_shipping_gst($price, $type) 
	{
		$after_tax_price = (float)$price;
		
		$before_tax_price = $after_tax_price /  (1 + $this->STANDARD_TAX_RATE);
		
		if($type == 'gst') {
			return $after_tax_price - $before_tax_price; 
		} else if($type == 'before-gst') { 
			return $before_tax_price;
		}
		
		// Return 0 if type is invalid
		return 0;
	}
}

?>