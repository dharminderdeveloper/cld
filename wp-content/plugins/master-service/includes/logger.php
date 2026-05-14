<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Append a log entry into daily JSON file inside logs directory.
 *
 * @param array $entry Associative array that will be appended.
 * @return bool True on success, false on failure.
 */
function master_append_log( $entry ) {
    $logs_dir = MASTER_LOGS_DIR;
    if ( ! file_exists( $logs_dir ) ) {
        wp_mkdir_p( $logs_dir );
    }

    // Use site timezone for timestamp file grouping; you can switch to UTC if desired.
    $date = date_i18n( 'Y-m-d' );
    $file = trailingslashit( $logs_dir ) . $date . '.json';

    $all = [];
    if ( file_exists( $file ) ) {
        $content = @file_get_contents( $file );
        if ( $content ) {
            $decoded = json_decode( $content, true );
            if ( is_array( $decoded ) ) {
                $all = $decoded;
            }
        }
    }

    $all[] = $entry;

    $tmp = $file . '.tmp';
    $written = @file_put_contents( $tmp, wp_json_encode( $all, JSON_PRETTY_PRINT ) );
    if ( $written === false ) {
        return false;
    }
    @chmod( $tmp, 0644 );
    rename( $tmp, $file );
    return true;
}
