<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TAA_Activator {
    public static function activate() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'taa_staging';
        $charset_collate = $wpdb->get_charset_collate();

        // Added marketing_url column for the new feature
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            status varchar(20) DEFAULT 'PENDING',
            dir varchar(10) NOT NULL,
            chart_name varchar(255) NOT NULL,
            strike varchar(50),
            lot_size int,
            total_lots int,
            total_qty int,
            entry float,
            target float,
            sl float,
            points float,
            risk float,
            profit float,
            rr_ratio varchar(50),
            image_url varchar(255),
            rejection_note text,
            rejection_image varchar(255),
            marketing_url varchar(255),
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
}