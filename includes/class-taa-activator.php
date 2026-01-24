<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TAA_Activator {
    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // 1. Existing Staging Table
        $table_name = $wpdb->prefix . 'taa_staging';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            status varchar(50) DEFAULT 'PENDING' NOT NULL,
            dir varchar(10) NOT NULL,
            chart_name varchar(100) NOT NULL,
            strike varchar(50) DEFAULT '' NOT NULL,
            entry float DEFAULT 0,
            target float DEFAULT 0,
            sl float DEFAULT 0,
            points float DEFAULT 0,
            profit float DEFAULT 0,
            risk float DEFAULT 0,
            lot_size int DEFAULT 0,
            total_lots int DEFAULT 0,
            total_qty int DEFAULT 0,
            rr_ratio varchar(20) DEFAULT '',
            image_url text DEFAULT '',
            marketing_url text DEFAULT '',
            rejection_note text DEFAULT '',
            rejection_image text DEFAULT '',
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // 2. [NEW] Instruments Table
        $table_inst = $wpdb->prefix . 'taa_instruments';
        $sql_inst = "CREATE TABLE $table_inst (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            lot_size int DEFAULT 1 NOT NULL,
            mode varchar(20) DEFAULT 'BOTH' NOT NULL,
            strike_req varchar(10) DEFAULT 'NO' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY name (name) 
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
        dbDelta( $sql_inst ); // Execute new table creation
    }
}