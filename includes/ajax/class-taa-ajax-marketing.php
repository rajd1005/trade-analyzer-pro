<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TAA_Ajax_Marketing {

    public function __construct() {
        add_action( 'wp_ajax_taa_send_marketing_telegram', [ $this, 'send_to_telegram' ] );
    }

    public function send_to_telegram() {
        // 1. Security Check (Nonce & Capability)
        check_ajax_referer( 'taa_nonce', 'security' );
        
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Unauthorized permission level.' );
        }

        // 2. Validate Configuration
        $token   = get_option( 'taag_telegram_token' );
        $chat_id = get_option( 'taag_telegram_chat_id' );

        if ( empty( $token ) || empty( $chat_id ) ) {
            wp_send_json_error( 'Telegram Token or Chat ID is missing in settings.' );
        }

        // 3. Validate File
        if ( empty( $_FILES['image'] ) || $_FILES['image']['error'] !== UPLOAD_ERR_OK ) {
            wp_send_json_error( 'Image data not received or upload failed.' );
        }

        $caption = isset( $_POST['caption'] ) ? sanitize_text_field( $_POST['caption'] ) : 'Trade Setup';
        
        // 4. Send to Telegram via CURL (Reliable for file uploads)
        $response = $this->post_image_to_telegram( $token, $chat_id, $_FILES['image']['tmp_name'], $caption );

        if ( $response['success'] ) {
            wp_send_json_success( 'Successfully sent to Telegram!' );
        } else {
            wp_send_json_error( 'Telegram API Error: ' . $response['message'] );
        }
    }

    /**
     * Helper to send multipart/form-data to Telegram
     */
    private function post_image_to_telegram( $token, $chat_id, $file_path, $caption ) {
        $url = "https://api.telegram.org/bot{$token}/sendPhoto";

        if ( ! function_exists( 'curl_init' ) ) {
            return [ 'success' => false, 'message' => 'CURL is not enabled on this server.' ];
        }

        $cfile = new CURLFile( $file_path, 'image/jpeg', 'trade_setup.jpg' );

        $data = [
            'chat_id' => $chat_id,
            'photo'   => $cfile,
            'caption' => $caption
        ];

        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, 1 );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false ); // Optional: set true if certificates are configured
        
        $result = curl_exec( $ch );
        $error  = curl_error( $ch );
        curl_close( $ch );

        if ( $error ) {
            return [ 'success' => false, 'message' => $error ];
        }

        $json = json_decode( $result, true );

        if ( isset( $json['ok'] ) && $json['ok'] === true ) {
            return [ 'success' => true ];
        } else {
            return [ 'success' => false, 'message' => isset($json['description']) ? $json['description'] : 'Unknown error' ];
        }
    }
}