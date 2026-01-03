<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TAA_Ajax_Loaders {
    public function __construct() {
        // [FIX] Added nopriv hooks to ensure frontend AJAX works
        add_action( 'wp_ajax_taa_reload_staging', [ $this, 'reload_staging_table' ] );
        add_action( 'wp_ajax_nopriv_taa_reload_staging', [ $this, 'reload_staging_table' ] );

        add_action( 'wp_ajax_taa_reload_approved', [ $this, 'reload_approved_table' ] );
        add_action( 'wp_ajax_nopriv_taa_reload_approved', [ $this, 'reload_approved_table' ] );

        add_action( 'wp_ajax_taa_reload_history', [ $this, 'reload_history_table' ] );
        add_action( 'wp_ajax_nopriv_taa_reload_history', [ $this, 'reload_history_table' ] );
    }

    private function get_filter_date() {
        if ( isset($_POST['date']) && !empty($_POST['date']) ) {
            return sanitize_text_field($_POST['date']);
        }
        return current_time('Y-m-d');
    }

    public function reload_staging_table() {
        $date = $this->get_filter_date();
        $today = $date; 
        $taa_is_ajax = true; 
        
        $file = TAA_PLUGIN_DIR . 'includes/shortcode-staging-view.php';
        if(file_exists($file)) {
            ob_start();
            include $file;
            $html = ob_get_clean();
            wp_send_json_success( $html );
        } else {
            wp_send_json_error('View file missing');
        }
    }

    public function reload_approved_table() {
        $date = $this->get_filter_date();
        $today = $date;
        $taa_is_ajax = true;
        
        $file = TAA_PLUGIN_DIR . 'includes/shortcode-approved-view.php';
        if(file_exists($file)) {
            ob_start();
            include $file;
            $html = ob_get_clean();
            wp_send_json_success( $html );
        } else {
            wp_send_json_error('View file missing');
        }
    }

    public function reload_history_table() {
        $date = $this->get_filter_date();
        $today = $date;
        $taa_is_ajax = true;
        
        $file = TAA_PLUGIN_DIR . 'includes/shortcode-history-view.php';
        if(file_exists($file)) {
            ob_start();
            include $file;
            $html = ob_get_clean();
            wp_send_json_success( $html );
        } else {
            wp_send_json_error('View file missing');
        }
    }
}