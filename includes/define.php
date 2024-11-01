<?php

namespace WACV\Inc;

use WACV\Inc\Email\Email_Templates;
use WACV\Inc\Email\Send_Email_Cron;
use WACV\Inc\Execute\Abandoned_Cart;
use WACV\Inc\Execute\Cart_Logs;
use WACV\Inc\Execute\Guest;
use WACV\Inc\Execute\Recovered;
use WACV\Inc\Reports\Reports;
use WACV\Inc\Settings\Admin_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


$plugin_url = plugins_url( '', __FILE__ );
$plugin_url = str_replace( '/includes', '', $plugin_url );

define( 'WACV_CSS', $plugin_url . "/assets/css/" );
define( 'WACV_CSS_DIR', WACV_DIR . "assets" . DIRECTORY_SEPARATOR . "css" . DIRECTORY_SEPARATOR );
define( 'WACV_JS', $plugin_url . "/assets/js/" );
define( 'WACV_JS_DIR', WACV_DIR . "assets" . DIRECTORY_SEPARATOR . "js" . DIRECTORY_SEPARATOR );
define( 'WACV_IMAGES', $plugin_url . "/assets/img/" );
define( 'WACV_FLAG', $plugin_url . "/assets/img/flag/" );

define( 'WACV_CURRENT_TIME', current_time( 'U' ) );

//Auto load class
spl_autoload_register( function ( $class ) {
	$prefix   = __NAMESPACE__;
	$base_dir = __DIR__;
	$len      = strlen( $prefix );

	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		return;
	}

	$relative_class = strtolower( substr( $class, $len ) );
	$relative_class = strtolower( str_replace( '_', '-', $relative_class ) );
	$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

	if ( file_exists( $file ) ) {
		require_once $file;
	} else {
		return;
	}
} );


/*
 * Initialize Plugin
 */
function load_class() {
	Init::get_instance();
	Admin_Settings::get_instance();
	Guest::get_instance();
	Abandoned_Cart::get_instance();
	Recovered::get_instance();
	Send_Email_Cron::get_instance();
	Email_Templates::get_instance();
	Reports::get_instance();
	Ajax::get_instance();
	Cron::get_instance();
	Cart_Logs::get_instance();

	if ( is_file( WACV_INCLUDES . 'support.php' ) ) {
		include_once( WACV_INCLUDES . 'support.php' );
	}
	if ( class_exists( '\VillaTheme_Support' ) ) {
		new \VillaTheme_Support(
			array(
				'support'   => 'https://wordpress.org/support/plugin/woo-abandoned-cart-recovery/',
				'docs'      => 'https://docs.villatheme.com/?item=woocommerce-abandoned-cart-recovery',
				'review'    => 'https://wordpress.org/support/plugin/woo-abandoned-cart-recovery/reviews/?rate=5#rate-response',
				'pro_url'   => WACV_PRO_URL,
				'css'       => WACV_CSS,
				'image'     => WACV_IMAGES,
				'slug'      => WACV_SLUG,
				'menu_slug' => 'wacv_sections',
				'survey_url' => 'https://script.google.com/macros/s/AKfycbxLNv3ouOgdnFYxI1UQ2s2u8vAqOtTiINytkbXeC4zGuKBwE9tVn69AvxK3JBqqXbPE/exec',
				'version'   => WACV_VERSION
			)
		);
	}

}



class Init {

	protected static $instance = null;

