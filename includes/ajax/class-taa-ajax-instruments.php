<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TAA_Ajax_Instruments {
    public function __construct() {
        // 1. Logged-in Users
        add_action( 'wp_ajax_taa_get_instruments', [ $this, 'get_instruments' ] );
        add_action( 'wp_ajax_taa_save_instruments', [ $this, 'save_instruments' ] );

        // 2. Logged-out Users
        add_action( 'wp_ajax_nopriv_taa_get_instruments', [ $this, 'get_instruments' ] );
        add_action( 'wp_ajax_nopriv_taa_save_instruments', [ $this, 'save_instruments' ] );
    }

    public function get_instruments() {
        global $wpdb;
        $table = $wpdb->prefix . 'taa_instruments';

        // Fetch from DB Table
        $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
        
        $data = [];
        if ($rows) {
            foreach($rows as $r) {
                $data[] = [
                    'name' => $r->name,
                    'lot'  => $r->lot_size,
                    'mode' => $r->mode,
                    'req'  => $r->strike_req,
                ];
            }
        }

        wp_send_json_success($data);
    }

    public function save_instruments() {
        global $wpdb;
        $table = $wpdb->prefix . 'taa_instruments';
        
        $items = isset($_POST['items']) ? $_POST['items'] : [];
        if (!is_array($items)) wp_send_json_error('Invalid Data');

        // STRATEGY: Wipe and Replace (Simplest for "Settings" style lists)
        // This prevents logic errors with ID matching during re-ordering.
        $wpdb->query("TRUNCATE TABLE $table");

        foreach($items as $item) {
            $name = sanitize_text_field($item['name']);
            $lot = intval($item['lot']);
            $mode = sanitize_text_field($item['mode']);
            $req = sanitize_text_field($item['req']);
            
            if(!empty($name)) {
                $wpdb->insert($table, [
                    'name' => strtoupper(trim($name)), // Force Uppercase for consistency
                    'lot_size' => $lot,
                    'mode' => $mode,
                    'strike_req' => $req
                ]);
            }
        }

        wp_send_json_success('Saved to Database Successfully');
    }
}