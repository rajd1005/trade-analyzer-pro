<?php
// FILE: includes/enqueue-scripts.php
if ( ! defined( 'ABSPATH' ) ) exit;

class TAA_Enqueue {
    public function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
    }

    public function enqueue_admin_scripts( $hook ) {
        // Only load on our plugin page to prevent conflicts
        if ( strpos( $hook, 'trade-analyzer-pro' ) === false ) {
            return;
        }

        // 1. CSS
        wp_enqueue_style( 'taa-admin-style', TAA_URL . 'assets/css/taa-admin.css', [], '1.0.0' );
        // Removed: taa-generator-style

        // 2. JS Dependencies
        // SweetAlert for nice popups
        wp_enqueue_script( 'sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], '11.0.0', true );

        // 3. Main Scripts
        wp_enqueue_script( 'taa-core-script', TAA_URL . 'assets/js/taa-core.js', ['jquery'], '1.0.0', true );
        // Removed: taa-generator-script

        // 4. Data Passing (Localization)
        wp_localize_script( 'taa-core-script', 'taa_vars', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'taa_nonce' )
        ]);
    }
}
new TAA_Enqueue();