<?php

namespace TwgSapConnection\Api;
use TwgSapConnection\Admin\Common;
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Client {

    private string $sap_host;
    private string $sap_database;
    private string $sap_username;
    private string $sap_password;
    private string $sap_host_path;
    private int $sap_port;
    private ?string $auth_token = null;
    private ?string $url = null;
    
    public function __construct() {
        $this->sap_host = rtrim( Common::get_setting( 'sap_host' ) ?? '', '/' );
        $this->sap_host_path = trim( Common::get_setting( 'sap_host_path' ) ?? '', '/' ) . '/';
        $this->sap_database = Common::get_setting( 'sap_database' ) ?? 'default_database';
        $this->sap_username = Common::get_setting( 'sap_username' ) ?? 'default_username';
        $this->sap_password = Common::get_setting( 'sap_password' ) ?? 'default_password';
        $this->sap_port = (int) ( Common::get_setting( 'sap_port' ) ?? 50000 );
        $this->auth_token = Common::get_setting( 'sap_auth_token' ) ?: null;
        $this->url = $this->sap_host .':'. $this->sap_port  . '/' . $this->sap_host_path;
    }

    public static function set_curl_options( $handle ) {
        curl_setopt( $handle, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $handle, CURLOPT_ENCODING, '' );
        curl_setopt( $handle, CURLOPT_MAXREDIRS, 10 );
        curl_setopt( $handle, CURLOPT_TIMEOUT, 0 );
        curl_setopt( $handle, CURLOPT_FOLLOWLOCATION, true );
        curl_setopt( $handle, CURLOPT_SSL_VERIFYPEER, false ); // SSL false
        curl_setopt( $handle, CURLOPT_SSL_VERIFYHOST, false ); // SSL false
        curl_setopt( $handle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
    }

    private function is_authenticated($session_id): bool {
        $response = wp_remote_get( $this->url, [
            'headers'   => [ 'Cookie' => 'B1SESSION=' . $session_id ],
            'sslverify' => false,
            'timeout'   => 30,
        ] );
        if ( is_wp_error( $response ) ) {
            return false;
        }
        return true;
    }

    public function login() {

        $response = wp_remote_post( $this->url . 'Login', [
            'body'      => json_encode([
                'CompanyDB' => $this->sap_database,
                'UserName'  => $this->sap_username,
                'Password'  => $this->sap_password
            ]),
            'headers'   => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json'
            ],
            'sslverify' => false,
            'timeout'   => 30,
        ]);

        if ( is_wp_error( $response ) ) {
            return false;
        }
        $session_id = '';
        $route_id = '';
        if (isset($response['cookies']) && is_array($response['cookies'])) {
            foreach ($response['cookies'] as $cookie) {
                if (isset($cookie->name) && $cookie->name === 'B1SESSION') {
                    $session_id = $cookie->value;
                    break;
                }
            }
        }
        
        if (isset($response['cookies']) && is_array($response['cookies'])) {
            foreach ($response['cookies'] as $cookie) {
                if (isset($cookie->name) && $cookie->name === 'ROUTEID') {
                    $route_id = $cookie->value;
                    break;
                }
            }
        }
         Common::set_setting('sap_auth_token', $session_id);
         Common::set_setting('sap_auth_route_id', $route_id);
        return $session_id;
    }


    
    public function insert_sap_data( $entity, $body ) {
        $session = $this->login(); // MUST return BOTH session + route
        $route_id = Common::get_setting('sap_auth_route_id');
        Common::add_log("Order Route", $route_id);    
        if (!$session || empty($session)) {
            return false;
        }
    
        $jsonData = json_encode($body);
          

        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => $this->url . ltrim( $entity, '/' ),
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_SSL_VERIFYPEER => false,
          CURLOPT_SSL_VERIFYHOST => false,
          CURLOPT_POSTFIELDS =>$jsonData,
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Accept: application/json',
            'Expect:',
            'Cookie: B1SESSION='.$session.'; ROUTEID='.$route_id
          ),
        ));
        
        $response = curl_exec($curl);

        $return_array = [
            'curl_error' => curl_error($curl),
            'curl_errno' => curl_errno($curl),
            'api_status' => curl_getinfo($curl, CURLINFO_HTTP_CODE),
            'request_header_size' => curl_getinfo($curl, CURLINFO_HEADER_SIZE),
        ];

        curl_close($curl);
    
        Common::add_log("Order Client Raw", json_encode($return_array));
        return $return_array;
        
    }

    public function update_sap_data( $entity, $id, $body ) {
        if ( ! $this->login() ) {
            return false;
        }
        $json_data = json_encode($body);
        $url = $this->url . ltrim( $entity, '/' ) . '(\'' . $id . '\')';


        $headers = [
            'Cookie'       => 'B1SESSION=' . Common::get_setting( 'sap_auth_token' ),
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];

        $response = wp_remote_request($url, [
            'method'    => 'PATCH',
            'body'      => $json_data,
            'headers'   => $headers,
            'sslverify' => false,
            'timeout'   => 30,
        ]);

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( is_wp_error( $response ) ) {
            return $result = (object) array_merge(array('api_status' => ''), array($code . ' ' . $data['message']));
        }
       
        $result = (object) array_merge(array('api_status' => $code), (array) $data);
        return $result;
    }   

    public function delete_sap_data( $entity, $id ) {
        if ( ! $this->login() ) {
            return false;
        }
        $url = $this->url . ltrim( $entity, '/' ) . '(\'' . $id . '\')';

        $headers = [
            'Cookie'       => 'B1SESSION=' . Common::get_setting( 'sap_auth_token' ),
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];

        $response = wp_remote_request( $url, [
            'method'    => 'DELETE',
            'headers'   => $headers,
            'sslverify' => false,
            'timeout'   => 30,
        ]);

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( is_wp_error( $response ) ) {
            return $result = (object) array_merge(array('api_status' => ''), array($code . ' ' . $data['message']));
        }
       
        $result = (object) array_merge(array('api_status' => $code), (array) $data);
        return $result;
    }

    public function sap_sql_query( $query ) {
        $token = $this->login();
        if ( ! $token ) {
            return false;
        }
        
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->url . "SQLQueries('" . $query . "')/List",
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
                'Cookie: B1SESSION=' . $token,
            ),
        ));

        $response = curl_exec($curl);

        $header_contant_type = curl_getinfo($curl, CURLINFO_CONTENT_TYPE); // get status code.

        $filetype_array = explode('/', $header_contant_type);

        $filetype = $filetype_array[1];

        if (isset($response->error)) {

            return $response->error->code . ' ' . $response->error->message->value;
        }
        curl_close($curl);
        
        return $response; 
        
    }
    
    
    public function get_sap_data($entity, $id = '', $query_string = '', $body = '')
    {
        $loginDetails = $this->login();

        if ($loginDetails != '') {
                $response = [];
                $get_data_url = $all_datas = $this->url . $entity;
                if ($id != '' && !empty($query_string)) {
                    $get_data_url = $this->url . $entity . '(\'' . $id . '\')?' . $query_string;
                } else if ($id != '') {
                    $get_data_url = $this->url . $entity . '(\'' . $id . '\')';
                } else if (!empty($query_string)) {
                    $get_data_url = $this->url . $entity . '?' . $query_string;
                }

                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => $get_data_url,
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
                        'Cookie: B1SESSION=' . $loginDetails,
                    ),
                    CURLOPT_POSTFIELDS => $body,
                ));

                $response = json_decode(curl_exec($curl));
                $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE); // get status code.
                // Check if any error occurred
                if (isset($response->error)) {
                    return $result = (object) array_merge(array('api_status' => ''), array($response->error->code . ' ' . $response->error->message->value));
                }
                curl_close($curl);

                $result = (object) array_merge(array('api_status' => $http_status), (array) $response);
                return $result;
        }
    }

}
