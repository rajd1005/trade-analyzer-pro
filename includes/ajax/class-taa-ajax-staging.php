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

        // 3. Daily Report Data Fetcher
        add_action( 'wp_ajax_taa_fetch_daily_report', [ $this, 'fetch_daily_report' ] );
        add_action( 'wp_ajax_nopriv_taa_fetch_daily_report', [ $this, 'fetch_daily_report' ] );

        // 4. Hard Delete
        add_action( 'wp_ajax_taa_hard_delete', [ $this, 'hard_delete_trade' ] );
        add_action( 'wp_ajax_nopriv_taa_hard_delete', [ $this, 'hard_delete_trade' ] );
    }

    /**
     * Hard Delete Trade (100% Cleanup: Remote + Final DB + Local Files + Staging DB)
     */
    public function hard_delete_trade() {
        global $wpdb;
        $id = intval($_POST['id']);
        
        $table = $wpdb->prefix . 'taa_staging';
        $row = $wpdb->get_row("SELECT * FROM $table WHERE id = $id");
        
        if (!$row) {
            wp_send_json_error('Trade not found');
        }

        // ----------------------------------------------------------------
        // 1. REMOTE DELETE (Clean up image.rdalgo.in)
        // ----------------------------------------------------------------
        if (!empty($row->marketing_url)) {
            // A. Fetch Remote ID using Date & URL
            $remote_date = date('Y-m-d', strtotime($row->created_at));
            $fetch_url = 'https://image.rdalgo.in/wp-json/rdalgo/v1/images?date=' . $remote_date . '&_t=' . time();
            
            $resp_list = wp_remote_get($fetch_url, ['timeout' => 10, 'sslverify' => false]);
            
            if (!is_wp_error($resp_list)) {
                $list_data = json_decode(wp_remote_retrieve_body($resp_list), true);
                if (is_array($list_data)) {
                    $remote_id_to_delete = 0;
                    // B. Find ID matching our URL
                    foreach ($list_data as $img_obj) {
                        $r_url = isset($img_obj['image_url']) ? $img_obj['image_url'] : (isset($img_obj['url']) ? $img_obj['url'] : '');
                        if ($r_url === $row->marketing_url) {
                            $remote_id_to_delete = isset($img_obj['id']) ? $img_obj['id'] : 0;
                            break;
                        }
                    }
                    // C. Fire Delete Command
                    if ($remote_id_to_delete > 0) {
                        wp_remote_post('https://image.rdalgo.in/wp-json/rdalgo/v1/delete', [
                            'body' => ['id' => $remote_id_to_delete],
                            'timeout' => 5, 'sslverify' => false
                        ]);
                    }
                }
            }
        }

        // ----------------------------------------------------------------
        // 2. FINAL DATABASE DELETE (If Trade was Approved)
        // ----------------------------------------------------------------
        // If this trade was already pushed to the Final Buy/Sell tables, remove it.
        if (stripos($row->status, 'APPROVED') !== false) {
            $db = TAA_DB::get_connection(); // Helper to get DB connection
            if ($db) {
                $dir = strtoupper($row->dir);
                $final_tbl = ($dir === 'BUY') ? get_option('taag_table_buy') : get_option('taag_table_sell');
                $prefix    = ($dir === 'BUY') ? 'taag_col_buy_' : 'taag_col_sell_';
                
                // Get column names map
                $col_name = get_option($prefix . 'name');
                $col_strike = get_option($prefix . 'strike');
                $col_date = get_option($prefix . 'date');

                if ($final_tbl && $col_name && $col_date) {
                    $trade_date = date('Y-m-d', strtotime($row->created_at));
                    
                    // Construct Delete Query for Final Table
                    // We match Name, Strike, and exact Date
                    $where_clause = [ $col_name => $row->chart_name ];
                    if (!empty($col_strike) && !empty($row->strike)) {
                        $where_clause[$col_strike] = $row->strike;
                    }
                    // Note: We need to handle the Date carefully (DATE vs DATETIME). 
                    // To be safe, we use a SQL query instead of helper for date matching.
                    $sql = "DELETE FROM $final_tbl WHERE $col_name = %s AND DATE($col_date) = %s";
                    $args = [ $row->chart_name, $trade_date ];
                    
                    if (!empty($col_strike) && !empty($row->strike)) {
                        $sql .= " AND $col_strike = %s";
                        $args[] = $row->strike;
                    }

                    $db->query( $db->prepare($sql, $args) );
                }
            }
        }

        // ----------------------------------------------------------------
        // 3. LOCAL FILE DELETE (Clean Disk Space)
        // ----------------------------------------------------------------
        // Delete Main Chart
        if (!empty($row->image_url)) {
            $file_path = $this->resolve_path_from_url($row->image_url);
            if ($file_path && file_exists($file_path)) {
                @unlink($file_path);
            }
        }
        // Delete Rejection Image
        if (!empty($row->rejection_image)) {
            $file_path_rej = $this->resolve_path_from_url($row->rejection_image);
            if ($file_path_rej && file_exists($file_path_rej)) {
                @unlink($file_path_rej);
            }
        }
        // Delete Local Marketing File (if exists)
        if (!empty($row->marketing_url)) {
            $file_path_mkt = $this->resolve_path_from_url($row->marketing_url);
            if ($file_path_mkt && file_exists($file_path_mkt)) {
                @unlink($file_path_mkt);
            }
        }

        // ----------------------------------------------------------------
        // 4. STAGING DB DELETE (The Row itself)
        // ----------------------------------------------------------------
        $result = $wpdb->delete($table, ['id' => $id]);

        if ($result !== false) {
            wp_send_json_success('Trade Completely Deleted (DB, Images, Remote & Final Tables)');
        } else {
            wp_send_json_error('Database Error: Could not delete row');
        }
    }

    /**
     * Fetch Approved Trades for Report
     */
    public function fetch_daily_report() {
        $date_input = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : current_time('Y-m-d');
        global $wpdb;
        $daily_summary = [];

        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}taa_staging WHERE status LIKE 'APPROVED%' AND DATE(created_at) = '$date_input' ORDER BY id DESC");

        if ($rows && count($rows) > 0) {
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
            $db = TAA_DB::get_connection();
            if ($db) {
                $tbl_buy = get_option('taag_table_buy');
                if ($tbl_buy) {
                    $col_date = get_option('taag_col_buy_date');
                    $col_profit = get_option('taag_col_buy_profit');
                    $buy_rows = $db->get_results("SELECT * FROM $tbl_buy WHERE DATE($col_date) = '$date_input'");
                    if ($buy_rows) {
                        foreach ($buy_rows as $b) {
                            $daily_summary[] = [
                                'dir' => 'BUY',
                                'symbol' => isset($b->{get_option('taag_col_buy_name')}) ? $b->{get_option('taag_col_buy_name')} : 'Unknown',
                                'strike' => isset($b->{get_option('taag_col_buy_strike')}) ? $b->{get_option('taag_col_buy_strike')} : '',
                                'profit_raw' => $b->$col_profit,
                                'profit_fmt' => TAA_DB::format_inr($b->$col_profit)
                            ];
                        }
                    }
                }
                $tbl_sell = get_option('taag_table_sell');
                if ($tbl_sell) {
                    $col_date = get_option('taag_col_sell_date');
                    $col_profit = get_option('taag_col_sell_profit');
                    $sell_rows = $db->get_results("SELECT * FROM $tbl_sell WHERE DATE($col_date) = '$date_input'");
                    if ($sell_rows) {
                        foreach ($sell_rows as $s) {
                            $daily_summary[] = [
                                'dir' => 'SELL',
                                'symbol' => isset($s->{get_option('taag_col_sell_name')}) ? $s->{get_option('taag_col_sell_name')} : 'Unknown',
                                'strike' => isset($s->{get_option('taag_col_sell_strike')}) ? $s->{get_option('taag_col_sell_strike')} : '',
                                'profit_raw' => $s->$col_profit,
                                'profit_fmt' => TAA_DB::format_inr($s->$col_profit)
                            ];
                        }
                    }
                }
            }
        }
        
        wp_send_json_success([
            'date_display' => date('d M Y', strtotime($date_input)),
            'trades'       => array_reverse($daily_summary)
        ]);
    }

    /**
     * Download Chart logic
     */
    public function download_chart() { 
        $url = isset($_GET['req_url']) ? esc_url_raw($_GET['req_url']) : '';
        if(empty($url)) wp_die('Invalid Request');

        $file_path = $this->resolve_path_from_url($url);
        if ($file_path && file_exists($file_path)) {
            header('Content-Type: image/png');
            header('Content-Disposition: attachment; filename="chart.png"');
            readfile($file_path);
            exit;
        } else {
            wp_die('File not found or access denied.');
        }
    }

    /**
     * Get Image as Base64 for Canvas Proxy
     */
    public function get_image_base64() {
        $img_url = isset($_POST['img_url']) ? esc_url_raw($_POST['img_url']) : '';
        if (empty($img_url)) wp_send_json_error('No URL provided');

        $file_path = $this->resolve_path_from_url($img_url);
        if ($file_path && file_exists($file_path)) {
            $image_data = file_get_contents($file_path);
            if ($image_data !== false) {
                wp_send_json_success(['base64' => 'data:image/png;base64,' . base64_encode($image_data)]);
            }
        }
        wp_send_json_error('Could not read image file.');
    }

    /**
     * Approve Trade Logic
     */
    public function approve_trade() {
        global $wpdb;
        $id = intval($_POST['id']);
        $staging_table = $wpdb->prefix . 'taa_staging';
        
        $staging_row = $wpdb->get_row("SELECT * FROM $staging_table WHERE id = $id", ARRAY_A);
        if (!$staging_row) wp_send_json_error('Trade not found');

        $db = TAA_DB::get_connection();
        if (!$db) wp_send_json_error('Final DB Connection Failed');

        $dir = $staging_row['dir'];
        $table_final = ($dir === 'BUY') ? get_option('taag_table_buy') : get_option('taag_table_sell');
        $prefix = ($dir === 'BUY') ? 'taag_col_buy_' : 'taag_col_sell_';
        
        $map_data = [];
        $fields = [ 'date'=>'created_at', 'name'=>'chart_name', 'strike'=>'strike', 'lot_size'=>'lot_size', 'total_lots'=>'total_lots', 'total_qty'=>'total_qty', 'entry'=>'entry', 'sl'=>'sl', 'target'=>'target', 'points'=>'points', 'risk'=>'risk', 'profit'=>'profit' ];

        foreach($fields as $map_key => $row_key) {
            $col = get_option($prefix . $map_key);
            if($col) $map_data[$col] = $staging_row[$row_key];
        }

        $trade_date = date('Y-m-d', strtotime($staging_row['created_at']));
        $prev_approved = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $staging_table WHERE status LIKE 'APPROVED%%' AND chart_name = %s AND strike = %s AND dir = %s AND DATE(created_at) = %s AND id != %d LIMIT 1", $staging_row['chart_name'], $staging_row['strike'], $staging_row['dir'], $trade_date, $id) );

        $msg = '';
if ($prev_approved) {
            // ----------------------------------------------------------------
            // 1. UPDATE FINAL TABLE (Syncs numbers with history)
            // ----------------------------------------------------------------
            $db->update($table_final, $map_data, [ 
                get_option($prefix.'name') => $staging_row['chart_name'], 
                get_option($prefix.'strike') => $staging_row['strike'] 
            ]); 

            // ----------------------------------------------------------------
            // 2. REMOTE DELETE (The "Strong" Cleanup for image.rdalgo.in)
            // ----------------------------------------------------------------
            if (!empty($prev_approved->marketing_url)) {
                // A. We need the Remote ID. Fetch the list for that date to find it.
                $remote_date = date('Y-m-d', strtotime($prev_approved->created_at));
                $fetch_url = 'https://image.rdalgo.in/wp-json/rdalgo/v1/images?date=' . $remote_date . '&_t=' . time();
                
                $resp_list = wp_remote_get($fetch_url, ['timeout' => 10, 'sslverify' => false]);
                
                if (!is_wp_error($resp_list)) {
                    $list_data = json_decode(wp_remote_retrieve_body($resp_list), true);
                    
                    if (is_array($list_data)) {
                        $remote_id_to_delete = 0;
                        
                        // B. Find the ID that matches our URL
                        foreach ($list_data as $img_obj) {
                            // Check 'image_url' or 'url' depending on API format
                            $r_url = isset($img_obj['image_url']) ? $img_obj['image_url'] : (isset($img_obj['url']) ? $img_obj['url'] : '');
                            
                            if ($r_url === $prev_approved->marketing_url) {
                                $remote_id_to_delete = isset($img_obj['id']) ? $img_obj['id'] : 0;
                                break;
                            }
                        }

                        // C. If ID found, FIRE the DELETE command
                        if ($remote_id_to_delete > 0) {
                            wp_remote_post('https://image.rdalgo.in/wp-json/rdalgo/v1/delete', [
                                'body' => ['id' => $remote_id_to_delete],
                                'timeout' => 5,
                                'sslverify' => false
                            ]);
                        }
                    }
                }
            }

            // ----------------------------------------------------------------
            // 3. LOCAL FILE DELETE (Clean Server Space)
            // ----------------------------------------------------------------
            // Delete Old Chart Image
            if (!empty($prev_approved->image_url)) {
                $prev_img_path = $this->resolve_path_from_url($prev_approved->image_url);
                if ($prev_img_path && file_exists($prev_img_path)) {
                    @unlink($prev_img_path);
                }
            }
            // Delete Old Rejection Image (if exists)
            if (!empty($prev_approved->rejection_image)) {
                $prev_rej_path = $this->resolve_path_from_url($prev_approved->rejection_image);
                if ($prev_rej_path && file_exists($prev_rej_path)) {
                    @unlink($prev_rej_path);
                }
            }

            // ----------------------------------------------------------------
            // 4. DATABASE CLEANUP
            // ----------------------------------------------------------------
            // Delete the old row completely
            $wpdb->delete($staging_table, ['id' => $prev_approved->id]);

            // Update the NEW row to "APPROVED (UPDATED)" and wipe the marketing link
            // This ensures it disappears from [taa_marketing_gallery] immediately.
            $wpdb->update($staging_table, [
                'status' => 'APPROVED (UPDATED)', 
                'marketing_url' => '' 
            ], ['id' => $id]);
            
            $msg = 'Trade Updated (Remote & Local Images Cleaned)';
            
            // 5. CLEANUP ANY OTHER DUPLICATES
            $this->cleanup_duplicates($id, $staging_row['chart_name'], $staging_row['strike'], $staging_row['dir'], $trade_date);

        } else {

            // INSERT New
            if ($db->insert($table_final, $map_data) !== false) {
                $wpdb->update($staging_table, ['status' => 'APPROVED'], ['id' => $id]);
                // CLEANUP: Delete matching Rejected/Pending and their images
                $this->cleanup_duplicates($id, $staging_row['chart_name'], $staging_row['strike'], $staging_row['dir'], $trade_date);
                $msg = 'Approved Successfully';
            } else {
                wp_send_json_error('Final DB Insert Failed');
            }
        }

        // Telegram Notification
        $full_name = $staging_row['chart_name'] . (!empty($staging_row['strike']) ? ' ' . $staging_row['strike'] : '');
        $tg_msg = "✅ *Trade Approved*\n\n📈 *Instrument:* " . $full_name . "\n↕️ *Action:* " . $staging_row['dir'] . "\n💰 *Profit:* " . TAA_DB::format_inr($staging_row['profit']) . "\n";
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

        $wpdb->update($wpdb->prefix . 'taa_staging', [
            'status' => 'REJECTED',
            'rejection_note' => $note,
            'rejection_image' => $final_image_url
        ], ['id' => $id]);

        wp_send_json_success('Trade Rejected');
    }

    /**
     * Helper to map URL to local path
     */
    private function resolve_path_from_url($url) {
        $upload_info = wp_upload_dir();
        $rel_path = str_replace($upload_info['baseurl'], '', $url);
        return wp_normalize_path($upload_info['basedir'] . urldecode($rel_path));
    }

    /**
     * Delete matching Pending or Rejected duplicates and their physical images
     */
    private function cleanup_duplicates($keep_id, $name, $strike, $dir, $date) {
        global $wpdb;
        $table = $wpdb->prefix . 'taa_staging';

        $duplicates = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, image_url, rejection_image FROM $table 
             WHERE id != %d AND chart_name = %s AND strike = %s AND dir = %s AND DATE(created_at) = %s 
             AND (status = 'PENDING' OR status = 'REJECTED')",
            $keep_id, $name, $strike, $dir, $date
        ));

        if ($duplicates) {
            foreach ($duplicates as $dup) {
                // Delete Main Image
                if (!empty($dup->image_url)) {
                    $path = $this->resolve_path_from_url($dup->image_url);
                    if ($path && file_exists($path)) @unlink($path);
                }
                // Delete Rejection Image
                if (!empty($dup->rejection_image)) {
                    $path_rej = $this->resolve_path_from_url($dup->rejection_image);
                    if ($path_rej && file_exists($path_rej)) @unlink($path_rej);
                }
                // Delete Database Record
                $wpdb->delete($table, ['id' => $dup->id]);
            }
        }
    }
}