<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TAA_DB {
    public static function get_connection() {
        $type = get_option('taag_db_type', 'local');
        if ( $type === 'local' ) {
            global $wpdb;
            return $wpdb;
        } else {
            $host = get_option('taag_db_host');
            $user = get_option('taag_db_user');
            $pass = get_option('taag_db_pass');
            $name = get_option('taag_db_name');
            
            if ( $host && $user && $name ) {
                $remote_db = new wpdb( $user, $pass, $name, $host );
                if ( ! empty( $remote_db->error ) ) return false;
                return $remote_db;
            }
        }
        return false;
    }

    public static function get_columns( $table ) {
        $db = self::get_connection();
        if ( ! $db || empty( $table ) ) return [];
        $check = $db->get_var( $db->prepare( "SHOW TABLES LIKE %s", $table ) );
        if ( ! $check ) return [];
        return $db->get_col( "DESCRIBE $table" );
    }

    public static function format_inr( $num ) {
        $num = round((float)$num);
        $is_neg = ($num < 0);
        $num_str = (string) abs($num);
        
        $last3 = substr($num_str, -3);
        $other = substr($num_str, 0, -3);
        
        if ($other != '') {
            $last3 = ',' . $last3;
        }
        
        $res = preg_replace("/\B(?=(\d{2})+(?!\d))/", ",", $other) . $last3;
        
        return '₹' . ($is_neg ? '-' : '') . $res;
    }

    // [UPDATED] Telegram Sender with Image Support
    public static function send_telegram( $message, $image_url = '' ) {
        $token = get_option('taag_telegram_token');
        $chat_id = get_option('taag_telegram_chat_id');

        if ( empty($token) || empty($chat_id) || empty($message) ) {
            return false;
        }

        $args = [ 'timeout' => 20 ];
        
        // Decide endpoint based on Image presence
        if ( ! empty($image_url) ) {
            $url = "https://api.telegram.org/bot{$token}/sendPhoto";
            $args['body'] = [
                'chat_id' => $chat_id,
                'photo'   => $image_url,
                'caption' => $message,
                'parse_mode' => 'Markdown'
            ];
        } else {
            $url = "https://api.telegram.org/bot{$token}/sendMessage";
            $args['body'] = [
                'chat_id' => $chat_id,
                'text'    => $message,
                'parse_mode' => 'Markdown'
            ];
        }

        $response = wp_remote_post( $url, $args );

        if ( is_wp_error( $response ) ) {
            error_log('TAA Telegram Error: ' . $response->get_error_message());
            return false;
        }

        return true;
    }
}