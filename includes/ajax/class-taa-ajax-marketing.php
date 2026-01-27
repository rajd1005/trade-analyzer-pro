<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TAA_Ajax_Marketing {

    public function __construct() {
        // 1. Telegram (Manual Upload from device)
        add_action( 'wp_ajax_taa_send_marketing_telegram', [ $this, 'send_to_telegram' ] );
        
        // 2. Remote Publish (Standard Upload)
        add_action( 'wp_ajax_taa_publish_marketing_image', [ $this, 'publish_image_remote' ] );
        
        // 3. Remote Delete
        add_action( 'wp_ajax_taa_delete_published_image', [ $this, 'delete_image_remote' ] );
        
        // 4. Remote Fetch (Proxy for Gallery)
        add_action( 'wp_ajax_taa_load_published_gallery', [ $this, 'load_gallery_remote' ] );
        add_action( 'wp_ajax_nopriv_taa_load_published_gallery', [ $this, 'load_gallery_remote' ] );

        // 5. Send Published Image to Telegram (Table Button ✈)
        add_action( 'wp_ajax_taa_send_published_telegram', [ $this, 'send_published_telegram' ] );

        // 6. Force Download Marketing Image (Table Button ⬇)
        add_action( 'wp_ajax_taa_download_marketing_image', [ $this, 'download_marketing_image' ] );
        add_action( 'wp_ajax_nopriv_taa_download_marketing_image', [ $this, 'download_marketing_image' ] );
    }

    /**
     * [FIXED] Force Download Remote Marketing Image
     * Handles the ⬇ button. Appends ID to filename to prevent conflicts.
     */
    public function download_marketing_image() {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$id) wp_die('Invalid ID');

        global $wpdb;
        $row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}taa_staging WHERE id = $id");
        
        if (!$row || empty($row->marketing_url)) {
            wp_die('Image not found or not published.');
        }

        // Clean Filename Construction
        // Pattern: INST_STRIKE_DIR_DATE_ID.jpg
        $name_part = preg_replace('/[^A-Z0-9]/', '', strtoupper($row->chart_name));
        $strike_part = !empty($row->strike) && $row->strike !== '-' ? '_' . preg_replace('/[^A-Z0-9]/', '', strtoupper($row->strike)) : '';
        $dir_part = '_' . strtoupper($row->dir);
        $date_part = '_' . date('Y-m-d', strtotime($row->created_at));
        
        // [CRITICAL] Unique ID prevents conflicts if multiple trades have same name
        $id_part = '_ID' . $id; 
        
        $filename = $name_part . $strike_part . $dir_part . $date_part . $id_part . '.jpg';

        // Stream Content
        $response = wp_remote_get($row->marketing_url, [
            'timeout' => 30,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            wp_die('Error fetching remote image: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        
        header('Content-Description: File Transfer');
        header('Content-Type: image/jpeg');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($body));
        
        echo $body;
        exit;
    }

     /**
     * Send Published Image to Telegram
     * [FIXED] Downloads remote image to temp file -> Uploads to Telegram
     * This fixes the "Intermittent Failure" caused by Telegram's server timeouts.
     */
    public function send_published_telegram() {
        check_ajax_referer( 'taa_nonce', 'security' );
        
        $id = intval($_POST['id']);
        if (!$id) wp_send_json_error('Invalid ID');

        global $wpdb;
        $row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}taa_staging WHERE id = $id");
        if (!$row) wp_send_json_error('Trade not found');

        if (empty($row->marketing_url)) wp_send_json_error('Image not published yet.');

        // 1. Prepare Caption
        $template = get_option('taag_telegram_template', "🚀 *{chart_name}* ({dir})\n\n💰 Profit: {profit}\n⚖️ RR: {rr}\n🎯 Strike: {strike}");
        
        $replacements = [
            '{chart_name}' => $row->chart_name,
            '{dir}'        => $row->dir,
            '{profit}'     => TAA_DB::format_inr($row->profit),
            '{rr}'         => '1:' . $row->rr_ratio,
            '{strike}'     => $row->strike ? $row->strike : '-'
        ];

        $message = str_replace(array_keys($replacements), array_values($replacements), $template);

        // 2. Download Remote Image to Temp File
        $temp_file = tempnam(sys_get_temp_dir(), 'taa_tg_');
        $response = wp_remote_get($row->marketing_url, array('timeout' => 30, 'sslverify' => false));

        if ( is_wp_error( $response ) ) {
            @unlink($temp_file);
            wp_send_json_error('Failed to download image from server: ' . $response->get_error_message());
        }

        $image_data = wp_remote_retrieve_body($response);
        if ( empty($image_data) ) {
            @unlink($temp_file);
            wp_send_json_error('Empty image data received.');
        }

        file_put_contents($temp_file, $image_data);

        // 3. Send using the robust cURL method
        $token = get_option( 'taag_telegram_token' );
        $chat_id = get_option( 'taag_telegram_chat_id' );

        if ( empty($token) || empty($chat_id) ) {
            @unlink($temp_file);
            wp_send_json_error('Telegram settings missing.');
        }

        // Reuse your existing private method which handles CURLFile correctly
        $res = $this->post_image_to_telegram($token, $chat_id, $temp_file, $message);

        // 4. Cleanup
        @unlink($temp_file);

        if ($res['success']) {
            wp_send_json_success('Sent to Telegram');
        } else {
            wp_send_json_error('Telegram Error: ' . ($res['message'] ?? 'Unknown'));
        }
    }

    /**
     * Publish Image (Standard Upload)
     * [Logic: Prevents re-publishing if 'marketing_url' exists]
     */
    public function publish_image_remote() {
        check_ajax_referer( 'taa_nonce', 'security' );
        if ( ! is_user_logged_in() ) wp_send_json_error( 'Please login' );
        
        if ( empty( $_FILES['file'] ) ) wp_send_json_error( 'No file received' );

        $trade_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        // Check if already published
        if ($trade_id > 0) {
            global $wpdb;
            $existing = $wpdb->get_var( $wpdb->prepare("SELECT marketing_url FROM {$wpdb->prefix}taa_staging WHERE id = %d", $trade_id) );
            if (!empty($existing)) {
                wp_send_json_error('ALREADY PUBLISHED. Please delete the existing image from the Gallery first.');
            }
        }

        // 1. Prepare Data
        $raw_name = sanitize_text_field( $_POST['name'] );
        $name = trim(strtoupper($raw_name));
        $date = sanitize_text_field( $_POST['date'] ); 

        // 2. Direct Upload
        $remote_url = 'https://image.rdalgo.in/wp-json/rdalgo/v1/upload';
        $boundary = wp_generate_password( 24 );
        $headers  = array( 'content-type' => 'multipart/form-data; boundary=' . $boundary );
        $payload  = '';

        $fields = array( 'name' => $name, 'date' => $date );
        foreach ( $fields as $key => $value ) {
            $payload .= '--' . $boundary . "\r\n";
            $payload .= 'Content-Disposition: form-data; name="' . $key . '"' . "\r\n\r\n";
            $payload .= $value . "\r\n";
        }

        if ( isset($_FILES['file']['tmp_name']) && file_exists( $_FILES['file']['tmp_name'] ) ) {
            $payload .= '--' . $boundary . "\r\n";
            $payload .= 'Content-Disposition: form-data; name="file"; filename="' . $_FILES['file']['name'] . '"' . "\r\n";
            $payload .= 'Content-Type: ' . $_FILES['file']['type'] . "\r\n\r\n";
            $payload .= file_get_contents( $_FILES['file']['tmp_name'] ) . "\r\n";
        }
        $payload .= '--' . $boundary . '--';

        $response = wp_remote_post( $remote_url, array(
            'headers' => $headers, 'body' => $payload, 'timeout' => 45
        ));

        if ( is_wp_error( $response ) ) wp_send_json_error( 'Remote Error: ' . $response->get_error_message() );
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( isset( $data['success'] ) && $data['success'] ) {
            // Update Local Database
            if ($trade_id > 0) {
                global $wpdb;
                $mkt_url = '';
                if (!empty($data['image_url'])) $mkt_url = $data['image_url'];
                elseif (!empty($data['url'])) $mkt_url = $data['url'];
                elseif (isset($data['data']['image_url'])) $mkt_url = $data['data']['image_url'];
                elseif (isset($data['data']['url'])) $mkt_url = $data['data']['url'];

                if (!empty($mkt_url)) {
                    $wpdb->update(
                        $wpdb->prefix . 'taa_staging',
                        ['marketing_url' => $mkt_url],
                        ['id' => $trade_id]
                    );
                }
            }
            wp_send_json_success( $data );
        } else {
            wp_send_json_error( isset($data['message']) ? $data['message'] : 'Upload Failed' );
        }
    }

    /**
     * Load Gallery
     */
    public function load_gallery_remote() {
        $date = sanitize_text_field( $_POST['date'] );
        if(empty($date)) $date = current_time('Y-m-d');

        $remote_url = 'https://image.rdalgo.in/wp-json/rdalgo/v1/images?date=' . $date . '&_t=' . time();
        $response = wp_remote_get( $remote_url, array( 'timeout' => 15 ) );

        if ( is_wp_error( $response ) ) wp_send_json_error( 'Fetch Error: ' . $response->get_error_message() );
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( is_null( $data ) ) wp_send_json_error( 'Invalid response from remote.' );
        wp_send_json_success( $data );
    }

    /**
     * Delete Image
     */
    public function delete_image_remote() {
        check_ajax_referer( 'taa_nonce', 'security' );
        if ( ! is_user_logged_in() ) wp_send_json_error( 'Unauthorized' );

        $id = intval( $_POST['id'] ); 
        $file_date = sanitize_text_field( $_POST['trade_date'] ); 
        $image_url = isset($_POST['image_url']) ? esc_url_raw($_POST['image_url']) : ''; 
        
        if ( ! $id ) wp_send_json_error( 'ID required' );

        $current_user = wp_get_current_user();
        $allowed_roles = get_option('taag_direct_add_roles', []);
        if(!is_array($allowed_roles)) $allowed_roles = [];
        
        $is_privileged = false;
        if ( current_user_can('manage_options') ) $is_privileged = true;
        else {
            foreach($current_user->roles as $role) {
                if(in_array($role, $allowed_roles)) { $is_privileged = true; break; }
            }
        }

        $today = current_time('Y-m-d');
        if ( $file_date !== $today && ! $is_privileged ) {
            wp_send_json_error( 'Permission Denied: Past images restricted.' );
        }

        $remote_url = 'https://image.rdalgo.in/wp-json/rdalgo/v1/delete';
        $response = wp_remote_post( $remote_url, array( 'body' => array( 'id' => $id ) ) );

        if ( is_wp_error( $response ) ) wp_send_json_error( 'Remote Connect Error' );
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( isset( $data['success'] ) && $data['success'] ) {
            // Remove from local database to reset "Publish" state
            if (!empty($image_url)) {
                global $wpdb;
                $table = $wpdb->prefix . 'taa_staging';
                $wpdb->query( $wpdb->prepare( 
                    "UPDATE $table SET marketing_url = '' WHERE marketing_url = %s", 
                    $image_url 
                ) );
            }
            wp_send_json_success( 'Deleted' );
        }
        else {
            wp_send_json_error( isset($data['message']) ? $data['message'] : 'Delete Failed' );
        }
    }

    /**
     * Send to Telegram (Manual)
     */
    public function send_to_telegram() {
        check_ajax_referer( 'taa_nonce', 'security' );
        
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }
        $token = get_option( 'taag_telegram_token' );
        $chat_id = get_option( 'taag_telegram_chat_id' );
        if ( empty($token) || empty($chat_id) || empty($_FILES['image']) ) wp_send_json_error( 'Missing Data' );

        $caption = isset( $_POST['caption'] ) ? sanitize_text_field( $_POST['caption'] ) : 'Trade Setup';
        $response = $this->post_image_to_telegram( $token, $chat_id, $_FILES['image']['tmp_name'], $caption );

        if ( $response['success'] ) wp_send_json_success( 'Sent to Telegram!' );
        else wp_send_json_error( 'Telegram Error: ' . $response['message'] );
    }

    private function post_image_to_telegram( $token, $chat_id, $file_path, $caption ) {
        $url = "https://api.telegram.org/bot{$token}/sendPhoto";
        if ( ! function_exists( 'curl_init' ) ) return [ 'success' => false, 'message' => 'CURL missing' ];
        $cfile = new CURLFile( $file_path, 'image/jpeg', 'trade.jpg' );
        $data = [ 'chat_id' => $chat_id, 'photo' => $cfile, 'caption' => $caption, 'parse_mode' => 'Markdown' ];
        $ch = curl_init(); curl_setopt($ch, CURLOPT_URL, $url); curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
        if ($err) return [ 'success' => false, 'message' => $err ];
        $json = json_decode($res, true);
        return ( isset($json['ok']) && $json['ok'] ) ? [ 'success' => true ] : [ 'success' => false, 'message' => $json['description'] ?? 'Err' ];
    }
}