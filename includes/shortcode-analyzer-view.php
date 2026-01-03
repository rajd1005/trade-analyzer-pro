<div class="taa-wrapper">
    <div id="taa-upload-section" class="taa-upload-box">
        <div class="taa-dir-selector">
            <input type="radio" name="trade_dir" id="rad-buy" class="taa-radio-input" value="buy">
            <label for="rad-buy" class="taa-radio-label">BUY</label>
            <input type="radio" name="trade_dir" id="rad-sell" class="taa-radio-input" value="sell">
            <label for="rad-sell" class="taa-radio-label">SELL</label>
        </div>

        <div style="text-align:left; margin-bottom:15px;">
            <label class="taa-label">SELECT INSTRUMENT:</label>
            <select id="taa-instrument-select" class="taa-select">
                <option value="" disabled selected>-- Select --</option>
                <?php foreach($instruments as $inst): ?>
                    <option value="<?php echo esc_attr($inst['value']); ?>"><?php echo esc_html($inst['name']); ?></option>
                <?php endforeach; ?>
            </select>

            <label id="lbl-strike" class="taa-label">STRIKE PRICE / CONTRACT:</label>
            <input type="text" id="taa-pre-strike" class="taa-input-text" placeholder="e.g. 24600">
            <select id="taa-strike-select" class="taa-select" style="display:none;">
                <option value="" selected>-- Select Contract --</option>
                <option value="2nd">2nd</option>
                <option value="3rd">3rd</option>
                <option value="4th">4th</option>
                <option value="5th">5th</option>
            </select>
        </div>

        <hr style="margin:20px 0; border:0; border-top:1px solid #eee;">

        <?php
        $show_analyze = ($input_mode === 'ai' || $input_mode === 'both');
        $show_manual  = ($input_mode === 'manual' || $input_mode === 'both');
        ?>

        <div style="display:flex; gap:10px;">
            <button id="taa-btn-analyze" class="taa-btn" style="flex:1; <?php echo $show_analyze ? '' : 'display:none;'; ?>">📷 Analyze Chart</button>
            <button id="taa-btn-manual" class="taa-btn" style="flex:1; background:#555; <?php echo $show_manual ? '' : 'display:none;'; ?>">✍ Enter Manually</button>
        </div>
        <input type="file" id="taa-file-input" accept="image/*" style="display:none;">
    </div>

    <div id="taa-loader" class="taa-loader">Analyzing...</div>

    <div id="taa-result-section" class="taa-result">
        <div id="taa-header-bg" class="taa-header">
            <span id="taa-header-text"></span>
            <button id="btn-swap" class="taa-btn-swap">🔄 Swap</button>
        </div>
        
        <table class="taa-table">
            <?php foreach(['entry'=>'Entry', 'target'=>'Target', 'sl'=>'Stop Loss'] as $id => $label): ?>
            <tr>
                <td><strong><?php echo $label; ?>:</strong></td>
                <td align="right">
                    <span id="span-<?php echo $id; ?>" class="taa-price-display">0</span>
                    <input type="number" id="val-<?php echo $id; ?>" class="taa-price-input" style="display:none;">
                    <span class="taa-edit-icon" data-field="<?php echo $id; ?>">✎</span>
                </td>
            </tr>
            <?php endforeach; ?>
            <tr class="taa-highlight-row"><td><strong>Point Gain:</strong></td><td align="right" id="val-point-gain">0</td></tr>
            <tr style="background:#fdfdfd;"><td><strong>Lot Size:</strong></td><td align="right"><span id="display-lot-size">-</span></td></tr>
            <tr style="background:#fdfdfd;"><td><strong>Total Lots:</strong></td><td align="right"><span id="display-total-lots"><?php echo esc_html($global_lots); ?></span></td></tr>
            <tr style="background:#fff;"><td><strong>Total Qnt:</strong></td><td align="right" id="val-total-qty">-</td></tr>
        </table>

        <div id="manual-upload-box" style="display:none; padding:15px; border-top:1px solid #eee; text-align:center;">
            <label class="taa-label" style="margin-bottom:5px; color:#d9534f;">⚠ Upload Chart (Mandatory):</label>
            <input type="file" id="taa-manual-file" accept="image/*" style="font-size:12px;">
        </div>

        <div class="taa-stats">
            <div class="taa-stat-box" style="background:#fff5f5;"><small>RISK</small><br><strong style="color:#d9534f;" id="res-risk">-</strong></div>
            <div class="taa-stat-box" style="background:#f0fff4;"><small>PROFIT</small><br><strong style="color:#28a745;" id="res-profit">-</strong></div>
        </div>
        <p style="text-align:center;"><strong>RR Ratio:</strong> <span id="res-rr">1 : -</span></p>
        
        <div style="padding:0 20px 20px 20px;">
            <button class="taa-btn-save">💾 Submit for Approval</button>
            <div id="save-status" style="text-align:center; margin-top:10px;"></div>
            <button class="taa-btn taa-btn-reset" style="background:#555; margin-top:10px;">Analyze Another</button>
        </div>
    </div>
</div>

<input type="hidden" id="raw-top" value="0">
<input type="hidden" id="raw-mid" value="0">
<input type="hidden" id="raw-bot" value="0">
<input type="hidden" id="current-dir" value="BUY">
<input type="hidden" id="chart-name" value="">
<input type="hidden" id="calc-lot-size" value="0">
<input type="hidden" id="calc-total-lots" value="<?php echo esc_attr($global_lots); ?>">
<input type="hidden" id="rule-strike-req" value="NO">
<input type="hidden" id="rule-mode" value="BOTH">
<input type="hidden" id="taag-global-mode" value="<?php echo esc_attr($input_mode); ?>">
<input type="hidden" id="image-url" value="">

<input type="hidden" id="val-rule-min-profit" value="<?php echo esc_attr(get_option('taag_val_min_profit', '10000')); ?>">
<input type="hidden" id="val-rule-min-rr" value="<?php echo esc_attr(get_option('taag_val_min_rr', '1.5')); ?>">