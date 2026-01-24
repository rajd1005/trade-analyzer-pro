<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TAA_Admin {
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function add_menu() {
        add_options_page( 'Trade Analyzer Pro', 'Trade Analyzer Pro', 'manage_options', 'trade_analyzer_pro', [ $this, 'render_page' ] );
    }

    public function register_settings() {
        // Core
        register_setting('taagPlugin', 'taag_api_key');
        register_setting('taagPlugin', 'taag_model_id');
        register_setting('taagPlugin', 'taag_default_total_lots');
        // [UPDATED] Register with Callback to sync DB
        register_setting('taagPlugin', 'taag_instruments_list', [
            'sanitize_callback' => [ $this, 'save_instruments_to_db' ]
        ]);
        
        // Settings: Input Mode & Permissions
        register_setting('taagPlugin', 'taag_input_mode'); 
        register_setting('taagPlugin', 'taag_direct_add_roles'); 

        // [NEW] Telegram Settings
        register_setting('taagPlugin', 'taag_telegram_token');
        register_setting('taagPlugin', 'taag_telegram_chat_id');
        register_setting('taagPlugin', 'taag_telegram_template'); // <--- New Setting

        // Validation Rules
        register_setting('taagPlugin', 'taag_val_min_profit');
        register_setting('taagPlugin', 'taag_val_min_rr');

        // Email Settings
        register_setting('taagPlugin', 'taag_email_enable'); 
        register_setting('taagPlugin', 'taag_email_cc');
        register_setting('taagPlugin', 'taag_email_bcc');
        register_setting('taagPlugin', 'taag_stage_cc');
        register_setting('taagPlugin', 'taag_stage_bcc');
        
        // Email Templates
        register_setting('taagPlugin', 'taag_email_sub_stage');
        register_setting('taagPlugin', 'taag_email_body_stage');
        register_setting('taagPlugin', 'taag_email_sub_approve');
        register_setting('taagPlugin', 'taag_email_body_approve');
        register_setting('taagPlugin', 'taag_email_sub_reject');
        register_setting('taagPlugin', 'taag_email_body_reject');

        // DB Connection
        register_setting('taagPlugin', 'taag_db_type');
        register_setting('taagPlugin', 'taag_db_host');
        register_setting('taagPlugin', 'taag_db_user');
        register_setting('taagPlugin', 'taag_db_pass');
        register_setting('taagPlugin', 'taag_db_name');
        
        // Tables
        register_setting('taagPlugin', 'taag_table_buy');
        register_setting('taagPlugin', 'taag_table_sell');

        // Buy Columns
        $buy_fields = [
            'taag_col_buy_date', 'taag_col_buy_name', 'taag_col_buy_strike',
            'taag_col_buy_lot_size', 'taag_col_buy_total_lots', 'taag_col_buy_total_qty',
            'taag_col_buy_entry', 'taag_col_buy_sl', 'taag_col_buy_target',
            'taag_col_buy_profit', 'taag_col_buy_risk', 'taag_col_buy_points'
        ];
        foreach($buy_fields as $field) register_setting('taagPlugin', $field);

        // Sell Columns
        $sell_fields = [
            'taag_col_sell_date', 'taag_col_sell_name', 'taag_col_sell_strike',
            'taag_col_sell_lot_size', 'taag_col_sell_total_lots', 'taag_col_sell_total_qty',
            'taag_col_sell_entry', 'taag_col_sell_sl', 'taag_col_sell_target',
            'taag_col_sell_profit', 'taag_col_sell_risk', 'taag_col_sell_points'
        ];
        foreach($sell_fields as $field) register_setting('taagPlugin', $field);
    }

    public function render_page() {
        // Fetch Tables for Dropdown
        $db = TAA_DB::get_connection();
        $tables = [];
        if ($db) {
            $tables = $db->get_col("SHOW TABLES");
        }

        $buy_table = get_option('taag_table_buy', 'wp_rd_algo_buy_trades');
        $sell_table = get_option('taag_table_sell', 'wp_web_ue_sell_trade_entries');
        
        $buy_cols = TAA_DB::get_columns($buy_table);
        $sell_cols = TAA_DB::get_columns($sell_table);

        // Helper function for dropdowns inside the view
        if (!function_exists('taag_column_dropdown_helper')) {
            function taag_column_dropdown_helper($option_name, $columns, $default_guess = '') {
                $val = get_option($option_name, '');
                if (empty($val) && !empty($default_guess) && in_array($default_guess, $columns)) {
                    $val = $default_guess;
                }
                echo "<select name='" . esc_attr($option_name) . "'>";
                echo "<option value=''>-- Select Column --</option>";
                foreach ($columns as $col) {
                    $selected = selected($val, $col, false);
                    echo "<option value='" . esc_attr($col) . "' $selected>$col</option>";
                }
                echo "</select>";
            }
        }
        
        // Helper for Table Dropdown
        if (!function_exists('taag_table_dropdown_helper')) {
            function taag_table_dropdown_helper($option_name, $tables) {
                $val = get_option($option_name, '');
                echo "<select name='" . esc_attr($option_name) . "' style='width:100%'>";
                echo "<option value=''>-- Select Table --</option>";
                foreach ($tables as $t) {
                    $selected = selected($val, $t, false);
                    echo "<option value='" . esc_attr($t) . "' $selected>$t</option>";
                }
                echo "</select>";
            }
        }

        require_once TAA_PLUGIN_DIR . 'includes/admin-view.php';
    }
     /**
     * Intercepts the Settings Save to Bulk Update the DB Table
     */
    public function save_instruments_to_db($input) {
        global $wpdb;
        $table = $wpdb->prefix . 'taa_instruments';

        // 1. Parse the Text Area Input
        $lines = array_filter(explode("\n", $input));
        
        if (!empty($lines)) {
            // 2. Clear Table (Bulk Replace Strategy)
            $wpdb->query("TRUNCATE TABLE $table");

            // 3. Insert New Rows
            foreach ($lines as $line) {
                $parts = explode('|', trim($line));
                // Format: NAME|LOT|MODE|REQ
                if (count($parts) >= 2) {
                    $name = strtoupper(trim($parts[0]));
                    $lot  = intval(trim($parts[1]));
                    $mode = isset($parts[2]) ? trim($parts[2]) : 'BOTH';
                    $req  = isset($parts[3]) ? trim($parts[3]) : 'NO';

                    $wpdb->insert($table, [
                        'name' => $name,
                        'lot_size' => $lot,
                        'mode' => $mode,
                        'strike_req' => $req
                    ]);
                }
            }
        }
        
        // Return input so it updates the local option cache too (as backup)
        return $input; 
    }
}