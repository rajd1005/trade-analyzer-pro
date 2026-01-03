<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TAA_Ajax_Instruments {
    public function __construct() {
        add_action( 'wp_ajax_taa_get_instruments', [ $this, 'get_instruments' ] );
        add_action( 'wp_ajax_taa_save_instruments', [ $this, 'save_instruments' ] );
        // No nopriv hooks - strictly Admin only
    }

    public function get_instruments() {
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        
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
        check_ajax_referer( 'taa_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

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