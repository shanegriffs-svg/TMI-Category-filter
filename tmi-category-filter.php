<?php
/**
 * Plugin Name: TMI Category Filter
 * Description: Lightweight WooCommerce category filters for TMI product archives, beginning with Zero Turn Mowers.
 * Version: 0.3.0
 * Author: TMI Tractor Shop
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * Text Domain: tmi-category-filter
 */

defined( 'ABSPATH' ) || exit;

define( 'TMI_CATEGORY_FILTER_VERSION', '0.3.0' );
define( 'TMI_CATEGORY_FILTER_FILE', __FILE__ );
define( 'TMI_CATEGORY_FILTER_DIR', plugin_dir_path( __FILE__ ) );
define( 'TMI_CATEGORY_FILTER_URL', plugin_dir_url( __FILE__ ) );

require_once TMI_CATEGORY_FILTER_DIR . 'includes/class-tmi-filter-config.php';
require_once TMI_CATEGORY_FILTER_DIR . 'includes/class-tmi-filter-query.php';
require_once TMI_CATEGORY_FILTER_DIR . 'includes/class-tmi-filter-renderer.php';
require_once TMI_CATEGORY_FILTER_DIR . 'includes/class-tmi-filter-admin.php';

final class TMI_Category_Filter {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		TMI_Filter_Query::init();
		TMI_Filter_Renderer::init();

		if ( is_admin() ) {
			TMI_Filter_Admin::init();
		}
	}

	public static function enqueue_assets() {
		if ( ! TMI_Filter_Config::is_supported_archive() ) {
			return;
		}

		wp_enqueue_style(
			'tmi-category-filter',
			TMI_CATEGORY_FILTER_URL . 'assets/css/tmi-category-filter.css',
			array(),
			TMI_CATEGORY_FILTER_VERSION
		);

		wp_enqueue_script(
			'tmi-category-filter',
			TMI_CATEGORY_FILTER_URL . 'assets/js/tmi-category-filter.js',
			array(),
			TMI_CATEGORY_FILTER_VERSION,
			true
		);
	}
}

add_action( 'plugins_loaded', array( 'TMI_Category_Filter', 'init' ), 20 );
