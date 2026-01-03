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
            <select id="taa-instrument-select" class="taa-select" style="width: 100%;">
                <option value="" disabled selected>-- Select --</option>
                <?php foreach($instruments as $inst): ?>
                    <option value="<?php echo esc_attr($inst['value']); ?>"><?php echo esc_html($inst['name']); ?></option>
                <?php endforeach; ?>
            </select>
            
            <div style="text-align: right; margin-top: 5px; min-height: 18px;">
                <span id="info-lot-display" style="font-size: 13px; font-weight: 600; color: #555; display:none;">
                    Lot Size: <span id="txt-lot-val">-</span>
                </span>
            </div>

            <div style="margin-top: 10px;">
                <label id="lbl-strike" class="taa-label">STRIKE PRICE / CONTRACT:</label>
                <input type="text" id="taa-pre-strike" class="taa-input-text" placeholder="e.g. 24600" style="width: 100%;">
                <select id="taa-strike-select" class="taa-select" style="display:none; width: 100%;">
                    <option value="" selected>-- Select Contract --</option>
                    <option value="2nd">2nd</option>
                    <option value="3rd">3rd</option>
                    <option value="4th">4th</option>
                    <option value="5th">5th</option>
                </select>
            </div>
        </div>

        <hr style="margin:20px 0; border:0; border-top:1px solid #eee;">

        <?php
        $show_analyze = ($input_mode === 'ai' || $input_mode === 'both');
        $show_manual  = ($input_mode === 'manual' || $input_mode === 'both');
        ?>

        <div id="taa-instr-box" style="margin-bottom: 10px; padding: 10px; background: #e3f2fd; border-left: 4px solid #2196F3; border-radius: 4px; display:none;">
            <p id="taa-instr-text" style="margin:0; font-size: 13px; color: #0d47a1;"></p>
        </div>

        <div id="taa-drop-zone" style="border: 2px dashed #ccc; padding: 20px; text-align: center; cursor: pointer; background: #fafafa; border-radius: 5px; margin-bottom: 15px; transition: 0.2s;">
            <div id="taa-drop-content">
                <span style="font-size: 24px;">📂</span>
                <p style="margin:5px 0 0; font-weight:600; color:#666;">Click to Upload or Paste Image (Ctrl+V)</p>
                <p style="margin:0; font-size:11px; color:#999;">(Image is Mandatory)</p>
            </div>
            <div id="taa-preview-container" style="display:none; position: relative;">
                <img id="taa-img-preview" style="max-width:100%; max-height: 200px; border-radius:5px; border:1px solid #ddd;">
                <button id="taa-remove-img" style="position: absolute; top: 5px; right: 5px; background: rgba(0,0,0,0.6); color: #fff; border: none; border-radius: 50%; width: 25px; height: 25px; cursor: pointer;">&times;</button>
            </div>
        </div>
        
        <input type="file" id="taa-file-input" accept="image/*" style="display:none;">

        <div style="display:flex; gap:10px;">
            <button id="taa-btn-analyze" class="taa-btn" style="flex:1; <?php echo $show_analyze ? '' : 'display:none;'; ?>">🚀 Analyze Chart</button>
            <button id="taa-btn-manual" class="taa-btn" style="flex:1; background:#555; <?php echo $show_manual ? '' : 'display:none;'; ?>">✍ Enter Manually</button>
        </div>
    </div>

    <div id="taa-loader" class="taa-loader">Analyzing...</div>

    <div id="taa-result-section" class="taa-result">
        <div id="taa-header-bg" class="taa-header">
            <span id="taa-header-text"></span>
            <button id="btn-swap" class="taa-btn-swap">🔄 Swap</button>
        </div>
        
        <table class="taa-table">
            <tbody id="taa-table-body">
                <?php foreach(['entry'=>'Entry', 'target'=>'Target', 'sl'=>'Stop Loss'] as $id => $label): ?>
                <tr id="tr-<?php echo $id; ?>">
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
            </tbody>
        </table>

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