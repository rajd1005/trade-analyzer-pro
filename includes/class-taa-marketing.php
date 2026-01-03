<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TAA_Marketing {
    public function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function enqueue_assets( $hook ) {
        $ver = '1.7.0'; // Version bump for styles
        
        wp_enqueue_style( 'taa-marketing-css', TAA_PLUGIN_URL . 'assets/css/taa-marketing.css', [], $ver );
        wp_enqueue_script( 'taa-marketing-js', TAA_PLUGIN_URL . 'assets/js/taa-marketing.js', [ 'jquery', 'taa-core' ], $ver, true );

        wp_localize_script( 'taa-marketing-js', 'taa_mkt_vars', [
            'img_path' => TAA_PLUGIN_URL . 'assets/img/'
        ]);
    }
}

new TAA_Marketing();