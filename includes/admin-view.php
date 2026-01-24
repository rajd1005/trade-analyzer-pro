<div class="wrap">
    <h1>Trade Analyzer Pro Settings</h1>
    <form action="options.php" method="post">
        <?php settings_fields('taagPlugin'); ?>
        
        <div style="display:flex; gap:20px; flex-wrap:wrap;">
            
            <div style="flex:1; min-width:300px;">
                <h2>1. API, Config & Alerts</h2>
                <table class="form-table">
                    <tr>
                        <th>Google API Key</th>
                        <td><input type="password" name="taag_api_key" value="<?php echo esc_attr(get_option('taag_api_key')); ?>" style="width:100%"></td>
                    </tr>
                    <tr>
                        <th>Model ID</th>
                        <td><input type="text" name="taag_model_id" value="<?php echo esc_attr(get_option('taag_model_id', 'gemini-2.0-flash')); ?>" style="width:100%"></td>
                    </tr>
                    <tr>
                        <th>Default Total Lots</th>
                        <td><input type="number" name="taag_default_total_lots" value="<?php echo esc_attr(get_option('taag_default_total_lots', '6')); ?>"></td>
                    </tr>
                    <tr>
                        <th>Entry Process Mode</th>
                        <td>
                            <select name="taag_input_mode">
                                <option value="ai" <?php selected(get_option('taag_input_mode', 'ai'), 'ai'); ?>>AI Analysis Only</option>
                                <option value="manual" <?php selected(get_option('taag_input_mode'), 'manual'); ?>>Manual Entry Only</option>
                                <option value="both" <?php selected(get_option('taag_input_mode'), 'both'); ?>>Both (AI & Manual)</option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th>Direct Add to Approved</th>
                        <td>
                            <?php 
                            global $wp_roles;
                            if ( ! isset( $wp_roles ) ) $wp_roles = new WP_Roles();
                            $all_roles = $wp_roles->get_names();
                            $selected_roles = get_option('taag_direct_add_roles', []);
                            if(!is_array($selected_roles)) $selected_roles = [];
                            ?>
                            <select name="taag_direct_add_roles[]" multiple style="height:120px; width:100%;">
                                <?php foreach($all_roles as $slug => $name): ?>
                                    <option value="<?php echo esc_attr($slug); ?>" <?php echo in_array($slug, $selected_roles) ? 'selected' : ''; ?>>
                                        <?php echo esc_html($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Hold CTRL/CMD to select multiple. Users with these roles bypass Staging.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th colspan="2" style="padding-top:20px; color:#0073aa;">Telegram Notifications</th>
                    </tr>
                    <tr>
                        <th>Bot Token</th>
                        <td>
                            <input type="password" name="taag_telegram_token" value="<?php echo esc_attr(get_option('taag_telegram_token')); ?>" style="width:100%">
                            <p class="description">From @BotFather</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Chat ID</th>
                        <td>
                            <input type="text" name="taag_telegram_chat_id" value="<?php echo esc_attr(get_option('taag_telegram_chat_id')); ?>" style="width:100%">
                            <p class="description">Group or User ID (e.g., -100xxxxxxx)</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Message Template (Button)</th>
                        <td>
                            <textarea name="taag_telegram_template" rows="4" style="width:100%"><?php echo esc_textarea(get_option('taag_telegram_template', "🚀 *{chart_name}* ({dir})\n\n💰 Profit: {profit}\n⚖️ RR: {rr}\n🎯 Strike: {strike}")); ?></textarea>
                            <p class="description">Placeholders: <code>{profit}</code>, <code>{rr}</code>, <code>{chart_name}</code>, <code>{strike}</code>, <code>{dir}</code></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th colspan="2" style="padding-top:20px; color:#0073aa;">Validation Rules</th>
                    </tr>
                    <tr>
                        <th>Minimum Profit (₹)</th>
                        <td>
                            <input type="number" name="taag_val_min_profit" value="<?php echo esc_attr(get_option('taag_val_min_profit', '10000')); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th>Minimum RR Ratio</th>
                        <td>
                            <input type="number" step="0.1" name="taag_val_min_rr" value="<?php echo esc_attr(get_option('taag_val_min_rr', '1.5')); ?>">
                        </td>
                    </tr>
                    
                    <tr>
                        <th>Instruments List</th>
                        <td>
                            <?php 
    // [UPDATED] Fetch from DB to ensure it matches Frontend Table
    global $wpdb;
    $inst_table = $wpdb->prefix . 'taa_instruments';
    $db_rows = $wpdb->get_results("SELECT * FROM $inst_table ORDER BY name ASC");
    
    $current_list_str = "";
    if ($db_rows) {
        foreach($db_rows as $row) {
            // Rebuild string: NAME|LOT|MODE|REQ
            $current_list_str .= $row->name . '|' . $row->lot_size . '|' . $row->mode . '|' . $row->strike_req . "\n";
        }
    } else {
        // Fallback if table empty
        $current_list_str = get_option('taag_instruments_list');
    }
?>
<textarea name="taag_instruments_list" rows="10" style="width:100%; font-family:monospace; white-space:pre; overflow-x:hidden;"><?php echo esc_textarea(trim($current_list_str)); ?></textarea>
                            <p class="description">Format: <code>NAME|LOT|MODE|STRIKE_REQ</code></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div style="flex:1; min-width:300px; background:#f9f9f9; padding:20px; border:1px solid #ddd; border-radius:5px;">
                <h2>2. Database Connection</h2>
                <p>Select where the trades should be saved (Final DB):</p>
                <select name="taag_db_type">
                    <option value="local" <?php selected(get_option('taag_db_type'), 'local'); ?>>Local (Current WordPress)</option>
                    <option value="remote" <?php selected(get_option('taag_db_type'), 'remote'); ?>>Remote Server</option>
                </select>
                
                <div style="margin-top:15px; display:<?php echo (get_option('taag_db_type') === 'remote' ? 'block' : 'none'); ?>;">
                    <p><input type="text" name="taag_db_host" placeholder="Host IP" value="<?php echo esc_attr(get_option('taag_db_host')); ?>" style="width:100%"></p>
                    <p><input type="text" name="taag_db_user" placeholder="Username" value="<?php echo esc_attr(get_option('taag_db_user')); ?>" style="width:100%"></p>
                    <p><input type="password" name="taag_db_pass" placeholder="Password" value="<?php echo esc_attr(get_option('taag_db_pass')); ?>" style="width:100%"></p>
                    <p><input type="text" name="taag_db_name" placeholder="Database Name" value="<?php echo esc_attr(get_option('taag_db_name')); ?>" style="width:100%"></p>
                </div>
            </div>
        </div>

        <hr>

        <h2>3. Email Notifications</h2>
        <table class="form-table">
            <tr>
                <th>Enable Emails</th>
                <td>
                    <input type="checkbox" name="taag_email_enable" value="1" <?php checked(get_option('taag_email_enable'), 1); ?>> Enable all email notifications
                </td>
            </tr>
            <tr>
                <th>CC / BCC</th>
                <td>
                    <input type="text" name="taag_stage_cc" value="<?php echo esc_attr(get_option('taag_stage_cc')); ?>" placeholder="Staging CC">
                    <input type="text" name="taag_stage_bcc" value="<?php echo esc_attr(get_option('taag_stage_bcc')); ?>" placeholder="Staging BCC"><br>
                    <input type="text" name="taag_email_cc" value="<?php echo esc_attr(get_option('taag_email_cc')); ?>" placeholder="Final CC">
                    <input type="text" name="taag_email_bcc" value="<?php echo esc_attr(get_option('taag_email_bcc')); ?>" placeholder="Final BCC">
                </td>
            </tr>
            <tr>
                <th colspan="2"><h3>Template: New Staging Trade</h3></th>
            </tr>
            <tr>
                <th>Subject</th>
                <td><input type="text" name="taag_email_sub_stage" value="<?php echo esc_attr(get_option('taag_email_sub_stage', 'New Trade Staged: {name}')); ?>" style="width:100%"></td>
            </tr>
            <tr>
                <th>Body</th>
                <td><textarea name="taag_email_body_stage" rows="3" style="width:100%"><?php echo esc_textarea(get_option('taag_email_body_stage', "A new {dir} trade for {name} has been staged.\nEntry: {entry}")); ?></textarea></td>
            </tr>
            
            <tr>
                <th colspan="2"><h3>Template: Trade Approved</h3></th>
            </tr>
            <tr>
                <th>Subject</th>
                <td><input type="text" name="taag_email_sub_approve" value="<?php echo esc_attr(get_option('taag_email_sub_approve', 'Trade Approved: {name}')); ?>" style="width:100%"></td>
            </tr>
            <tr>
                <th>Body</th>
                <td><textarea name="taag_email_body_approve" rows="3" style="width:100%"><?php echo esc_textarea(get_option('taag_email_body_approve', "The trade for {name} has been approved.")); ?></textarea></td>
            </tr>

            <tr>
                <th colspan="2"><h3>Template: Trade Rejected</h3></th>
            </tr>
            <tr>
                <th>Subject</th>
                <td><input type="text" name="taag_email_sub_reject" value="<?php echo esc_attr(get_option('taag_email_sub_reject', 'Trade Rejected: {name}')); ?>" style="width:100%"></td>
            </tr>
            <tr>
                <th>Body</th>
                <td><textarea name="taag_email_body_reject" rows="3" style="width:100%"><?php echo esc_textarea(get_option('taag_email_body_reject', "Trade for {name} was rejected.\nReason: {reason}")); ?></textarea></td>
            </tr>
        </table>

        <hr>

        <h2>4. Database Column Mapping</h2>
        <div style="display:flex; gap:20px; flex-wrap:wrap;">
            <div style="flex:1; min-width:300px;">
                <h3>BUY Table</h3>
                <?php taag_table_dropdown_helper('taag_table_buy', $tables); ?>
                <br><br>
                <table class="form-table">
                    <tr><td>Date</td><td><?php taag_column_dropdown_helper('taag_col_buy_date', $buy_cols, 'tradedate_buy'); ?></td></tr>
                    <tr><td>Stock Name</td><td><?php taag_column_dropdown_helper('taag_col_buy_name', $buy_cols, 'seclectstockname_buy'); ?></td></tr>
                    <tr><td>Strike/Contract</td><td><?php taag_column_dropdown_helper('taag_col_buy_strike', $buy_cols, 'strickprice_buy'); ?></td></tr>
                    <tr><td>Lot Size (Qty)</td><td><?php taag_column_dropdown_helper('taag_col_buy_lot_size', $buy_cols, 'seclectstock_buy'); ?></td></tr>
                    <tr><td>Total Lots</td><td><?php taag_column_dropdown_helper('taag_col_buy_total_lots', $buy_cols, 'stocklot_buy'); ?></td></tr>
                    <tr><td>Total Qty (Calc)</td><td><?php taag_column_dropdown_helper('taag_col_buy_total_qty', $buy_cols, 'stocktotalot_buy'); ?></td></tr>
                    <tr><td>Entry Price</td><td><?php taag_column_dropdown_helper('taag_col_buy_entry', $buy_cols, 'stockentry_buy'); ?></td></tr>
                    <tr><td>Stop Loss</td><td><?php taag_column_dropdown_helper('taag_col_buy_sl', $buy_cols, 'stocksl_buy'); ?></td></tr>
                    <tr><td>Target/High</td><td><?php taag_column_dropdown_helper('taag_col_buy_target', $buy_cols, 'stockmadehight_buy'); ?></td></tr>
                    <tr><td>Points Gain</td><td><?php taag_column_dropdown_helper('taag_col_buy_points', $buy_cols, 'stockpointgain_buy'); ?></td></tr>
                    <tr><td>Risk Amt</td><td><?php taag_column_dropdown_helper('taag_col_buy_risk', $buy_cols, 'stockrisk_buy'); ?></td></tr>
                    <tr><td>Profit Amt</td><td><?php taag_column_dropdown_helper('taag_col_buy_profit', $buy_cols, 'stockprofit_buy'); ?></td></tr>
                </table>
            </div>
            <div style="flex:1; min-width:300px;">
                <h3>SELL Table</h3>
                <?php taag_table_dropdown_helper('taag_table_sell', $tables); ?>
                <br><br>
                <table class="form-table">
                    <tr><td>Date</td><td><?php taag_column_dropdown_helper('taag_col_sell_date', $sell_cols, 'tradedate_sell'); ?></td></tr>
                    <tr><td>Stock Name</td><td><?php taag_column_dropdown_helper('taag_col_sell_name', $sell_cols, 'seclectstockname_sell'); ?></td></tr>
                    <tr><td>Strike/Contract</td><td><?php taag_column_dropdown_helper('taag_col_sell_strike', $sell_cols, 'strickprice_sell'); ?></td></tr>
                    <tr><td>Lot Size (Qty)</td><td><?php taag_column_dropdown_helper('taag_col_sell_lot_size', $sell_cols, 'seclectstock_sell'); ?></td></tr>
                    <tr><td>Total Lots</td><td><?php taag_column_dropdown_helper('taag_col_sell_total_lots', $sell_cols, 'stocklot_sell'); ?></td></tr>
                    <tr><td>Total Qty (Calc)</td><td><?php taag_column_dropdown_helper('taag_col_sell_total_qty', $sell_cols, 'stocktotalot_sell'); ?></td></tr>
                    <tr><td>Entry Price</td><td><?php taag_column_dropdown_helper('taag_col_sell_entry', $sell_cols, 'stockentry_sell'); ?></td></tr>
                    <tr><td>Stop Loss</td><td><?php taag_column_dropdown_helper('taag_col_sell_sl', $sell_cols, 'stocksl_sell'); ?></td></tr>
                    <tr><td>Target/Low</td><td><?php taag_column_dropdown_helper('taag_col_sell_target', $sell_cols, 'stockmadelow_sell'); ?></td></tr>
                    <tr><td>Points Gain</td><td><?php taag_column_dropdown_helper('taag_col_sell_points', $sell_cols, 'stockpointgain_sell'); ?></td></tr>
                    <tr><td>Risk Amt</td><td><?php taag_column_dropdown_helper('taag_col_sell_risk', $sell_cols, 'stockrisk_sell'); ?></td></tr>
                    <tr><td>Profit Amt</td><td><?php taag_column_dropdown_helper('taag_col_sell_profit', $sell_cols, 'stockprofit_sell'); ?></td></tr>
                </table>
            </div>
        </div>

        <?php submit_button(); ?>
    </form>
</div>