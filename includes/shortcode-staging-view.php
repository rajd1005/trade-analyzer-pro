<?php
// FILE: includes/shortcode-staging-view.php
$is_ajax = (isset($taa_is_ajax) && $taa_is_ajax === true);
if (!isset($today)) $today = current_time('Y-m-d');

// --- 1. FETCH DATA (Initial Load) ---
global $wpdb;
$rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}taa_staging WHERE DATE(created_at) = '$today' ORDER BY id DESC");

// Initial Stats (Used for first server-side render)
$stats = [ 'total' => 0, 'buy' => 0, 'sell' => 0, 'approved' => 0, 'rejected' => 0, 'pending' => 0 ];
if ($rows) {
    foreach ($rows as $r) {
        $stats['total']++;
        if (strtoupper($r->dir) === 'BUY') $stats['buy']++;
        if (strtoupper($r->dir) === 'SELL') $stats['sell']++;
        if (strpos($r->status, 'APPROVED') !== false) $stats['approved']++;
        elseif ($r->status === 'REJECTED') $stats['rejected']++;
        else $stats['pending']++;
    }
}

// --- 2. RENDER HEADER (NON-AJAX ONLY) ---
if (!$is_ajax):
?>
<div class="taa-dashboard-wrapper" data-type="staging">
    <div class="taa-staging-header">
        <h2>Trade Approval Dashboard</h2>
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
        <div class="taa-summ-item s-pending-box"><span class="taa-summ-label">Pending</span> <span class="taa-summ-val s-pending"><?php echo $stats['pending']; ?></span></div>
        <div class="taa-summ-item s-rejected-box"><span class="taa-summ-label">Rejected</span> <span class="taa-summ-val s-rejected"><?php echo $stats['rejected']; ?></span></div>
    </div>

    <div class="taa-table-responsive">
        <table class="taa-staging-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Time</th><th>Dir</th><th>Instrument</th><th>Strike</th><th>Entry</th><th>Target</th><th>SL</th><th>Risk</th><th>RR</th><th>Profit</th><th>Actions</th><th>Chart</th>
                </tr>
            </thead>
            <tbody class="taa-table-body">
