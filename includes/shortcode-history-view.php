<?php
// FILE: includes/shortcode-history-view.php
$is_ajax = (isset($taa_is_ajax) && $taa_is_ajax === true);
if (!isset($today)) $today = current_time('Y-m-d');

if (!$is_ajax):
?>
<div class="taa-dashboard-wrapper" data-type="history">
    <div class="taa-staging-header">
        <h2>Trades History</h2>
        <div style="display:flex; align-items:center; gap:10px;">
            <button type="button" class="taa-btn-daily-report" 
                style="background:#ffc107; color:#000; border:none; padding:6px 12px; border-radius:4px; cursor:pointer; font-weight:bold; font-size:13px; display:flex; align-items:center; gap:5px;">
                📉 Daily Report
            </button>

            <input type="date" class="taa-date-input" value="<?php echo esc_attr($today); ?>" style="padding:5px; border-radius:4px; border:1px solid #ccc;">
            <button class="taa-btn-reload taa-refresh-btn">↻ Refresh</button>
        </div>
    </div>
    <div class="taa-table-responsive">
        <table class="taa-staging-table">
            <thead>
                <tr>
                    <th>Time</th><th>Dir</th><th>Instrument</th><th>Strike</th><th>Entry</th><th>Target</th><th>SL</th><th>Risk</th><th>RR</th><th>Status</th><th>Profit</th><th>Chart</th><th>Feedback</th>
                </tr>
            </thead>
            <tbody class="taa-table-body">
<?php endif; ?>

                <?php 
                global $wpdb;
                $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}taa_staging WHERE DATE(created_at) = '$today' ORDER BY id DESC");
                
                $total_approved_profit = 0; 
                if ($rows) {
                    foreach($rows as $r) {
                        if (strpos($r->status, 'APPROVED') !== false) {
                            $total_approved_profit += floatval($r->profit);
                        }
                    }
                }
                
                if($rows):
                    foreach($rows as $r): 
                        $is_approved = (strpos($r->status, 'APPROVED') !== false);
                        
                        // Marketing Data
                        $mkt_data = [];
                        if ($is_approved) {
                            $mkt_data = [
                                'id'     => $r->id,
                                'dir'    => $r->dir,
                                'inst'   => $r->chart_name,
                                'strike' => $r->strike,
                                'entry'  => $r->entry,
                                'target' => $r->target,
                                'sl'     => $r->sl,
                                'risk'   => TAA_DB::format_inr($r->risk),
                                'rr'     => $r->rr_ratio,
                                'profit' => TAA_DB::format_inr($r->profit),
                                'lots'   => $r->total_lots, 
                                'img'    => $r->image_url
                            ];
                        }
                    ?>
                    <tr>
                        <td><?php echo date('H:i', strtotime($r->created_at)); ?></td>
                        <td><span class="taa-badge <?php echo $r->dir=='BUY'?'taa-buy':'taa-sell'; ?>"><?php echo $r->dir; ?></span></td>
                        <td style="font-weight:600;"><?php echo esc_html($r->chart_name); ?></td>
                        <td><?php echo esc_html($r->strike) ?: '-'; ?></td>
                        <td><?php echo number_format($r->entry, 0); ?></td>
                        <td><?php echo number_format($r->target, 0); ?></td>
                        <td><?php echo number_format($r->sl, 0); ?></td>
                        <td style="color:#d9534f;"><?php echo TAA_DB::format_inr($r->risk); ?></td>
                        <td>1:<?php echo ceil(floatval($r->rr_ratio)); ?></td>
                        
                        <td>
                            <?php if($is_approved): ?>
                                <span class="taa-badge" style="background:#28a745;">APPROVED</span>
                            <?php elseif($r->status === 'REJECTED'): ?>
                                <span class="taa-badge" style="background:#dc3545;">REJECTED</span>
                            <?php else: ?>
                                <span class="taa-badge" style="background:#ffc107; color:#333;">PENDING</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:green;"><?php echo TAA_DB::format_inr($r->profit); ?></td>
                        <td style="white-space:nowrap;">
                            <?php if($r->image_url): ?>
                                <a href="<?php echo esc_url($r->image_url); ?>" target="_blank" class="taa-btn-view">Original</a>
                                
                                <?php if($is_approved): ?>
                                <button type="button" class="taa-btn-marketing" 
                                    data-trade='<?php echo json_encode($mkt_data, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'
                                    style="background:#6f42c1; color:white; border:none; border-radius:3px; cursor:pointer; font-size:10px; padding:3px 6px; margin-left:5px;">
                                    📢 Ads
                                </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($r->status === 'REJECTED' && ($r->rejection_note || $r->rejection_image)): ?>
                                <button class="taa-btn-view taa-js-view-feedback" 
                                        data-note="<?php echo esc_attr($r->rejection_note); ?>" 
                                        data-img="<?php echo esc_url($r->rejection_image); ?>"
                                        style="border-color:#dc3545; color:#dc3545;">
                                    View Reason
                                </button>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach;
                    
                    echo "<tr style='background:#e8f5e9; font-weight:bold; border-top:2px solid #28a745;'><td colspan='10' style='text-align:right;'>TOTAL APPROVED PROFIT:</td><td style='color:green; font-size:14px;'>" . TAA_DB::format_inr($total_approved_profit) . "</td><td colspan='2'></td></tr>";
                else:
                    echo "<tr><td colspan='13' style='text-align:center; padding:20px;'>No history found.</td></tr>";
                endif; 
                ?>

<?php if (!$is_ajax): ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>