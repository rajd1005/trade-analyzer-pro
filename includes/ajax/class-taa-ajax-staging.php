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
     * Hard Delete Trade & Image
     */
    public function hard_delete_trade() {
        global $wpdb;
        $id = intval($_POST['id']);
        
        $table = $wpdb->prefix . 'taa_staging';
        $row = $wpdb->get_row("SELECT * FROM $table WHERE id = $id");
        
        if (!$row) {
            wp_send_json_error('Trade not found');
        }

        // Delete Main Image
        if (!empty($row->image_url)) {
            $file_path = $this->resolve_path_from_url($row->image_url);
            if ($file_path && file_exists($file_path)) {
                @unlink($file_path);
            }
        }

        // Delete Rejection Feedback Image
        if (!empty($row->rejection_image)) {
            $file_path_rej = $this->resolve_path_from_url($row->rejection_image);
            if ($file_path_rej && file_exists($file_path_rej)) {
                @unlink($file_path_rej);
            }
        }

        $result = $wpdb->delete($table, ['id' => $id]);

        if ($result !== false) {
            wp_send_json_success('Trade and Image Deleted Permanently');
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
            // UPDATE Existing
            $db->update($table_final, $map_data, [ get_option($prefix.'name') => $staging_row['chart_name'], get_option($prefix.'strike') => $staging_row['strike'] ]); 
            $wpdb->update($staging_table, [ 'status' => 'APPROVED (UPDATED)' ], ['id' => $prev_approved->id]);
            $wpdb->delete($staging_table, ['id' => $id]);
            $msg = 'Trade Updated Successfully';
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