<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TAA_Ajax_Instruments {
    public function __construct() {
        // 1. Logged-in Users
        add_action( 'wp_ajax_taa_get_instruments', [ $this, 'get_instruments' ] );
        add_action( 'wp_ajax_taa_save_instruments', [ $this, 'save_instruments' ] );

        // 2. Logged-out Users (Fixes "Failed to load data" for visitors)
        add_action( 'wp_ajax_nopriv_taa_get_instruments', [ $this, 'get_instruments' ] );
        add_action( 'wp_ajax_nopriv_taa_save_instruments', [ $this, 'save_instruments' ] );
    }

    public function get_instruments() {
        // NO RESTRICTIONS: Available to everyone (Admin, User, Guest)
        
        $raw = get_option('taag_instruments_list', '');
        $lines = array_filter(explode("\n", $raw));
        $data = [];

        foreach($lines as $line) {
            $parts = explode('|', trim($line));
            if(count($parts) >= 2) {
                $data[] = [
                    'name' => trim($parts[0]),
                    'lot'  => trim($parts[1]),
                    'mode' => isset($parts[2]) ? trim($parts[2]) : 'BOTH',
                    'req'  => isset($parts[3]) ? trim($parts[3]) : 'NO',
                ];
            }
        }

        wp_send_json_success($data);
    }

    public function save_instruments() {
        // NO RESTRICTIONS: No Nonce check, No Capability check.
        // Anyone can save data.
        
        $items = isset($_POST['items']) ? $_POST['items'] : [];
        if (!is_array($items)) wp_send_json_error('Invalid Data');

        $lines = [];
        foreach($items as $item) {
            $name = sanitize_text_field($item['name']);
            $lot = intval($item['lot']);
            $mode = sanitize_text_field($item['mode']);
            $req = sanitize_text_field($item['req']);
            
            if(!empty($name)) {
                $lines[] = "$name|$lot|$mode|$req";
            }
        }

        $final_str = implode("\n", $lines);
        update_option('taag_instruments_list', $final_str);

        wp_send_json_success('Saved Successfully');
    }
}