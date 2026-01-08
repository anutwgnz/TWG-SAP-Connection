<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Add cleanup logic here (e.g., delete options, drop tables).
/**
 * Delete the "SAP_Connection" folder from the uploads directory upon plugin uninstallation
 */
function delete_sap_connection_folder(): void {
    $upload_dir = wp_upload_dir();
    $sap_connection_dir = trailingslashit( $upload_dir['basedir'] ) . 'SAP_Connection';

    if ( file_exists( $sap_connection_dir ) ) {
        // Recursively delete the directory and its contents
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $sap_connection_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ( $files as $fileinfo ) {
            $todo = ( $fileinfo->isDir() ? 'rmdir' : 'unlink' );
            $todo( $fileinfo->getRealPath() );
        }

        rmdir( $sap_connection_dir );
    }
}

delete_sap_connection_folder();