<?php endif; ?>

                <?php 
                $counts = []; 
                $total_approved_profit = 0;

                if ($rows) { 
                    foreach($rows as $r) { 
                        $k = $r->chart_name . '|' . $r->strike . '|' . $r->dir; 
                        $counts[$k] = ($counts[$k]??0)+1; 
                        if (strpos($r->status, 'APPROVED') !== false) {
                            $total_approved_profit += floatval($r->profit);
                        }
                    } 
                }
                
                $i = 1;

                if($rows):
                    foreach($rows as $r): 
                        $is_dup = (isset($counts[$r->chart_name.'|'.$r->strike.'|'.$r->dir]) && $counts[$r->chart_name.'|'.$r->strike.'|'.$r->dir] > 1 && $r->status === 'PENDING');
                        $is_approved = (strpos($r->status, 'APPROVED') !== false); 
                        $is_rejected = ($r->status === 'REJECTED');

                        $mkt_data = [];
                        if ($is_approved) {
                            $mkt_data = [
                                'id'     => $r->id, 'dir' => $r->dir, 'inst' => $r->chart_name, 'strike' => $r->strike,
                                'entry'  => $r->entry, 'target' => $r->target, 'sl' => $r->sl,
                                'risk'   => TAA_DB::format_inr($r->risk), 'rr' => $r->rr_ratio,
                                'profit' => TAA_DB::format_inr($r->profit), 'lots' => $r->total_lots, 'img' => $r->image_url,
                                'trade_date' => date('Y-m-d', strtotime($r->created_at))
                            ];
                        }
                    ?>
                    <tr id="row-<?php echo $r->id; ?>" style="<?php echo $is_dup ? 'background:#fffbea;' : ''; ?>">
                        <td><?php echo $i++; ?></td>
                        <td><?php echo date('H:i', strtotime($r->created_at)); ?></td>
                        <td><span class="taa-badge <?php echo $r->dir=='BUY'?'taa-buy':'taa-sell'; ?>"><?php echo $r->dir; ?></span></td>
                        <td style="font-weight:600;"><?php echo esc_html($r->chart_name); ?></td>
                        <td><?php echo esc_html($r->strike) ?: '-'; ?></td>
                        <td><?php echo number_format($r->entry, 0); ?></td>
                        <td><?php echo number_format($r->target, 0); ?></td>
                        <td><?php echo number_format($r->sl, 0); ?></td>
                        <td style="color:#d9534f;"><?php echo TAA_DB::format_inr($r->risk); ?></td>
                        <td>1:<?php echo ceil(floatval($r->rr_ratio)); ?></td>
                        <td style="color:green;"><?php echo TAA_DB::format_inr($r->profit); ?></td>
                        
                        <td>
                            <?php if($is_approved): ?>
                                <span class="taa-badge" style="background:#28a745;">APPROVED</span>
                                <button type="button" class="taa-btn-marketing" 
                                    data-trade='<?php echo json_encode($mkt_data, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'
                                    style="background:#6f42c1; color:white; border:none; border-radius:3px; cursor:pointer; font-size:10px; padding:3px 6px; margin-left:5px;">
                                    📢 Ads
                                </button>
                            <?php elseif($is_rejected): ?>
                                <span class="taa-badge" style="background:#dc3545;">REJECTED</span>
                            <?php else: ?>
                                <?php if($is_dup): ?><span style="font-size:10px; color:orange; display:block;">DUPLICATE</span><?php endif; ?>
                                <button class="taa-btn-approve taa-js-approve" data-id="<?php echo $r->id; ?>">✔</button>
                                <button class="taa-btn-delete taa-js-reject-init" 
                                    data-id="<?php echo $r->id; ?>" 
                                    data-img="<?php echo esc_url($r->image_url); ?>"
                                    data-dir="<?php echo esc_attr($r->dir); ?>"
                                    data-entry="<?php echo number_format($r->entry, 0); ?>"
                                    data-target="<?php echo number_format($r->target, 0); ?>"
                                    data-sl="<?php echo number_format($r->sl, 0); ?>"
                                    data-risk="<?php echo TAA_DB::format_inr($r->risk); ?>"
                                    data-rr="<?php echo ceil(floatval($r->rr_ratio)); ?>"
                                    data-profit="<?php echo TAA_DB::format_inr($r->profit); ?>"
                                >✖</button>
                            <?php endif; ?>

                            <button class="taa-js-hard-delete" data-id="<?php echo $r->id; ?>" 
                                    title="Hard Delete"
                                    style="background:none; border:none; color:#999; cursor:pointer; font-size:14px; margin-left:5px;">
                                🗑
                            </button>
                        </td>

                        <td style="white-space:nowrap;">
                            <?php if($r->image_url): ?>
                                <a href="<?php echo esc_url($r->image_url); ?>" target="_blank" class="taa-btn-view" data-img="<?php echo esc_url($r->image_url); ?>">Raw</a>
                                <?php if(!empty($r->marketing_url)): ?>
                                    <button class="taa-btn-view taa-tbl-telegram-btn" data-id="<?php echo $r->id; ?>" style="background:#0088cc; color:white; border:none; margin-left:5px; padding:3px 6px; font-size:10px; cursor:pointer;" title="Send to Telegram">✈</button>
                                    <button class="taa-btn-view taa-js-view-marketing" data-img="<?php echo esc_url($r->marketing_url); ?>" style="background:#17a2b8; color:white; border:none; margin-left:5px;">View</button>
                                    <a href="<?php echo esc_url(add_query_arg('t', time(), $r->marketing_url)); ?>" target="_blank" class="taa-btn-view" style="background:#6c757d; color:white; border:none; margin-left:5px;">⬇</a>
                                <?php endif; ?>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; 
                    
                    // Total Row
                    echo "<tr style='background:#e8f5e9; font-weight:bold; border-top:2px solid #28a745;'><td colspan='10' style='text-align:right;'>TOTAL APPROVED PROFIT:</td><td style='color:green; font-size:14px;'>" . TAA_DB::format_inr($total_approved_profit) . "</td><td colspan='2'></td></tr>";

                else:
                    echo "<tr><td colspan='13' style='text-align:center; padding:20px;'>No trades for today.</td></tr>";
                endif; 
                ?>
                
<?php if (!$is_ajax): ?>
            </tbody>
        </table>
    </div>
    
    <div id="taa-reject-modal" class="taa-modal-overlay" style="display:none;">
        <div class="taa-modal-content">
            <div class="taa-modal-header">
                <h3>Reject Trade & Provide Feedback</h3>
                <div style="display:flex; gap:10px;">
                    <button id="taa-btn-fullscreen" class="taa-tool-btn" title="Toggle Fullscreen" style="padding:4px 8px; font-size:14px; border:1px solid #ccc; background:#fff;">⛶</button>
                    <button id="taa-modal-close" class="taa-modal-close">&times;</button>
                </div>
            </div>
            <div class="taa-modal-body">
                <div class="taa-modal-info-bar">
                    <div class="taa-info-item"><span class="label">DIR</span><span class="val" id="inf-dir">-</span></div>
                    <div class="taa-info-item"><span class="label">ENTRY</span><span class="val" id="inf-entry">-</span></div>
                    <div class="taa-info-item"><span class="label">TARGET</span><span class="val" id="inf-target">-</span></div>
                    <div class="taa-info-item"><span class="label">SL</span><span class="val" id="inf-sl">-</span></div>
                    <div class="taa-info-item"><span class="label">RISK</span><span class="val" id="inf-risk" style="color:#d9534f;">-</span></div>
                    <div class="taa-info-item"><span class="label">RR</span><span class="val" id="inf-rr">-</span></div>
                    <div class="taa-info-item"><span class="label">PROFIT</span><span class="val" id="inf-profit" style="color:green;">-</span></div>
                </div>
                <div id="taa-editor-holder"></div>
                <textarea id="taa-reject-note" class="taa-input-text" rows="2" placeholder="Enter reason for rejection... (Optional)" style="margin-top:10px;"></textarea>
            </div>
            <div class="taa-modal-footer">
                <button id="taa-submit-reject" class="taa-btn" style="background:#dc3545;">Reject Trade</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>