	private function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'wacv_admin_enqueue' ) );
		add_action( 'init', array( $this, 'plugin_textdomain' ) );

	}

	public static function get_instance() {

		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function plugin_textdomain() {
		$locale = is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
		load_textdomain( 'woo-abandoned-cart-recovery', WACV_LANGUAGES . "woo-abandoned-cart-recovery-$locale.mo" );
		load_plugin_textdomain( 'woo-abandoned-cart-recovery', false, WACV_LANGUAGES );
	}

	public function register_script( $handle, $depend = array(), $min = '' ) {
		$min = $min ? '.min' : '';
		wp_register_script( WACV_SLUG . $handle, WACV_JS . $handle . $min . '.js', $depend, WACV_VERSION, false );
	}

	public function register_style( $handle, $min = '' ) {
		$min = $min ? '.min' : '';
		wp_register_style( WACV_SLUG . $handle, WACV_CSS . $handle . $min . '.css', '', WACV_VERSION );
	}

	public function wacv_admin_enqueue() {
		if ( isset( $_REQUEST['_wacv_admin_nonce'] ) && ! wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['_wacv_admin_nonce'] ) ), 'wacv_admin_nonce' ) ) {
			return;
		}
		$multi_lang = array();
		if ( is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) ) {
			global $sitepress;
			$default_lang   = $sitepress->get_default_language();
			$wpml_languages = icl_get_languages( 'skip_missing=N&orderby=KEY&order=DIR&link_empty_to=str' );

			if ( count( $wpml_languages ) ) {
				foreach ( $wpml_languages as $key => $language ) {
					if ( $key != $default_lang ) {
						$multi_lang[] = $key;
					}
				}
			}
		} elseif ( class_exists( 'Polylang' ) ) {
			/*Polylang*/
			$languages    = pll_languages_list();
			$default_lang = pll_default_language( 'slug' );
			foreach ( $languages as $language ) {
				if ( $language == $default_lang ) {
					continue;
				}
				$multi_lang[] = $language;
			}
		}

		$page_id = get_current_screen()->id;

		switch ( $page_id ) {
			case 'abandoned-cart_page_wacv_settings':
				$this->plugin_enqueue_script( 'admin', array( 'jquery' ) );
				$this->plugin_enqueue_script( 'select2', array( 'jquery' ) );
				$this->plugin_enqueue_script( 'jquery.address-1.6.min', array( 'jquery' ) );
				$this->plugin_enqueue_script( 'tab.min', array( 'jquery' ) );
				$this->plugin_enqueue_script( 'accordion.min', array( 'jquery' ) );
				$this->plugin_enqueue_script( 'date-picker', array( 'jquery' ) );
				wp_enqueue_script( 'wp-color-picker' );
				wp_enqueue_style( 'wp-color-picker' );

				$this->plugin_enqueue_style( array(
					'admin-settings',
					'checkbox.min',
					'select2.min',
					'form.min',
					'segment.min',
					'table.min',
					'tab.min',
					'menu.min',
					'button.min',
					'icon.min',
					'popup.min',
					'accordion.min',
					'message.min',
					'flag.min',
				) );

				$obj = array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'wacv_nonce' )
				);

				$list_template = Functions::get_email_template();
				wp_localize_script( WACV_SLUG . 'admin', 'wacvEmailTemplatesList', $list_template );
				wp_localize_script( WACV_SLUG . 'admin', 'wacv_ls', $obj );
				wp_localize_script( WACV_SLUG . 'admin', 'wacv_ml', $multi_lang );
				break;

			case 'toplevel_page_wacv_sections':
				$this->plugin_enqueue_script( 'date-picker', array( 'jquery' ) );
				$this->plugin_enqueue_script( 'abandoned-report', array( 'jquery' ) );

				$this->plugin_enqueue_style( array( 'admin-settings', 'flag.min', 'icon.min' ) );

				$obj = array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'wacv_nonce' )
				);
				wp_localize_script( WACV_SLUG . 'abandoned-report', 'wacv_ls', $obj );
				break;

			case 'abandoned-cart_page_wacv_reports':
				if ( ! isset( $_GET['tab'] ) ) {
					$this->plugin_enqueue_script( 'chart.min' );
					$this->plugin_enqueue_script( 'reports', array( 'jquery' ) );

					$obj = array(
						'ajax_url' => admin_url( 'admin-ajax.php' ),
						'currency' => get_woocommerce_currency_symbol(),
						'nonce'    => wp_create_nonce( 'wacv_get_reports' )
					);
					wp_localize_script( WACV_SLUG . 'reports', 'wacv_ls', $obj );
					$this->plugin_enqueue_style( array( 'chart.min' ) );

				} elseif ( isset( $_GET['tab'] ) && $_GET['tab'] == 'cart_logs' ) {
					$this->plugin_enqueue_script( 'date-picker', array( 'jquery' ) );
				}
				$this->plugin_enqueue_style( array( 'reports', 'w3', 'flag.min' ) );
				break;

			case 'wacv_email_template':
				wp_enqueue_media();
				wp_enqueue_editor();
				$this->plugin_enqueue_script( 'select2' );
				$this->plugin_enqueue_script( 'email-template',
					array( 'jquery', 'jquery-ui-sortable', 'jquery-ui-draggable', 'wp-color-picker' ) );
				$this->plugin_enqueue_script( 'coupon-setting', array( 'jquery' ) );

				$this->plugin_enqueue_style(
					array( 'email-template', 'w3', 'checkbox.min', 'select2.min', 'icon.min' )
				);
				wp_enqueue_style( 'wp-color-picker' );

				$obj = array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'img_src'  => WACV_IMAGES,
					'nonce'    => wp_create_nonce( 'wacv_nonce' ),
				);
				wp_localize_script( WACV_SLUG . 'email-template', 'wacv_ls', $obj );
				break;
		}
	}

	public function plugin_enqueue_script( $script, $depend = array() ) {
		wp_enqueue_script( WACV_SLUG . $script, WACV_JS . $script . '.js', $depend, WACV_VERSION, false );
	}

	public function plugin_enqueue_style( $styles ) {
		if ( is_array( $styles ) ) {
			foreach ( $styles as $style ) {
				wp_enqueue_style( WACV_SLUG . $style, WACV_CSS . $style . '.css', '', WACV_VERSION );
			}
		} else {
			wp_enqueue_style( WACV_SLUG . $styles, WACV_CSS . $styles . '.css', '', WACV_VERSION );
		}
	}


	public function update_email_template_notice() {
		$check_notice = get_option( 'wacv_hide_notice' );
		if ( $check_notice ) {
			return;
		}
		?>
        <div id="wacv-message" class="notice notice-warning is-dismissible">
            <p style="font-size: 15px;">
				<?php esc_html_e( 'Email templates have been updated. Please re-create your email templates.', 'woo-abandoned-cart-recovery' ); ?>
            </p>
        </div>
        <script type="text/javascript" id="wacv-dismiss-notice">
            'use strict';
            jQuery(document).ready(function ($) {
                $('body').on('click', '#wacv-message .notice-dismiss', function () {
                    $.ajax({
                        url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) )?>',
                        type: 'post',
                        data: {action: 'wacv_hide_notice'},
                        success: function (res) {
                        },
                        error: function (res) {
                        }
                    });
                });
            });
        </script>
		<?php
	}

	public function wacv_hide_notice() {
		update_option( 'wacv_hide_notice', 1 );
		wp_die();
	}
}

