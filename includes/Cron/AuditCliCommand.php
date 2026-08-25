<?php

namespace TwgSapConnection\Cron;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use TwgSapConnection\Admin\Common;
use TwgSapConnection\Admin\Product;

/**
 * WP-CLI audit commands — read-only product/JSON/Woo comparison.
 */
class AuditCliCommand {

    /**
     * Full audit report comparing JSON and WooCommerce products.
     *
     * ## OPTIONS
     *
     * [--compare-sap]
     * : Include live SAP item count in the report.
     *
     * [--limit=<number>]
     * : Limit orphan rows returned in the report output.
     *
     * [--format=<format>]
     * : Output format: table or json.
     *
     * [--output=<path>]
     * : When format is json, save report to this filename under uploads/SAP_Connection/.
     *
     * ## EXAMPLES
     *
     *     wp twg-sap-connection audit report
     *     wp twg-sap-connection audit report --compare-sap --limit=50
     *     wp twg-sap-connection audit report --format=json --output=audit_report.json
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Named arguments.
     */
    public function report( array $args, array $assoc_args ): void {
        if ( ! $this->ensure_woocommerce() ) {
            return;
        }

        $compare_sap  = isset( $assoc_args['compare-sap'] );
        $limit        = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 0;
        $format       = $assoc_args['format'] ?? 'table';
        $report       = Product::build_audit_report( $compare_sap, $limit );

        if ( 'json' === $format ) {
            $json = wp_json_encode( $report, JSON_PRETTY_PRINT );

            if ( ! empty( $assoc_args['output'] ) ) {
                $filename = sanitize_file_name( (string) $assoc_args['output'] );
                Common::write_json( $filename, $report );
                \WP_CLI::success( 'Audit report saved to uploads/SAP_Connection/' . $filename );
                return;
            }

            \WP_CLI::log( $json );
            return;
        }

        \WP_CLI::log( 'Audit report generated at: ' . $report['generated_at'] );
        \WP_CLI::log( 'JSON file: ' . $report['json_file'] );
        \WP_CLI::log( 'JSON SKU count: ' . $report['json_count'] );
        \WP_CLI::log( 'WooCommerce SKU count: ' . $report['woo_sku_count'] );
        \WP_CLI::log( 'Orphan count (Woo SKUs missing from JSON): ' . $report['orphan_count'] );
        \WP_CLI::log( 'JSON-only SKU count (in JSON, not in Woo): ' . $report['json_only_count'] );

        if ( $compare_sap ) {
            \WP_CLI::log( 'SAP count: ' . ( null === $report['sap_count'] ? 'unknown' : (string) $report['sap_count'] ) );
            \WP_CLI::log( 'SAP/JSON mismatch: ' . ( $report['sap_json_mismatch'] ? 'yes' : 'no' ) );
        }

        if ( empty( $report['orphans'] ) ) {
            \WP_CLI::log( 'No orphan WooCommerce products found.' );
            return;
        }

        \WP_CLI\Utils\format_items(
            'table',
            $report['orphans'],
            [ 'sku', 'name', 'current_status', 'would_become', 'product_id' ]
        );
    }

    /**
     * List WooCommerce products whose SKU is missing from product_codes.json.
     *
     * ## OPTIONS
     *
     * [--limit=<number>]
     * : Limit number of rows shown.
     *
     * ## EXAMPLES
     *
     *     wp twg-sap-connection audit orphans
     *     wp twg-sap-connection audit orphans --limit=25
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Named arguments.
     */
    public function orphans( array $args, array $assoc_args ): void {
        if ( ! $this->ensure_woocommerce() ) {
            return;
        }

        $limit  = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 0;
        $result = Product::get_woo_orphans( null, $limit );

        \WP_CLI::log(
            sprintf(
                'Found %d orphan WooCommerce SKU(s) not present in %s.',
                count( Product::get_woo_orphans( null, 0 )['orphans'] ),
                Product::get_product_codes_filename()
            )
        );

        if ( empty( $result['orphans'] ) ) {
            \WP_CLI::success( 'No orphan products found.' );
            return;
        }

        \WP_CLI\Utils\format_items(
            'table',
            $result['orphans'],
            [ 'sku', 'name', 'current_status', 'would_become', 'product_id' ]
        );
    }

