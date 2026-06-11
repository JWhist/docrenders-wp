<?php
/**
 * Plugin Name: DocRenders – PDF Download Button
 * Description: Add a "Download PDF" button to any post or page, powered by the DocRenders API.
 * Version:     1.0.2
 * Author:      DocRenders
 * Author URI:  https://docrenders.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: docrenders
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DOCRENDERS_VERSION', '1.0.2' );
define( 'DOCRENDERS_DIR', plugin_dir_path( __FILE__ ) );
define( 'DOCRENDERS_URL', plugin_dir_url( __FILE__ ) );

require_once DOCRENDERS_DIR . 'includes/class-api-client.php';
require_once DOCRENDERS_DIR . 'includes/class-settings.php';
require_once DOCRENDERS_DIR . 'includes/class-content-pdf.php';
require_once DOCRENDERS_DIR . 'includes/class-woo-invoices.php';

function docrenders_init(): void {
	$settings = new DocRenders_Settings();
	$settings->init();

	$api_key = get_option( 'docrenders_api_key', '' );
	if ( $api_key ) {
		$client = new DocRenders_API_Client( $api_key );

		$pdf = new DocRenders_Content_PDF( $client );
		$pdf->init();

		if ( class_exists( 'WooCommerce' ) && get_option( 'docrenders_woo_enabled' ) ) {
			$woo = new DocRenders_Woo_Invoices( $client );
			$woo->init();
		}
	}
}
add_action( 'plugins_loaded', 'docrenders_init' );
