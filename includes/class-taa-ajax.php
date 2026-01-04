<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TAA_Ajax {
    public function __construct() {
        $this->load_modules();
    }

    private function load_modules() {
        // 1. Analyzer Features
        require_once TAA_PLUGIN_DIR . 'includes/ajax/class-taa-ajax-analyzer.php';
        new TAA_Ajax_Analyzer();

        // 2. Staging Actions
        require_once TAA_PLUGIN_DIR . 'includes/ajax/class-taa-ajax-staging.php';
        new TAA_Ajax_Staging();

        // 3. Loaders
        require_once TAA_PLUGIN_DIR . 'includes/ajax/class-taa-ajax-loaders.php';
        new TAA_Ajax_Loaders();

        // 4. Instruments Editor
        require_once TAA_PLUGIN_DIR . 'includes/ajax/class-taa-ajax-instruments.php';
        new TAA_Ajax_Instruments();

        // 5. Marketing (Telegram, Publish, Gallery)
        require_once TAA_PLUGIN_DIR . 'includes/ajax/class-taa-ajax-marketing.php';
        new TAA_Ajax_Marketing(); 
    }
}