    /**
     * Preview how a product would map from JSON/SAP flags to WooCommerce status.
     *
     * ## OPTIONS
     *
     * [--sku=<sku>]
     * : Product SKU / ItemCode to preview.
     *
     * ## EXAMPLES
     *
     *     wp twg-sap-connection audit preview --sku=01547330
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Named arguments.
     */
    public function preview( array $args, array $assoc_args ): void {
        if ( ! $this->ensure_woocommerce() ) {
            return;
        }

        $sku = trim( (string) ( $assoc_args['sku'] ?? '' ) );

        if ( $sku === '' ) {
            \WP_CLI::error( 'Provide --sku=<sku> to preview a product.' );
            return;
        }

        $json_product = Product::get_json_product_by_sku( $sku );
        $product_id   = wc_get_product_id_by_sku( $sku );
        $wc_product   = $product_id ? wc_get_product( $product_id ) : null;

        if ( null === $json_product ) {
            \WP_CLI::warning( sprintf( 'SKU %s is not in %s.', $sku, Product::get_product_codes_filename() ) );

            if ( $wc_product ) {
                \WP_CLI::log( 'WooCommerce product exists:' );
                \WP_CLI::log( '  Name: ' . $wc_product->get_name() );
                \WP_CLI::log( '  Status: ' . $wc_product->get_status() );
                \WP_CLI::log( '  Would become: draft (if reconcile-products --apply is run)' );
            } else {
                \WP_CLI::log( 'No WooCommerce product found for this SKU.' );
            }

            return;
        }

        $resolved_status = Product::resolve_status_from_sap( $json_product );

        \WP_CLI::log( 'JSON product preview for SKU: ' . $sku );
        \WP_CLI::log( '  ItemName: ' . ( $json_product['ItemName'] ?? '' ) );
        \WP_CLI::log( '  U_Pub_Flg: ' . ( $json_product['U_Pub_Flg'] ?? '' ) );
        \WP_CLI::log( '  U_PRX_WbAv: ' . ( $json_product['U_PRX_WbAv'] ?? '' ) );
        \WP_CLI::log( '  Resolved Woo status: ' . $resolved_status );

        if ( $wc_product ) {
            \WP_CLI::log( '  Current Woo status: ' . $wc_product->get_status() );
            \WP_CLI::log( '  Woo product ID: ' . $product_id );
        } else {
            \WP_CLI::log( '  WooCommerce product: not found (would be created on sync)' );
        }
    }

    /**
     * @return bool
     */
    private function ensure_woocommerce(): bool {
        if ( ! class_exists( 'WooCommerce' ) ) {
            \WP_CLI::error( 'WooCommerce must be active for audit commands.' );
            return false;
        }

        return true;
    }
}

/**
 * WP-CLI reconcile command — preview or apply drafting of orphan Woo products.
 */
class ReconcileCliCommand {

    /**
     * Draft WooCommerce products whose SKU is missing from product_codes.json.
     *
     * ## OPTIONS
     *
     * [--apply]
     * : Apply changes. Without this flag, preview only.
     *
     * ## EXAMPLES
     *
     *     wp twg-sap-connection reconcile-products
     *     wp twg-sap-connection reconcile-products --apply
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Named arguments.
     */
    public function __invoke( array $args, array $assoc_args ): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            \WP_CLI::error( 'WooCommerce must be active for reconcile-products.' );
            return;
        }

        $apply  = isset( $assoc_args['apply'] );
        $result = Product::reconcile_products_not_in_json( null, ! $apply );

        \WP_CLI::log(
            sprintf(
                '%s %d WooCommerce product(s) with SKU missing from %s (checked %d SKU products).',
                $apply ? 'Drafted' : 'Would draft',
                $result['count'],
                Product::get_product_codes_filename(),
                $result['checked']
            )
        );

        if ( empty( $result['products'] ) ) {
            \WP_CLI::success( 'No orphan products to reconcile.' );
            return;
        }

        \WP_CLI\Utils\format_items(
            'table',
            $result['products'],
            [ 'sku', 'name', 'current_status', 'would_become', 'applied', 'product_id' ]
        );

        if ( $apply ) {
            \WP_CLI::success( 'Reconciliation applied.' );
        } else {
            \WP_CLI::warning( 'Preview only. Re-run with --apply to draft these products.' );
        }
    }
}
