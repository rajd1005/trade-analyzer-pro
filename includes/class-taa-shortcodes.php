<?php
/**
 * The file that defines the shortcodes for the plugin
 *
 * @link       https://rdalgo.in
 * @since      1.0.0
 *
 * @package    Trade_Analyzer_Pro
 * @subpackage Trade_Analyzer_Pro/includes
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class TAA_Shortcodes {

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {

		// 1. Main Analyzer Dashboard
		add_shortcode( 'trade_analyzer', array( $this, 'render_analyzer' ) );

		// 2. Staging Area (Approval Queue)
		add_shortcode( 'trade_analyzer_staging', array( $this, 'render_staging_dashboard' ) );

		// 3. Approved Trades (Journal)
		add_shortcode( 'trade_analyzer_approved', array( $this, 'render_approved_dashboard' ) );

		// 4. Rejected Trades (Trash) - (Optional View)
		add_shortcode( 'trade_analyzer_rejected', array( $this, 'render_rejected_dashboard' ) );

		// 5. History / Daily Report
		add_shortcode( 'trade_analyzer_history', array( $this, 'render_history_dashboard' ) );

		// 6. Instruments Manager
		add_shortcode( 'trade_analyzer_instruments', array( $this, 'render_instruments_editor' ) );

		// 7. NEW: Marketing Gallery Table (The "2nd Plugin" Style View)
		add_shortcode( 'taa_marketing_gallery', array( $this, 'render_marketing_gallery_view' ) );

		// Register Assets
		add_action( 'wp_enqueue_scripts', array( $this, 'register_and_load_assets' ) );
	}

	/**
	 * Register and Enqueue Scripts/Styles based on Shortcode presence
	 */
	public function register_and_load_assets() {
		$ver = '33.1'; // Updated Version for Fixes

		// --- External Libraries ---
		// SweetAlert2
		wp_register_style( 'taa-sa2-css', 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css', [], '11.0' );
		wp_register_script( 'taa-sa2-js', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', ['jquery'], '11.0', true );
		
		// Painterro (Image Editor)
		wp_register_script( 'taa-painterro', 'https://cdn.jsdelivr.net/npm/painterro@1.2.78/build/painterro.min.js', [], '1.2.78', true );
		
		// ViewerJS (Image Zoom)
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
		// Core (Global)
		wp_register_script( 'taa-core', TAA_PLUGIN_URL . 'assets/js/taa-core.js', [ 'jquery', 'taa-sa2-js' ], $ver, true );
		
		// Localize Core (Nonce & AJAX URL)
		wp_localize_script( 'taa-core', 'taa_vars', [ 
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'taa_nonce' ) 
		]);

		// Module Scripts
		wp_register_script( 'taa-analyzer', TAA_PLUGIN_URL . 'assets/js/taa-analyzer.js', [ 'jquery', 'taa-core' ], $ver, true );
		wp_register_script( 'taa-staging', TAA_PLUGIN_URL . 'assets/js/taa-staging.js', [ 'jquery', 'taa-core', 'taa-painterro' ], $ver, true );
		wp_register_script( 'taa-viewer', TAA_PLUGIN_URL . 'assets/js/taa-viewer.js', [ 'jquery', 'taa-core', 'taa-viewer-js' ], $ver, true );
		wp_register_script( 'taa-daily-report', TAA_PLUGIN_URL . 'assets/js/taa-daily-report.js', [ 'jquery', 'taa-core' ], $ver, true );
		wp_register_script( 'taa-instruments', TAA_PLUGIN_URL . 'assets/js/taa-instruments.js', [ 'jquery' ], $ver, true );

		// Marketing JS (Popups & Publishing)
		wp_register_script( 'taa-marketing-js', TAA_PLUGIN_URL . 'assets/js/taa-marketing.js', [ 'jquery', 'taa-core' ], $ver, true );
		
		// Localize Marketing (Paths & Date)
		wp_localize_script( 'taa-marketing-js', 'taa_mkt_vars', [ 
			'img_path' => TAA_PLUGIN_URL . 'assets/img/',
			'today'    => current_time('Y-m-d') // Server date for logic
		]);

		// --- Conditional Loading ---
		global $post;
		if ( is_a( $post, 'WP_Post' ) && ( 
			 has_shortcode( $post->post_content, 'trade_analyzer' ) || 
			 has_shortcode( $post->post_content, 'trade_analyzer_staging' ) || 
			 has_shortcode( $post->post_content, 'trade_analyzer_approved' ) || 
			 has_shortcode( $post->post_content, 'trade_analyzer_history' ) || 
			 has_shortcode( $post->post_content, 'trade_analyzer_rejected' ) ||
			 has_shortcode( $post->post_content, 'trade_analyzer_instruments' ) ||
			 has_shortcode( $post->post_content, 'taa_marketing_gallery' ) // New Shortcode
		   ) ) {
			
			// Enqueue CSS
			wp_enqueue_style('taa-sa2-css');
			wp_enqueue_style('taa-viewer-css');
			wp_enqueue_style('taa-core-css');
			wp_enqueue_style('taa-analyzer-css');
			wp_enqueue_style('taa-dashboard-css');
			wp_enqueue_style('taa-modal-css');
			wp_enqueue_style('taa-mobile-css');
			wp_enqueue_style('taa-marketing-css');

			// Enqueue JS
			wp_enqueue_script('taa-core');
			wp_enqueue_script('taa-analyzer');
			wp_enqueue_script('taa-staging');
			wp_enqueue_script('taa-viewer');
			wp_enqueue_script('taa-marketing-js');
			wp_enqueue_script('taa-daily-report');

			// Instrument Editor Logic
			if ( has_shortcode( $post->post_content, 'trade_analyzer_instruments' ) ) {
				wp_enqueue_script('taa-instruments');
				wp_localize_script( 'taa-instruments', 'taa_vars', [ 
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'taa_nonce' ) 
				]);
			}
		}
	}

