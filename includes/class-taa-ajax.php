<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TAA_Ajax {
    public function __construct() {
        // Load Modular AJAX Handlers
        $this->load_modules();
    }

    private function load_modules() {
        // 1. Analyzer Features (AI & Staging Save)
        require_once TAA_PLUGIN_DIR . 'includes/ajax/class-taa-ajax-analyzer.php';
        new TAA_Ajax_Analyzer();

        // 2. Staging Actions (Approve, Reject, Download)
        require_once TAA_PLUGIN_DIR . 'includes/ajax/class-taa-ajax-staging.php';
        new TAA_Ajax_Staging();

        // 3. Loaders (Table Refreshes)
        require_once TAA_PLUGIN_DIR . 'includes/ajax/class-taa-ajax-loaders.php';
        new TAA_Ajax_Loaders();

        // 4. NEW: Instruments Editor
        require_once TAA_PLUGIN_DIR . 'includes/ajax/class-taa-ajax-instruments.php';
        new TAA_Ajax_Instruments();

        // 4. Marketing Actions (Telegram Send) [NEW]
        require_once TAA_PLUGIN_DIR . 'includes/ajax/class-taa-ajax-marketing.php';
        new TAA_Ajax_Marketing(); 

        // Removed: Design Generator Data Fetcher
    }
}