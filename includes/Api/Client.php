<?php

namespace TwgPluginName\Api;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Client {

    private string $base_url;

    public function __construct( string $base_url ) {
        $this->base_url = $base_url;
    }

    public function get( string $endpoint ): array {
        $response = wp_remote_get( $this->base_url . $endpoint );

        if ( is_wp_error( $response ) ) {
            return [];
        }

        return json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];
    }
}
