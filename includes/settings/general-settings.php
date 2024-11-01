<?php
/**
 * Created by PhpStorm.
 * User: Villatheme-Thanh
 * Date: 08-06-19
 * Time: 12:01 PM
 */

namespace WACV\Inc\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class General_Settings extends Admin_Settings {

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
        <div id="" class="vi-ui bottom attached active tab segment tab-admin" data-tab="first">
            <h4><?php esc_html_e( 'Cart', 'woo-abandoned-cart-recovery' ) ?></h4>
            <table class="wacv-table">
				<?php
				$this->checkbox_option( 'tracking_member', esc_html__( "Tracking Member's Cart", 'woo-abandoned-cart-recovery' ), esc_html__( 'If enable, the plugin will track abandoned cart of logged users', 'woo-abandoned-cart-recovery' ) );
				$this->number_option( 'member_cut_off_time', esc_html__( 'Abandoned Cart time for Members', 'woo-abandoned-cart-recovery' ), esc_html__( 'Select the time to mark a cart as abandoned with logged in users', 'woo-abandoned-cart-recovery' ), 'minutes' );
				$this->checkbox_option( 'tracking_guest', esc_html__( "Tracking Guest's Cart", 'woo-abandoned-cart-recovery' ), esc_html__( 'If enable, the plugin will track abandoned cart of guests', 'woo-abandoned-cart-recovery' ) );
				$this->number_option( 'guest_cut_off_time', esc_html__( 'Abandoned Cart time for Guest', 'woo-abandoned-cart-recovery' ), esc_html__( 'Select the time to mark a cart as abandoned with guests', 'woo-abandoned-cart-recovery' ), 'minutes' );
				$this->number_option( 'delete_record_time', esc_html__( 'Delete abandoned records', 'woo-abandoned-cart-recovery' ), esc_html__( 'Abandoned cart records will be automatically deleted after this time', 'woo-abandoned-cart-recovery' ), 'days' );

				$tracking_user_exclude_opts = array();
				if ( is_array( self::get_field( 'tracking_user_exclude' ) ) && count( self::get_field( 'tracking_user_exclude' ) ) > 0 ) {
					foreach ( get_users( array( 'include' => self::get_field( 'tracking_user_exclude' ) ) ) as $user ) {
						$tracking_user_exclude_opts[ $user->ID ] = $user->user_nicename;
					}
				}
				$this->select_option( 'tracking_user_exclude', $tracking_user_exclude_opts, esc_html__( "Exclude people", 'woo-abandoned-cart-recovery' ), '', true );
				$this->select_option(
					'direct_recover_link',
					[ esc_html__( 'Cart page', 'woo-abandoned-cart-recovery' ), esc_html__( 'Checkout page', 'woo-abandoned-cart-recovery' ) ],
					esc_html__( "Recover link to", 'woo-abandoned-cart-recovery' ) );
				$this->get_pro_version( esc_html__( 'Cart log', 'woo-abandoned-cart-recovery' ) );
				$this->checkbox_option( 'enable_checkout_gdpr', esc_html__( "Checkout GDPR", 'woo-abandoned-cart-recovery' ), esc_html__( 'If enabled, a GDPR notice will display after the email input field on the checkout page.', 'woo-abandoned-cart-recovery' ) );
				$this->text_option( 'checkout_gdpr_message', esc_html__( 'GDPR message', 'woo-abandoned-cart-recovery' ), '', '' );
				$this->text_option( 'checkout_gdpr_cancel', esc_html__( 'Ignore Email Button Text', 'woo-abandoned-cart-recovery' ), '', '' );
				?>
            </table>

            <table class="wacv-table">
                <tr class="vlt-row vlt-margin-top">
                    <td class="vlt-third vlt-margin-bottom-8 col-1">
                        <label style="font-weight: bold" class=""><?php esc_html_e( 'Cron job' ) ?></label>
                    </td>
                    <td class="vlt-twothird vlt-row col-2">
						<?php
						$cron_command = site_url() . '/wp-cron.php';
						if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
							?>
                            <p>
								<?php esc_html_e( "Your WP Cron is disabled. If you want to use WP Cron, open file wp-config.php and delete row define( \"DISABLE_WP_CRON\", true );.", 'woo-abandoned-cart-recovery' ); ?>
                            </p>
                            <p>
								<?php esc_html_e( " If you want to use CronJob server, access your server and config with command:", 'woo-abandoned-cart-recovery' ); ?>
                            </p>
                            <p>
                                <input type="text" value=" * * * * * curl <?php echo esc_html( $cron_command ) ?>" readonly>
                            </p>
							<?php
						} else {
							?>
                            <p>
								<?php esc_html_e( "Your WP Cron is enabled. If you want to use Cronjob server, open file wp-config.php and add row define( \"DISABLE_WP_CRON\", true );. Access your server and config with command:", 'woo-abandoned-cart-recovery' ); ?>
                            </p>
                            <div class="wacv-input-readonly-block">
                                <input type="text" class="wacv-readonly" value=" * * * * * curl <?php echo esc_html( $cron_command ) ?>" readonly>
                                <span class="wacv-copy-icon"><i class="copy outline icon"> </i></span>
                            </div>
						<?php }
						?>
                    </td>
                    <td class="col-3"></td>
                </tr>
            </table>
        </div>
		<?php
	}

}
