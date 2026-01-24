<?php
$is_ajax = (isset($taa_is_ajax) && $taa_is_ajax === true);
if (!isset($today)) $today = current_time('Y-m-d');

// --- FETCH & STATS ---
global $wpdb;
$rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}taa_staging WHERE status LIKE 'APPROVED%' AND DATE(created_at) = '$today' ORDER BY id DESC");

$stats = [ 'total' => 0, 'buy' => 0, 'sell' => 0, 'approved' => 0, 'rejected' => 0, 'pending' => 0 ];
if ($rows) {
    foreach ($rows as $r) {
        $stats['total']++;
        if (strtoupper($r->dir) === 'BUY') $stats['buy']++;
        if (strtoupper($r->dir) === 'SELL') $stats['sell']++;
        $stats['approved']++; 
    }
}

if (!$is_ajax):
?>
<div class="taa-dashboard-wrapper" data-type="approved">
    <div class="taa-staging-header">
        <h2>Approved Trades</h2>
        <div style="display:flex; align-items:center; gap:10px;">
            <button type="button" class="taa-btn-daily-report" 
                style="background:#ffc107; color:#000; border:none; padding:6px 12px; border-radius:4px; cursor:pointer; font-weight:bold; font-size:13px; display:flex; align-items:center; gap:5px;">
                📉 Daily Report
            </button>

            <input type="date" class="taa-date-input" value="<?php echo esc_attr($today); ?>" style="padding:5px; border-radius:4px; border:1px solid #ccc;">
            <button class="taa-btn-reload taa-refresh-btn">↻ Refresh</button>
        </div>
    </div>

    <div class="taa-summary-bar">
        <div class="taa-summ-item"><span class="taa-summ-label">Total</span> <span class="taa-summ-val s-total"><?php echo $stats['total']; ?></span></div>
        <div class="taa-summ-item"><span class="taa-summ-label">Buy</span> <span class="taa-summ-val s-buy"><?php echo $stats['buy']; ?></span></div>
        <div class="taa-summ-item"><span class="taa-summ-label">Sell</span> <span class="taa-summ-val s-sell"><?php echo $stats['sell']; ?></span></div>
        <div class="taa-summ-item s-approved-box"><span class="taa-summ-label">Approved</span> <span class="taa-summ-val s-approved"><?php echo $stats['approved']; ?></span></div>
    </div>

    <div class="taa-table-responsive">
        <table class="taa-staging-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Time</th><th>Dir</th><th>Instrument</th><th>Strike</th><th>Entry</th><th>Target</th><th>SL</th><th>Risk</th><th>RR</th><th>Profit</th><th>Chart</th>
                </tr>
            </thead>
            <tbody class="taa-table-body">
<?php endif; ?>

                <?php 
                $total_profit_day = 0; 
                $total_count = $rows ? count($rows) : 0; 
                $i = 1;

                if($rows):
                    foreach($rows as $key => $r): 
                        $total_profit_day += floatval($r->profit);
                        $is_updated = (strpos($r->status, 'UPDATED') !== false);
                        
                        $daily_index = $total_count - $key;
                        $idx_str = sprintf('%02d', $daily_index); 
                        $date_str = date('d_M', strtotime($r->created_at)); 
                        $clean_chart_name = preg_replace('/[^A-Za-z0-9\- ]/', '', $r->chart_name); 
                        $download_name = $idx_str . '_' . $date_str . '_' . $clean_chart_name;
                        if(!empty($r->strike)) $download_name .= ' ' . preg_replace('/[^A-Za-z0-9\- ]/', '', $r->strike);
                        $ext = pathinfo($r->image_url, PATHINFO_EXTENSION);
                        if(empty($ext) || strlen($ext) > 4) $ext = 'png'; 
                        $full_filename = $download_name . '.' . $ext;
                        $download_link = admin_url('admin-ajax.php') . '?action=taa_download_chart&req_url=' . urlencode($r->image_url) . '&req_name=' . urlencode($full_filename);

                        $mkt_data = [
                            'id'     => $r->id, 'dir' => $r->dir, 'inst' => $r->chart_name, 'strike' => $r->strike,
                            'entry'  => $r->entry, 'target' => $r->target, 'sl' => $r->sl,
                            'risk'   => TAA_DB::format_inr($r->risk), 'rr' => $r->rr_ratio,
                            'profit' => TAA_DB::format_inr($r->profit), 'lots' => $r->total_lots, 'img' => $r->image_url,
                            'trade_date' => date('Y-m-d', strtotime($r->created_at))
                        ];
                    ?>
                    <tr data-lots="<?php echo esc_attr($r->total_lots); ?>">
                        <td><?php echo $i++; ?></td>
                        <td><?php echo date('H:i', strtotime($r->created_at)); ?><?php if($is_updated): ?>
    <br>
    <span style="font-size:9px; color:blue; font-weight:bold; text-transform:uppercase;">
        Trade Update
    </span>