/**
	 * Render: Analyzer
	 */
	public function render_analyzer() {
		// Input Mode Check (AI/Manual)
		$input_mode = get_option('taag_input_mode', 'ai');
		
		// Default Lots
		$global_lots = get_option('taag_default_total_lots', '6');

		// [UPDATED] FETCH INSTRUMENTS FROM DB TABLE
		global $wpdb;
		$table_inst = $wpdb->prefix . 'taa_instruments';
		
		// We use ARRAY_A to get an associative array
		$db_rows = $wpdb->get_results("SELECT * FROM $table_inst ORDER BY name ASC", ARRAY_A);
		
		$instruments = [];
		if ($db_rows) {
			foreach($db_rows as $row) {
				// Reconstruct the "Value" string that the JS expects: "NAME|LOT|MODE|REQ"
				// This ensures we don't break your existing JS logic in taa-analyzer.js
				$combined_val = $row['name'] . '|' . $row['lot_size'] . '|' . $row['mode'] . '|' . $row['strike_req'];
				
				$instruments[] = [
					'name'  => $row['name'],
					'value' => $combined_val
				];
			}
		}

		ob_start(); 
		include TAA_PLUGIN_DIR . 'includes/shortcode-analyzer-view.php'; 
		return ob_get_clean();
	}

	/**
	 * Render: Staging
	 */
	public function render_staging_dashboard() {
		ob_start(); 
		include TAA_PLUGIN_DIR . 'includes/shortcode-staging-view.php'; 
		return ob_get_clean();
	}

	/**
	 * Render: Approved
	 */
	public function render_approved_dashboard() {
		ob_start(); 
		include TAA_PLUGIN_DIR . 'includes/shortcode-approved-view.php'; 
		return ob_get_clean();
	}

	/**
	 * Render: History
	 */
	public function render_history_dashboard() {
		ob_start(); 
		include TAA_PLUGIN_DIR . 'includes/shortcode-history-view.php'; 
		return ob_get_clean();
	}

	/**
	 * Render: Rejected (Optional)
	 */
	public function render_rejected_dashboard() {
		ob_start(); 
		include TAA_PLUGIN_DIR . 'includes/shortcode-rejected-view.php'; 
		return ob_get_clean();
	}

	/**
	 * Render: Instruments
	 */
	public function render_instruments_editor() {
		ob_start(); 
		include TAA_PLUGIN_DIR . 'includes/shortcode-instruments-view.php'; 
		return ob_get_clean();
	}

	/**
	 * Render: Marketing Gallery (New Table View)
	 */
	public function render_marketing_gallery_view() {
		// Points to the new table view file
		ob_start(); 
		include TAA_PLUGIN_DIR . 'includes/shortcode-marketing-gallery-view.php'; 
		return ob_get_clean();
	}

}