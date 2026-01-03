<?php
/**
 * Plugin Name: Trade Analyzer Pro (Gemini 2.0 - V30 Modular)
 * Description: V30 - Complete Refactor. Maker-Checker System, Staging DB, Dual Email Alerts, and Modular Codebase.
 * Version: 30.0
 * Author: Your Name
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Define Constants
define( 'TAA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TAA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include Classes
require_once TAA_PLUGIN_DIR . 'includes/class-taa-db.php';
require_once TAA_PLUGIN_DIR . 'includes/class-taa-activator.php';
require_once TAA_PLUGIN_DIR . 'includes/class-taa-admin.php';
require_once TAA_PLUGIN_DIR . 'includes/class-taa-shortcodes.php';
require_once TAA_PLUGIN_DIR . 'includes/class-taa-ajax.php';
require_once TAA_PLUGIN_DIR . 'includes/class-taa-marketing.php';

// Activation Hook (Create Staging Table)
register_activation_hook( __FILE__, [ 'TAA_Activator', 'activate' ] );

// Initialize Admin & Shortcodes
add_action( 'plugins_loaded', function() {
    new TAA_Admin();
    new TAA_Shortcodes();
    new TAA_Ajax();
});