<?php endif; ?></td>
                        <td><span class="taa-badge <?php echo $r->dir=='BUY'?'taa-buy':'taa-sell'; ?>"><?php echo $r->dir; ?></span></td>
                        <td style="font-weight:600;"><?php echo esc_html($r->chart_name); ?></td>
                        <td><?php echo esc_html($r->strike); ?></td>
                        <td><?php echo number_format($r->entry, 0); ?></td>
                        <td><?php echo number_format($r->target, 0); ?></td>
                        <td><?php echo number_format($r->sl, 0); ?></td>
                        <td style="color:#d9534f;"><?php echo TAA_DB::format_inr($r->risk); ?></td>
                        <td>1:<?php echo ceil(floatval($r->rr_ratio)); ?></td>
                        <td style="color:green;"><?php echo TAA_DB::format_inr($r->profit); ?></td>
                        <td style="white-space: nowrap;">
                            <?php if($r->image_url): ?>
                                <a href="<?php echo $download_link; ?>" class="taa-btn-view" style="background-color:#0073aa; color:white; padding:4px 8px; font-size:12px; border-radius:3px; margin-right:5px; text-decoration:none;">⬇</a>
                                <button type="button" class="taa-btn-marketing" 
                                    data-trade='<?php echo json_encode($mkt_data, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'
                                    style="background:#6f42c1; color:white; border:none; border-radius:3px; cursor:pointer; font-size:11px; padding:4px 8px;">
                                    📢 Ads
                                </button>
                                <?php if(!empty($r->marketing_url)): ?>
                                    <button class="taa-btn-view taa-tbl-telegram-btn" data-id="<?php echo $r->id; ?>" style="background:#0088cc; color:white; border:none; margin-left:5px; padding:4px 8px; font-size:11px; cursor:pointer;" title="Send to Telegram">✈</button>
                                    <button class="taa-btn-view taa-js-view-marketing" data-img="<?php echo esc_url($r->marketing_url); ?>" style="background:#17a2b8; color:white; border:none; margin-left:5px; padding:4px 8px; font-size:11px;">View</button>
                                    <a href="<?php echo esc_url(add_query_arg('t', time(), $r->marketing_url)); ?>" target="_blank" class="taa-btn-view" style="background:#6c757d; color:white; border:none; margin-left:5px; padding:4px 8px; font-size:11px; text-decoration:none;">⬇</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; 
                    echo "<tr style='background:#e8f5e9; font-weight:bold; border-top:2px solid #28a745;'><td colspan='10' style='text-align:right;'>TOTAL APPROVED PROFIT:</td><td style='color:green; font-size:14px;'>" . TAA_DB::format_inr($total_profit_day) . "</td><td></td></tr>";
                else:
                    echo "<tr><td colspan='12' style='text-align:center; padding:20px;'>No approved trades found.</td></tr>";
                endif; 
                ?>
                
<?php if (!$is_ajax): ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>