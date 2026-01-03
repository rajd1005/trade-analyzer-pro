<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TAA_Ajax_Analyzer {
    public function __construct() {
        add_action( 'wp_ajax_taa_analyze_image', [ $this, 'analyze_image' ] );
        add_action( 'wp_ajax_nopriv_taa_analyze_image', [ $this, 'analyze_image' ] );
        add_action( 'wp_ajax_taa_save_to_staging', [ $this, 'save_to_staging' ] );
        add_action( 'wp_ajax_nopriv_taa_save_to_staging', [ $this, 'save_to_staging' ] );
    }

    public function analyze_image() {
        if ( ! isset( $_FILES['image'] ) ) wp_send_json_error( 'No image uploaded' );
        $api_key = get_option( 'taag_api_key' );
        if ( ! $api_key ) wp_send_json_error( 'API Key missing' );

        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );
        $attachment_id = media_handle_upload( 'image', 0 );
        if ( is_wp_error( $attachment_id ) ) wp_send_json_error( 'Upload Failed' );

        $image_url = wp_get_attachment_url( $attachment_id );
        $file_path = get_attached_file( $attachment_id );
        $mime_type = wp_check_filetype( $file_path )['type'];
        
        $b64 = base64_encode( file_get_contents( $file_path ) );
        
        $prompt = "Analyze this trading chart. Return ONLY a valid JSON object. 
        Format: { \"chart_name\": \"...\", \"entry\": 0.0, \"sl\": 0.0, \"target\": 0.0, \"bot\": 0.0, \"mid\": 0.0, \"top\": 0.0, \"direction\": \"BUY/SELL\" }
        If price labels are visible, use them. 'bot', 'mid', 'top' are the lowest, middle, and highest key price lines visible.";

        if (isset($_POST['direction'])) {
            $prompt .= " The user intends to " . $_POST['direction'] . ". Focus on setups for this direction.";
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/" . get_option('taag_model_id', 'gemini-2.0-flash') . ":generateContent?key=" . $api_key;
        
        $payload = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt],
                        ["inline_data" => ["mime_type" => $mime_type, "data" => $b64]]
                    ]
                ]
            ]
        ];

        $args = [
            'body' => json_encode($payload),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 45
        ];

        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) wp_send_json_error('API Error: ' . $response->get_error_message());
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            wp_send_json_error('AI Analysis Failed');
        }

        $raw_text = $data['candidates'][0]['content']['parts'][0]['text'];
        $clean_json = str_replace(['```json', '```'], '', $raw_text);
        $parsed = json_decode($clean_json, true);

        if (!$parsed) wp_send_json_error('Could not parse AI response');

        $parsed['image_url'] = $image_url;
        wp_send_json_success($parsed);
    }

    public function save_to_staging() {
        global $wpdb;

        $final_image_url = isset($_POST['image_url']) ? esc_url_raw($_POST['image_url']) : '';
        if (isset($_FILES['manual_image'])) {
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/media.php' );
            $att_id = media_handle_upload('manual_image', 0);
            if (!is_wp_error($att_id)) {
                $final_image_url = wp_get_attachment_url($att_id);
            }
        }
        
        $data = [
            'created_at' => current_time('mysql'),
            'status' => 'PENDING',
            'dir' => sanitize_text_field($_POST['dir']),
            'chart_name' => sanitize_text_field($_POST['name']),
            'strike' => sanitize_text_field($_POST['strike']),
            'entry' => floatval($_POST['entry']),
            'target' => floatval($_POST['target']),
            'sl' => floatval($_POST['sl']),
            'points' => floatval($_POST['points']),
            'profit' => floatval($_POST['profit']),
            'risk' => floatval($_POST['risk']),
            'lot_size' => intval($_POST['lot_size']),
            'total_lots' => intval($_POST['total_lots']),
            'total_qty' => intval($_POST['lot_size']) * intval($_POST['total_lots']),
            'rr_ratio' => sanitize_text_field($_POST['rr_ratio']),
            'image_url' => $final_image_url
        ];

        $current_user = wp_get_current_user();
        $user_name = $current_user->exists() ? $current_user->display_name : 'Guest';
        $allowed_roles = get_option('taag_direct_add_roles', []);
        if (!is_array($allowed_roles)) $allowed_roles = [];
        
        $is_direct_add = false;
        if ( is_user_logged_in() && !empty($allowed_roles) ) {
            foreach($current_user->roles as $role) {
                if (in_array($role, $allowed_roles)) {
                    $is_direct_add = true;
                    $data['status'] = 'APPROVED';
                    break;
                }
            }
        }
        
        $result = $wpdb->insert( $wpdb->prefix . 'taa_staging', $data );
        if($result === false) {
            wp_send_json_error('DB Insert Failed: ' . $wpdb->last_error);
        }

        if ($is_direct_add) {
            $db = TAA_DB::get_connection();
            if ($db) {
                $dir = $data['dir'];
                $table_final = ($dir === 'BUY') ? get_option('taag_table_buy') : get_option('taag_table_sell');
                $prefix = ($dir === 'BUY') ? 'taag_col_buy_' : 'taag_col_sell_';
                $map_data = [];
                $fields = [ 'date'=>'created_at', 'name'=>'chart_name', 'strike'=>'strike', 'lot_size'=>'lot_size', 'total_lots'=>'total_lots', 'total_qty'=>'total_qty', 'entry'=>'entry', 'sl'=>'sl', 'target'=>'target', 'points'=>'points', 'risk'=>'risk', 'profit'=>'profit' ];
                foreach($fields as $map_key => $row_key) {
                    $col = get_option($prefix . $map_key);
                    if($col) $map_data[$col] = $data[$row_key];
                }
                $db->insert($table_final, $map_data);
            }
        }

        if (get_option('taag_email_enable') == '1') {
            $admin_email = get_option('admin_email');
            if ($admin_email) {
                if ($is_direct_add) {
                    $sub_tpl = get_option('taag_email_sub_approve', 'Trade Approved: {name}');
                    $body_tpl = get_option('taag_email_body_approve', 'The trade for {name} has been approved.');
                    $replacements = [ '{name}' => $data['chart_name'], '{dir}' => $data['dir'], '{profit}' => $data['profit'] ];
                    $headers = []; $cc = get_option('taag_email_cc'); $bcc = get_option('taag_email_bcc');
                } else {
                    $sub_tpl = get_option('taag_email_sub_stage', 'New Trade Staged: {name}');
                    $body_tpl = get_option('taag_email_body_stage', "New {dir} Trade Staged.\nEntry: {entry}");
                    $replacements = [ '{name}' => $data['chart_name'], '{dir}' => $data['dir'], '{entry}' => $data['entry'] ];
                    $headers = []; $cc = get_option('taag_stage_cc'); $bcc = get_option('taag_stage_bcc');
                }
                $subject = str_replace(array_keys($replacements), array_values($replacements), $sub_tpl);
                $body = str_replace(array_keys($replacements), array_values($replacements), $body_tpl);
                if($cc) $headers[] = 'Cc: ' . $cc; if($bcc) $headers[] = 'Bcc: ' . $bcc;
                wp_mail($admin_email, $subject, $body, $headers);
            }
        }

        // Telegram with Image, RR, and Risk
        $profit_fmt = TAA_DB::format_inr($data['profit']);
        $risk_fmt = TAA_DB::format_inr($data['risk']);
        $full_name = $data['chart_name'];
        if(!empty($data['strike'])) $full_name .= ' ' . $data['strike'];

        if ($is_direct_add) {
            $tg_msg = "✅ *Trade Approved Directly*\n\n";
            $tg_msg .= "👤 *User:* " . $user_name . "\n";
            $tg_msg .= "📈 *Instrument:* " . $full_name . "\n";
            $tg_msg .= "↕️ *Action:* " . $data['dir'] . "\n";
            $tg_msg .= "🎯 *Entry:* " . $data['entry'] . "\n";
            $tg_msg .= "🛑 *SL:* " . $data['sl'] . "\n";
            $tg_msg .= "🎯 *Target:* " . $data['target'] . "\n";
            $tg_msg .= "⚖️ *RR:* 1:" . $data['rr_ratio'] . "\n"; 
            $tg_msg .= "⚠️ *Risk:* " . $risk_fmt . "\n"; // [NEW]
            $tg_msg .= "💰 *Est. Profit:* " . $profit_fmt . "\n";
        } else {
            $tg_msg = "🆕 *New Trade Staged*\n\n";
            $tg_msg .= "👤 *User:* " . $user_name . "\n";
            $tg_msg .= "📈 *Instrument:* " . $full_name . "\n";
            $tg_msg .= "↕️ *Action:* " . $data['dir'] . "\n";
            $tg_msg .= "🎯 *Entry:* " . $data['entry'] . "\n";
            $tg_msg .= "🛑 *SL:* " . $data['sl'] . "\n";
            $tg_msg .= "⚖️ *RR:* 1:" . $data['rr_ratio'] . "\n"; 
            $tg_msg .= "💰 *Proj. Profit:* " . $profit_fmt . "\n";
            $tg_msg .= "⚠️ *Risk:* " . $risk_fmt . "\n";
        }

        TAA_DB::send_telegram($tg_msg, $final_image_url);

        $msg = $is_direct_add ? 'Approved Directly!' : 'Trade Staged Successfully';
        wp_send_json_success($msg);
    }
}