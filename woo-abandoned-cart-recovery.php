<?php
/**
 * Plugin Name: Abandoned Cart Recovery for WooCommerce
 * Plugin URI: https://villatheme.com/extensions/woo-abandoned-cart-recovery/
 * Description: Capture abandoned cart & send reminder emails to the customers.
 * Version: 1.1.6
 * Author: VillaTheme
 * Author URI: https://villatheme.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woo-abandoned-cart-recovery
 * Domain Path: /languages
 * Copyright 2019-2024 VillaTheme.com. All rights reserved.
 * Requires Plugins: woocommerce
 * Requires at least: 5.0
 * Tested up to: 6.6
 * WC requires at least: 7.0
 * WC tested up to: 9.3
 * Requires PHP: 7.0
 **/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

//Compatible with High-Performance order storage (COT)
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

if ( is_plugin_active( 'woocommerce-abandoned-cart-recovery/woocommerce-abandoned-cart-recovery.php' ) ) {
	return;
}

define( 'WACV_VERSION', '1.1.6' );


define( 'WACV_SLUG', 'woo-abandoned-cart-recovery' );
define( 'WACV_DIR', plugin_dir_path( __FILE__ ) );
define( 'WACV_LANGUAGES', WACV_DIR . "/languages" . DIRECTORY_SEPARATOR );
define( 'WACV_INCLUDES', WACV_DIR . "/includes" . DIRECTORY_SEPARATOR );
define( 'WACV_VIEWS', WACV_DIR . "/views" . DIRECTORY_SEPARATOR );
define( 'WACV_TEMPLATES', WACV_INCLUDES . "templates" . DIRECTORY_SEPARATOR );
define( 'WACV_PRO_URL', 'https://1.envato.market/roBbv' );

class WACVPInit_F {
    public function __construct() {
        add_action('plugins_loaded', array( $this, 'init') );
	    register_activation_hook( __FILE__, array($this, 'wacv_activate') );
    }

    public function init() {
	    $include_dir = plugin_dir_path( __FILE__ ) . 'includes/';

	    if ( ! class_exists( 'VillaTheme_Require_Environment' ) ) {
		    include_once $include_dir . 'support.php';
	    }
	    $environment = new \VillaTheme_Require_Environment( [
			    'plugin_name'     => 'Abandoned Cart Recovery for WooCommerce',
			    'php_version'     => '7.0',
			    'wp_version'      => '5.0',
			    'require_plugins' => [
				    [
					    'slug'             => 'woocommerce',
					    'name'             => 'WooCommerce',
					    'required_version' => '7.0'
				    ]
			    ]
		    ]
	    );

	    if ( $environment->has_error() ) {
		    return;
	    }

	    add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'wacv_add_action_links') );
	    add_action( 'wpmu_new_blog', array( $this, 'wacvf_activate_new_blog') );
	    add_filter( 'wpmu_drop_tables', array( $this, 'wacv_delete_plugin_tables', 10, 2) );

	    $this->load_classes();
    }

	public function load_classes( ) {
		require_once WACV_INCLUDES . "define.php";
		\WACV\Inc\load_class();
	}

	public function wacv_add_action_links( $links ) {
		$settings_link = array(
			'<a href="' . admin_url( 'admin.php?page=wacv_settings' ) . '">' . esc_html__( 'Settings', 'woo-abandoned-cart-recovery' ) . '</a>',
		);

		return array_merge( $links, $settings_link );
	}

	public function wacvf_activate_new_blog( $blog_id ) {
		if ( is_plugin_active_for_network( 'woo-abandoned-cart-recovery/woo-abandoned-cart-recovery.php' ) ) {
			switch_to_blog( $blog_id );
			require_once WACV_INCLUDES . "plugin.php";
			$wacv_plugin = \WACV\Inc\Plugin::get_instance();
			$wacv_plugin->single_active();
			restore_current_blog();
		}
	}

	public function wacv_activate( $network_wide ) {
		require_once WACV_INCLUDES . "plugin.php";
		$wacv_plugin = \WACV\Inc\Plugin::get_instance();
		$wacv_plugin->activate( $network_wide );
	}

	public function wacv_delete_plugin_tables( $tables, $blog_id ) {
		if ( empty( $blog_id ) || 1 == $blog_id || $blog_id != $GLOBALS['blog_id'] ) {
			return $tables;
		}
		global $wpdb;
		$blog_prefix   = $wpdb->get_blog_prefix( $blog_id );
		$plugin_tables = array(
			'wacv_abandoned_cart_record',
			'wacv_guest_info_record',
			'wacv_email_history',
			'wacv_cart_log'
		);
		foreach ( $plugin_tables as $k => $table ) {
			$tables[ $table ] = $blog_prefix . $table;
		}

		return $tables;
	}
}

new WACVPInit_F();



