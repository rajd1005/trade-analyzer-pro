<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TAA_Shortcodes {
    public function __construct() {
        add_shortcode( 'trade_analyzer', [ $this, 'render_analyzer' ] );
        add_shortcode( 'trade_analyzer_staging', [ $this, 'render_staging_dashboard' ] );
        add_shortcode( 'trade_analyzer_approved', [ $this, 'render_approved_dashboard' ] );
        add_shortcode( 'trade_analyzer_history', [ $this, 'render_history_dashboard' ] );
        
        // NEW: Register Instrument Editor Shortcode
        add_shortcode( 'trade_analyzer_instruments', [ $this, 'render_instruments_editor' ] );

        add_action( 'wp_enqueue_scripts', [ $this, 'register_and_load_assets' ] );
    }

    public function register_and_load_assets() {
        $ver = '65.0'; // Version bump

        // --- REGISTER EXTERNAL LIBS ---
        wp_register_style( 'taa-sa2-css', 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css', [], '11.0' );
        wp_register_script( 'taa-sa2-js', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', ['jquery'], '11.0', true );
        wp_register_script( 'taa-painterro', 'https://cdn.jsdelivr.net/npm/painterro@1.2.78/build/painterro.min.js', [], '1.2.78', true );
        wp_register_style( 'taa-viewer-css', 'https://cdnjs.cloudflare.com/ajax/libs/viewerjs/1.11.6/viewer.min.css', [], '1.11.6' );
        wp_register_script( 'taa-viewer-js', 'https://cdnjs.cloudflare.com/ajax/libs/viewerjs/1.11.6/viewer.min.js', [], '1.11.6', true );

        // --- REGISTER PLUGIN STYLES ---
        wp_register_style( 'taa-core-css', TAA_PLUGIN_URL . 'assets/css/taa-core.css', [], $ver );
        wp_register_style( 'taa-analyzer-css', TAA_PLUGIN_URL . 'assets/css/taa-analyzer.css', [], $ver );
        wp_register_style( 'taa-dashboard-css', TAA_PLUGIN_URL . 'assets/css/taa-dashboard.css', [], $ver );
        wp_register_style( 'taa-modal-css', TAA_PLUGIN_URL . 'assets/css/taa-modal.css', [], $ver );
        wp_register_style( 'taa-mobile-css', TAA_PLUGIN_URL . 'assets/css/taa-mobile.css', [], $ver );
        wp_register_style( 'taa-marketing-css', TAA_PLUGIN_URL . 'assets/css/taa-marketing.css', [], $ver );

        // --- REGISTER PLUGIN SCRIPTS ---
        wp_register_script( 'taa-core', TAA_PLUGIN_URL . 'assets/js/taa-core.js', [ 'jquery', 'taa-sa2-js' ], $ver, true );
        wp_register_script( 'taa-analyzer', TAA_PLUGIN_URL . 'assets/js/taa-analyzer.js', [ 'jquery', 'taa-core' ], $ver, true );
        wp_register_script( 'taa-staging', TAA_PLUGIN_URL . 'assets/js/taa-staging.js', [ 'jquery', 'taa-core', 'taa-painterro' ], $ver, true );
        wp_register_script( 'taa-viewer', TAA_PLUGIN_URL . 'assets/js/taa-viewer.js', [ 'jquery', 'taa-core', 'taa-viewer-js' ], $ver, true );
        wp_register_script( 'taa-marketing-js', TAA_PLUGIN_URL . 'assets/js/taa-marketing.js', [ 'jquery', 'taa-core' ], $ver, true );
        wp_register_script( 'taa-daily-report', TAA_PLUGIN_URL . 'assets/js/taa-daily-report.js', [ 'jquery', 'taa-core' ], $ver, true );

        // NEW: Register Instruments JS
        wp_register_script( 'taa-instruments', TAA_PLUGIN_URL . 'assets/js/taa-instruments.js', [ 'jquery' ], $ver, true );

        // --- ENQUEUE ---
        global $post;

        // 1. Existing Dashboards Enqueue
        if ( is_a( $post, 'WP_Post' ) && ( 
             has_shortcode( $post->post_content, 'trade_analyzer' ) || 
             has_shortcode( $post->post_content, 'trade_analyzer_staging' ) || 
             has_shortcode( $post->post_content, 'trade_analyzer_approved' ) || 
             has_shortcode( $post->post_content, 'trade_analyzer_history' )
           ) ) {
            
            wp_enqueue_style('taa-sa2-css');
            wp_enqueue_style('taa-viewer-css');
            wp_enqueue_style('taa-core-css');
            wp_enqueue_style('taa-analyzer-css');
            wp_enqueue_style('taa-dashboard-css');
            wp_enqueue_style('taa-modal-css');
            wp_enqueue_style('taa-mobile-css');
            wp_enqueue_style('taa-marketing-css');

            wp_enqueue_script('taa-core');
            wp_localize_script( 'taa-core', 'taa_vars', [ 'ajaxurl' => admin_url( 'admin-ajax.php' ) ]);

            wp_enqueue_script('taa-analyzer');
            wp_enqueue_script('taa-staging');
            wp_enqueue_script('taa-viewer');
            
            wp_enqueue_script('taa-marketing-js');
            wp_localize_script( 'taa-marketing-js', 'taa_mkt_vars', [ 'img_path' => TAA_PLUGIN_URL . 'assets/img/' ]);

            wp_enqueue_script('taa-daily-report');
        }

        // 2. NEW: Instrument Editor Enqueue
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'trade_analyzer_instruments' ) ) {
            wp_enqueue_style('taa-dashboard-css'); // Re-use dashboard table styles
            wp_enqueue_style('taa-sa2-css'); // SweetAlert for notifications
            
            wp_enqueue_script('taa-sa2-js'); 
            wp_enqueue_script('taa-instruments');
            
            // Pass Nonce for security
            wp_localize_script( 'taa-instruments', 'taa_vars', [ 
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'taa_nonce' ) 
            ]);
        }
    }

    public function render_analyzer() {
        $global_lots = get_option('taag_default_total_lots', '6');
        $raw_list = get_option('taag_instruments_list', '');
        $lines = explode("\n", $raw_list);
        $instruments = [];
        foreach($lines as $line) {
            $parts = explode('|', $line);
            if(count($parts) >= 2) {
                $name = trim($parts[0]);
                $lot = trim($parts[1]);
                $mode = isset($parts[2]) ? strtoupper(trim($parts[2])) : 'BOTH';
                $reqStrike = isset($parts[3]) ? strtoupper(trim($parts[3])) : 'NO';
                $val_str = "$name|$lot|$mode|$reqStrike";
                $instruments[] = ['name' => $name, 'value' => $val_str];
            }
        }
        $input_mode = get_option('taag_input_mode', 'ai');
        ob_start(); include TAA_PLUGIN_DIR . 'includes/shortcode-analyzer-view.php'; return ob_get_clean();
    }

    public function render_staging_dashboard() {
        $timezone = new DateTimeZone("Asia/Kolkata");
        $today = (new DateTime("now", $timezone))->format('Y-m-d');
        ob_start(); include TAA_PLUGIN_DIR . 'includes/shortcode-staging-view.php'; return ob_get_clean();
    }

    public function render_approved_dashboard() {
        $timezone = new DateTimeZone("Asia/Kolkata");
        $today = (new DateTime("now", $timezone))->format('Y-m-d');
        ob_start(); include TAA_PLUGIN_DIR . 'includes/shortcode-approved-view.php'; return ob_get_clean();
    }

    public function render_history_dashboard() {
        $timezone = new DateTimeZone("Asia/Kolkata");
        $today = (new DateTime("now", $timezone))->format('Y-m-d');
        ob_start(); include TAA_PLUGIN_DIR . 'includes/shortcode-history-view.php'; return ob_get_clean();
    }

    // NEW FUNCTION: Instruments Editor Renderer
    public function render_instruments_editor() {
        ob_start(); 
        include TAA_PLUGIN_DIR . 'includes/shortcode-instruments-view.php'; 
        return ob_get_clean();
    }
}