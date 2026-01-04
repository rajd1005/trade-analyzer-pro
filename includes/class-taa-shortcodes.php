<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TAA_Shortcodes {
    public function __construct() {
        add_shortcode( 'trade_analyzer', [ $this, 'render_analyzer' ] );
        add_shortcode( 'trade_analyzer_staging', [ $this, 'render_staging_dashboard' ] );
        add_shortcode( 'trade_analyzer_approved', [ $this, 'render_approved_dashboard' ] );
        add_shortcode( 'trade_analyzer_history', [ $this, 'render_history_dashboard' ] );
        add_shortcode( 'trade_analyzer_instruments', [ $this, 'render_instruments_editor' ] );
        
        // NEW: Published Gallery Shortcode
        add_shortcode( 'taa_published_gallery', [ $this, 'render_published_gallery' ] );

        add_action( 'wp_enqueue_scripts', [ $this, 'register_and_load_assets' ] );
    }

    public function register_and_load_assets() {
        $ver = '68.0'; // Version Bump

        // --- External Libs ---
        wp_register_style( 'taa-sa2-css', 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css', [], '11.0' );
        wp_register_script( 'taa-sa2-js', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', ['jquery'], '11.0', true );
        wp_register_script( 'taa-painterro', 'https://cdn.jsdelivr.net/npm/painterro@1.2.78/build/painterro.min.js', [], '1.2.78', true );
        wp_register_style( 'taa-viewer-css', 'https://cdnjs.cloudflare.com/ajax/libs/viewerjs/1.11.6/viewer.min.css', [], '1.11.6' );
        wp_register_script( 'taa-viewer-js', 'https://cdnjs.cloudflare.com/ajax/libs/viewerjs/1.11.6/viewer.min.js', [], '1.11.6', true );

        // --- Plugin CSS ---
        wp_register_style( 'taa-core-css', TAA_PLUGIN_URL . 'assets/css/taa-core.css', [], $ver );
        wp_register_style( 'taa-analyzer-css', TAA_PLUGIN_URL . 'assets/css/taa-analyzer.css', [], $ver );
        wp_register_style( 'taa-dashboard-css', TAA_PLUGIN_URL . 'assets/css/taa-dashboard.css', [], $ver );
        wp_register_style( 'taa-modal-css', TAA_PLUGIN_URL . 'assets/css/taa-modal.css', [], $ver );
        wp_register_style( 'taa-mobile-css', TAA_PLUGIN_URL . 'assets/css/taa-mobile.css', [], $ver );
        wp_register_style( 'taa-marketing-css', TAA_PLUGIN_URL . 'assets/css/taa-marketing.css', [], $ver );

        // --- Plugin JS ---
        wp_register_script( 'taa-core', TAA_PLUGIN_URL . 'assets/js/taa-core.js', [ 'jquery', 'taa-sa2-js' ], $ver, true );
        
        // Critical: Localize Nonce for AJAX Security
        wp_localize_script( 'taa-core', 'taa_vars', [ 
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'taa_nonce' ) 
        ]);

        wp_register_script( 'taa-analyzer', TAA_PLUGIN_URL . 'assets/js/taa-analyzer.js', [ 'jquery', 'taa-core' ], $ver, true );
        wp_register_script( 'taa-staging', TAA_PLUGIN_URL . 'assets/js/taa-staging.js', [ 'jquery', 'taa-core', 'taa-painterro' ], $ver, true );
        wp_register_script( 'taa-viewer', TAA_PLUGIN_URL . 'assets/js/taa-viewer.js', [ 'jquery', 'taa-core', 'taa-viewer-js' ], $ver, true );
        
        // Marketing JS
        wp_register_script( 'taa-marketing-js', TAA_PLUGIN_URL . 'assets/js/taa-marketing.js', [ 'jquery', 'taa-core' ], $ver, true );
        
        // TIMEZONE FIX: Pass server time 'today' to JS
        // This ensures upload date matches the server's idea of "today", not the user's computer time.
        wp_localize_script( 'taa-marketing-js', 'taa_mkt_vars', [ 
            'img_path' => TAA_PLUGIN_URL . 'assets/img/',
            'today'    => current_time('Y-m-d') 
        ]);

        wp_register_script( 'taa-daily-report', TAA_PLUGIN_URL . 'assets/js/taa-daily-report.js', [ 'jquery', 'taa-core' ], $ver, true );
        wp_register_script( 'taa-instruments', TAA_PLUGIN_URL . 'assets/js/taa-instruments.js', [ 'jquery' ], $ver, true );

        global $post;
        if ( is_a( $post, 'WP_Post' ) && ( 
             has_shortcode( $post->post_content, 'trade_analyzer' ) || 
             has_shortcode( $post->post_content, 'trade_analyzer_staging' ) || 
             has_shortcode( $post->post_content, 'trade_analyzer_approved' ) || 
             has_shortcode( $post->post_content, 'trade_analyzer_history' ) || 
             has_shortcode( $post->post_content, 'taa_published_gallery' ) ||
             has_shortcode( $post->post_content, 'trade_analyzer_instruments' )
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
            wp_enqueue_script('taa-analyzer');
            wp_enqueue_script('taa-staging');
            wp_enqueue_script('taa-viewer');
            wp_enqueue_script('taa-marketing-js');
            wp_enqueue_script('taa-daily-report');

            if ( has_shortcode( $post->post_content, 'trade_analyzer_instruments' ) ) {
                wp_enqueue_script('taa-instruments');
                wp_localize_script( 'taa-instruments', 'taa_vars', [ 
                    'ajaxurl' => admin_url( 'admin-ajax.php' ),
                    'nonce'   => wp_create_nonce( 'taa_nonce' ) 
                ]);
            }
        }
    }

    public function render_analyzer() {
        $input_mode = get_option('taag_input_mode', 'ai');
        ob_start(); include TAA_PLUGIN_DIR . 'includes/shortcode-analyzer-view.php'; return ob_get_clean();
    }
    public function render_staging_dashboard() {
        ob_start(); include TAA_PLUGIN_DIR . 'includes/shortcode-staging-view.php'; return ob_get_clean();
    }
    public function render_approved_dashboard() {
        ob_start(); include TAA_PLUGIN_DIR . 'includes/shortcode-approved-view.php'; return ob_get_clean();
    }
    public function render_history_dashboard() {
        ob_start(); include TAA_PLUGIN_DIR . 'includes/shortcode-history-view.php'; return ob_get_clean();
    }
    public function render_instruments_editor() {
        ob_start(); include TAA_PLUGIN_DIR . 'includes/shortcode-instruments-view.php'; return ob_get_clean();
    }
    public function render_published_gallery() {
        ob_start(); include TAA_PLUGIN_DIR . 'includes/shortcode-marketing-gallery-view.php'; return ob_get_clean();
    }
}