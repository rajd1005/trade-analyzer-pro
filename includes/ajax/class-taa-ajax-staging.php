<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TAA_Ajax_Staging {

    public function __construct() {
        // 1. Approve & Reject
        add_action( 'wp_ajax_taa_approve_trade', [ $this, 'approve_trade' ] );
        add_action( 'wp_ajax_nopriv_taa_approve_trade', [ $this, 'approve_trade' ] );

        add_action( 'wp_ajax_taa_reject_trade', [ $this, 'reject_trade' ] );
        add_action( 'wp_ajax_nopriv_taa_reject_trade', [ $this, 'reject_trade' ] );

        // 2. Image Handling & Downloads
        add_action( 'wp_ajax_taa_get_image_base64', [ $this, 'get_image_base64' ] );
        add_action( 'wp_ajax_nopriv_taa_get_image_base64', [ $this, 'get_image_base64' ] );

        add_action( 'wp_ajax_taa_download_chart', [ $this, 'download_chart' ] );
        add_action( 'wp_ajax_nopriv_taa_download_chart', [ $this, 'download_chart' ] );

        // 3. Daily Report Data Fetcher (Enhanced)
        add_action( 'wp_ajax_taa_fetch_daily_report', [ $this, 'fetch_daily_report' ] );
        add_action( 'wp_ajax_nopriv_taa_fetch_daily_report', [ $this, 'fetch_daily_report' ] );

        // 4. Hard Delete
        add_action( 'wp_ajax_taa_hard_delete', [ $this, 'hard_delete_trade' ] );
        add_action( 'wp_ajax_nopriv_taa_hard_delete', [ $this, 'hard_delete_trade' ] );
    }

    /**
     * Hard Delete Trade & Image
     */
    public function hard_delete_trade() {
        global $wpdb;
        $id = intval($_POST['id']);
        
        // 1. Get Trade Details to find Image
        $table = $wpdb->prefix . 'taa_staging';
        $row = $wpdb->get_row("SELECT * FROM $table WHERE id = $id");
        
        if (!$row) {
            wp_send_json_error('Trade not found');
        }

        // 2. Delete Main Image
        if (!empty($row->image_url)) {
            $file_path = $this->resolve_path_from_url($row->image_url);
            if ($file_path && file_exists($file_path)) {
                @unlink($file_path);
            }
        }

        // 3. Delete Rejection Image (if exists)
        if (!empty($row->rejection_image)) {
            $file_path_rej = $this->resolve_path_from_url($row->rejection_image);
            if ($file_path_rej && file_exists($file_path_rej)) {
                @unlink($file_path_rej);
            }
        }

        // 4. Delete Database Row
        $result = $wpdb->delete($table, ['id' => $id]);

        if ($result !== false) {
            wp_send_json_success('Trade and Image Deleted Permanently');
        } else {
            wp_send_json_error('Database Error: Could not delete row');
        }
    }

    /**
     * Fetch Approved Trades: First from Staging, then Fallback to Final DB
     */
    public function fetch_daily_report() {
        $date_input = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : current_time('Y-m-d');
        
        global $wpdb;
        $daily_summary = [];

        // 1. Try Fetching from STAGING Table first
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}taa_staging WHERE status LIKE 'APPROVED%' AND DATE(created_at) = '$date_input' ORDER BY id DESC");

        if ($rows && count($rows) > 0) {
            // Data found in Staging
            foreach ($rows as $r) {
                $daily_summary[] = [
                    'dir'        => $r->dir,
                    'symbol'     => $r->chart_name,
                    'strike'     => $r->strike,
                    'profit_raw' => $r->profit,
                    'profit_fmt' => TAA_DB::format_inr($r->profit)
                ];
            }
        } else {
            // 2. Fallback: Fetch from FINAL TABLES (Buy/Sell) if Staging is empty
            $db = TAA_DB::get_connection(); // Support Remote/Local
            if ($db) {
                // --- Process BUY Table ---
                $tbl_buy = get_option('taag_table_buy');
                if ($tbl_buy) {
                    $col_date   = get_option('taag_col_buy_date');
                    $col_name   = get_option('taag_col_buy_name');
                    $col_strike = get_option('taag_col_buy_strike');
                    $col_profit = get_option('taag_col_buy_profit');

                    if ($col_date && $col_profit) {
                        $buy_query = "SELECT * FROM $tbl_buy WHERE DATE($col_date) = '$date_input'";
                        $buy_rows = $db->get_results($buy_query);
                        
                        if ($buy_rows) {
                            foreach ($buy_rows as $b) {
                                $daily_summary[] = [
                                    'dir'        => 'BUY',
                                    'symbol'     => ($col_name && isset($b->$col_name)) ? $b->$col_name : 'Unknown',
                                    'strike'     => ($col_strike && isset($b->$col_strike)) ? $b->$col_strike : '',
                                    'profit_raw' => isset($b->$col_profit) ? $b->$col_profit : 0,
                                    'profit_fmt' => TAA_DB::format_inr(isset($b->$col_profit) ? $b->$col_profit : 0)
                                ];
                            }
                        }
                    }
                }

                // --- Process SELL Table ---
                $tbl_sell = get_option('taag_table_sell');
                if ($tbl_sell) {
                    $col_date   = get_option('taag_col_sell_date');
                    $col_name   = get_option('taag_col_sell_name');
                    $col_strike = get_option('taag_col_sell_strike');
                    $col_profit = get_option('taag_col_sell_profit');

                    if ($col_date && $col_profit) {
                        $sell_query = "SELECT * FROM $tbl_sell WHERE DATE($col_date) = '$date_input'";
                        $sell_rows = $db->get_results($sell_query);
                        
                        if ($sell_rows) {
                            foreach ($sell_rows as $s) {
                                $daily_summary[] = [
                                    'dir'        => 'SELL',
                                    'symbol'     => ($col_name && isset($s->$col_name)) ? $s->$col_name : 'Unknown',
                                    'strike'     => ($col_strike && isset($s->$col_strike)) ? $s->$col_strike : '',
                                    'profit_raw' => isset($s->$col_profit) ? $s->$col_profit : 0,
                                    'profit_fmt' => TAA_DB::format_inr(isset($s->$col_profit) ? $s->$col_profit : 0)
                                ];
                            }
                        }
                    }
                }
            }
        }
        
        // Final Formatting
        $daily_summary = array_reverse($daily_summary);
        
        wp_send_json_success([
            'date_display' => date('d M Y', strtotime($date_input)),
            'trades'       => $daily_summary
        ]);
    }

    /**
     * Download Chart logic with Path Traversal Protection
     */
    public function download_chart() { 
        $url = isset($_GET['req_url']) ? esc_url_raw($_GET['req_url']) : '';
        $name = isset($_GET['req_name']) ? sanitize_file_name($_GET['req_name']) : 'chart.png';
        
        if(empty($url)) wp_die('Invalid Request');

        $file_path = $this->resolve_path_from_url($url);
        
        if ($file_path && file_exists($file_path)) {
            $mime = 'image/png';
            if (function_exists('wp_check_filetype')) {
                $check = wp_check_filetype($file_path);
                if ($check['type']) $mime = $check['type'];
            }

            if (ob_get_level()) ob_end_clean();
            header('Content-Description: File Transfer');
            header('Content-Type: ' . $mime);
            header('Content-Disposition: attachment; filename="' . basename($name) . '"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
        } else {
            wp_die('File not found or access denied.');
        }
    }

    /**
     * Get Image as Base64 (Proxy for Canvas/Painterro)
     */
    public function get_image_base64() {
        $img_url = isset($_POST['img_url']) ? esc_url_raw($_POST['img_url']) : '';
        if (empty($img_url)) wp_send_json_error('No URL provided');

        $file_path = $this->resolve_path_from_url($img_url);

        if (!$file_path || !file_exists($file_path)) {
            wp_send_json_error('Image file not found on server.');
        }

        $image_data = file_get_contents($file_path);
        if ($image_data === false) wp_send_json_error('Could not read image file.');

        $filetype = wp_check_filetype($file_path);
        $mime = $filetype['type'] ? $filetype['type'] : 'image/png';

        $base64 = base64_encode($image_data);
        wp_send_json_success(['base64' => 'data:' . $mime . ';base64,' . $base64]);
    }

    /**
     * Approve Trade Logic (Supports Updates)
     */
    public function approve_trade() {
        global $wpdb;
        $id = intval($_POST['id']);
        $staging_table = $wpdb->prefix . 'taa_staging';
        
        $staging_row = $wpdb->get_row("SELECT * FROM $staging_table WHERE id = $id", ARRAY_A);
        
        if (!$staging_row) wp_send_json_error('Trade not found');
        if (strpos($staging_row['status'], 'APPROVED') !== false) wp_send_json_error('Already Approved');

        $db = TAA_DB::get_connection();
        if (!$db) wp_send_json_error('Final DB Connection Failed');

        $dir = $staging_row['dir'];
        $table_final = ($dir === 'BUY') ? get_option('taag_table_buy') : get_option('taag_table_sell');
        $prefix = ($dir === 'BUY') ? 'taag_col_buy_' : 'taag_col_sell_';
        
        // Map Columns
        $map_data = [];
        $fields = [ 'date'=>'created_at', 'name'=>'chart_name', 'strike'=>'strike', 'lot_size'=>'lot_size', 'total_lots'=>'total_lots', 'total_qty'=>'total_qty', 'entry'=>'entry', 'sl'=>'sl', 'target'=>'target', 'points'=>'points', 'risk'=>'risk', 'profit'=>'profit' ];

        foreach($fields as $map_key => $row_key) {
            $col = get_option($prefix . $map_key);
            if($col) $map_data[$col] = $staging_row[$row_key];
        }

        // Check for Existing Approved Trade (Update Logic)
        $trade_date = date('Y-m-d', strtotime($staging_row['created_at']));
        $prev_approved = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $staging_table WHERE status LIKE 'APPROVED%%' AND chart_name = %s AND strike = %s AND dir = %s AND DATE(created_at) = %s AND id != %d LIMIT 1", $staging_row['chart_name'], $staging_row['strike'], $staging_row['dir'], $trade_date, $id) );

        $msg = '';

        if ($prev_approved) {
            // UPDATE Existing Trade
            $update_where = [ get_option($prefix.'name') => $staging_row['chart_name'], get_option($prefix.'strike') => $staging_row['strike'] ];
            
            // Update Final DB
            $db->update($table_final, $map_data, $update_where); 
            
            // Update Old Staging Row to match new data
            $update_staging_data = [ 
                'entry' => $staging_row['entry'], 
                'target' => $staging_row['target'], 
                'sl' => $staging_row['sl'], 
                'points' => $staging_row['points'], 
                'risk' => $staging_row['risk'], 
                'profit' => $staging_row['profit'], 
                'rr_ratio' => $staging_row['rr_ratio'], 
                'image_url' => $staging_row['image_url'], 
                'status' => 'APPROVED (UPDATED)', 
                'created_at' => $staging_row['created_at'] 
            ];
            $wpdb->update($staging_table, $update_staging_data, ['id' => $prev_approved->id]);
            
            // Delete the temporary pending row
            $wpdb->delete($staging_table, ['id' => $id]);
            $msg = 'Trade Updated Successfully';
        } else {
            // INSERT New Trade
            $result = $db->insert($table_final, $map_data);
            if ($result !== false) {
                $wpdb->update($staging_table, ['status' => 'APPROVED'], ['id' => $id]);
                $this->cleanup_duplicates($id, $staging_row['chart_name'], $staging_row['strike'], $staging_row['dir']);
                $msg = 'Approved Successfully';
            } else {
                wp_send_json_error('Final DB Insert Failed: ' . $db->last_error);
            }
        }

        // Email Notification
        if (get_option('taag_email_enable') == '1') {
            $admin_email = get_option('admin_email');
            if ($admin_email) {
                $sub_tpl = get_option('taag_email_sub_approve', 'Approved: {name}');
                $body_tpl = get_option('taag_email_body_approve', 'Trade {name} Approved.');
                $replacements = [ '{name}' => $staging_row['chart_name'], '{dir}' => $staging_row['dir'], '{profit}' => $staging_row['profit'] ];
                $subject = str_replace(array_keys($replacements), array_values($replacements), $sub_tpl);
                $body = str_replace(array_keys($replacements), array_values($replacements), $body_tpl);
                $headers = []; $cc = get_option('taag_email_cc'); $bcc = get_option('taag_email_bcc');
                if($cc) $headers[] = 'Cc: ' . $cc; if($bcc) $headers[] = 'Bcc: ' . $bcc;
                wp_mail($admin_email, $subject, $body, $headers);
            }
        }

        // Telegram Notification
        $current_user = wp_get_current_user();
        $user_name = $current_user->exists() ? $current_user->display_name : 'Admin';
        $full_name = $staging_row['chart_name'];
        if(!empty($staging_row['strike'])) $full_name .= ' ' . $staging_row['strike'];

        $tg_msg = "✅ *Trade Approved*\n\n";
        $tg_msg .= "👤 *Approver:* " . $user_name . "\n";
        $tg_msg .= "📈 *Instrument:* " . $full_name . "\n";
        $tg_msg .= "↕️ *Action:* " . $staging_row['dir'] . "\n";
        $tg_msg .= "⚖️ *RR:* 1:" . $staging_row['rr_ratio'] . "\n"; 
        $tg_msg .= "⚠️ *Risk:* " . TAA_DB::format_inr($staging_row['risk']) . "\n"; 
        $tg_msg .= "💰 *Profit:* " . TAA_DB::format_inr($staging_row['profit']) . "\n";
        
        TAA_DB::send_telegram($tg_msg, $staging_row['image_url']);

        wp_send_json_success($msg);
    }

    /**
     * Reject Trade Logic
     */
    public function reject_trade() {
        global $wpdb;
        $id = intval($_POST['id']);
        $note = isset($_POST['note']) ? sanitize_textarea_field($_POST['note']) : '';
        $base64_img = isset($_POST['image_data']) ? $_POST['image_data'] : '';

        $row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}taa_staging WHERE id = $id");
        if(!$row) wp_send_json_error('Trade not found');

        $final_image_url = '';
        if (!empty($base64_img)) {
            $upload_dir = wp_upload_dir();
            $image_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64_img));
            $filename = 'rejected_feedback_' . $id . '_' . time() . '.png';
            $file_path = $upload_dir['path'] . '/' . $filename;
            if (file_put_contents($file_path, $image_data)) {
                $final_image_url = $upload_dir['url'] . '/' . $filename;
            }
        }

        $update_data = [
            'status' => 'REJECTED',
            'rejection_note' => $note,
            'rejection_image' => $final_image_url
        ];

        $wpdb->update($wpdb->prefix . 'taa_staging', $update_data, ['id' => $id]);
        
        // Also reject any duplicates
        $this->cleanup_duplicates($id, $row->chart_name, $row->strike, $row->dir);

        // Email Notification
        if (get_option('taag_email_enable') == '1') {
            $admin_email = get_option('admin_email');
            if ($admin_email) {
                $sub_tpl = get_option('taag_email_sub_reject', 'Rejected: {name}');
                $body_tpl = get_option('taag_email_body_reject', 'Trade {name} Rejected. Reason: {reason}');
                $replacements = [ '{name}' => $row->chart_name, '{reason}' => $note ];
                $subject = str_replace(array_keys($replacements), array_values($replacements), $sub_tpl);
                $body = str_replace(array_keys($replacements), array_values($replacements), $body_tpl);
                wp_mail($admin_email, $subject, $body);
            }
        }

        // Telegram Notification
        $current_user = wp_get_current_user();
        $user_name = $current_user->exists() ? $current_user->display_name : 'Admin';
        $full_name = $row->chart_name;
        if(!empty($row->strike)) $full_name .= ' ' . $row->strike;

        $tg_msg = "❌ *Trade Rejected*\n\n";
        $tg_msg .= "👤 *Reviewer:* " . $user_name . "\n";
        $tg_msg .= "📈 *Instrument:* " . $full_name . "\n";
        $tg_msg .= "📝 *Reason:* " . $note . "\n";
        
        $img_to_send = !empty($final_image_url) ? $final_image_url : $row->image_url;

        TAA_DB::send_telegram($tg_msg, $img_to_send);
        
        wp_send_json_success();
    }

    /**
     * Helper to map URL to local file path (Security)
     */
    private function resolve_path_from_url($url) {
        $upload_info = wp_upload_dir();
        $base_url = $upload_info['baseurl'];
        $base_dir = $upload_info['basedir'];

        $clean_url = str_replace(['http:', 'https:'], '', $url);
        $clean_base = str_replace(['http:', 'https:'], '', $base_url);
        $clean_url = strtok($clean_url, '?');

        if (strpos($clean_url, '/wp-content/uploads/') === false) {
            return false; 
        }

        $rel_path = str_replace($clean_base, '', $clean_url);
        $rel_path = urldecode($rel_path);
        $file_path = $base_dir . $rel_path;
        
        return wp_normalize_path($file_path);
    }

    /**
     * Mark duplicates as REJECTED automatically
     */
    private function cleanup_duplicates($keep_id, $name, $strike, $dir) {
        global $wpdb;
        $today = current_time('Y-m-d');
        $table = $wpdb->prefix . 'taa_staging';
        // Delete other pending requests for same trade on same day
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM $table WHERE id != %d AND chart_name = %s AND strike = %s AND dir = %s AND DATE(created_at) = %s AND status = 'PENDING'",
            $keep_id, $name, $strike, $dir, $today
        ));
    }
}