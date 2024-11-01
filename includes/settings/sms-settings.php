<?php
/**
 * Created by PhpStorm.
 * User: Villatheme-Thanh
 * Date: 08-06-19
 * Time: 2:35 PM
 */

namespace WACV\Inc\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SMS_Settings extends Admin_Settings {

	protected static $instance = null;

	public static function get_instance() {

		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function setting_page() {
		?>
        <div class="vi-ui bottom attached tab segment tab-admin" data-tab="fourth">
            <h4><?php esc_html_e( 'Routee config', 'woo-abandoned-cart-recovery' ) ?></h4>
            <table class="wacv-table">
				<?php
				$this->get_pro_version( esc_html__( 'App ID', 'woo-abandoned-cart-recovery' ) );
				$this->get_pro_version( esc_html__( 'App secret', 'woo-abandoned-cart-recovery' ) );
				?>
            </table>

            <h4><?php esc_html_e( 'Bitly config', 'woo-abandoned-cart-recovery' ) ?></h4>
            <table class="wacv-table">
				<?php
				$this->get_pro_version( esc_html__( 'Access token', 'woo-abandoned-cart-recovery' ) );
				?>
            </table>
            <hr>

            <h4><?php esc_html_e( 'SMS for Abandoned Cart', 'woo-abandoned-cart-recovery' ) ?></h4>
            <table class="wacv-table">
				<?php
				$this->get_pro_version( esc_html__( 'Enable', 'woo-abandoned-cart-recovery' ) );
				?>
            </table>
            <hr>

            <h4><?php esc_html_e( 'SMS for Abandoned Order', 'woo-abandoned-cart-recovery' ) ?></h4>
            <table class="wacv-table">
				<?php
				$this->get_pro_version( esc_html__( 'Enable', 'woo-abandoned-cart-recovery' ) );
				$this->get_pro_version( esc_html__( 'Order status', 'woo-abandoned-cart-recovery' ) );
				?>
            </table>
        </div>
		<?php
	}

	//Messenger rule


}
