<?php
/**
 * Created by PhpStorm.
 * User: Villatheme-Thanh
 * Date: 13-06-19
 * Time: 9:55 AM
 */

namespace WACV\Inc\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Email_Popup_Settings extends Admin_Settings {

	protected static $instance = null;

	public function __construct() {

	}

	public static function get_instance() {

		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function setting_page() {
		?>
        <div id="" class="vi-ui bottom attached tab segment tab-admin" data-tab="fifth">
            <h4><?php esc_html_e( 'Pop-up config', 'woo-abandoned-cart-recovery' ) ?></h4>
            <table class="wacv-table">
				<?php
				$this->get_pro_version( esc_html__( 'Appear on', 'woo-abandoned-cart-recovery' ) );
				$this->get_pro_version( esc_html__( 'Email required', 'woo-abandoned-cart-recovery' ) );
				$this->get_pro_version( esc_html__( 'Dismiss time', 'woo-abandoned-cart-recovery' ) );
				$this->get_pro_version( esc_html__( 'Redirect after Add to cart', 'woo-abandoned-cart-recovery' ) );
				?>
            </table>

            <h4><?php esc_html_e( 'Design', 'woo-abandoned-cart-recovery' ) ?></h4>
            <table class="wacv-table">
				<?php
				$this->get_pro_version( esc_html__( 'Template', 'woo-abandoned-cart-recovery' ) );
				$this->get_pro_version( esc_html__( 'Title', 'woo-abandoned-cart-recovery' ) );
				$this->get_pro_version( esc_html__( 'Sub title', 'woo-abandoned-cart-recovery' ) );
				$this->get_pro_version( esc_html__( 'Add to cart', 'woo-abandoned-cart-recovery' ) );
				$this->get_pro_version( esc_html__( 'Invalid email notice', 'woo-abandoned-cart-recovery' ) );
				$this->get_pro_version( esc_html__( 'Color settings', 'woo-abandoned-cart-recovery' ) );
				?>
            </table>
        </div>
		<?php
	}

}
