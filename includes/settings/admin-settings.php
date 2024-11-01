<?php
/**
 * Created by PhpStorm.
 * User: Villatheme-Thanh
 * Date: 29-03-19
 * Time: 8:44 AM
 */

namespace WACV\Inc\Settings;

use WACV\Inc\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Settings {

	public static $params;

	protected static $instance = null;

	public function __construct() {
		add_action( 'admin_init', array( $this, 'save_params' ), 1 );
		add_action( 'admin_menu', array( $this, 'admin_menu_page' ), 40 );
	}

	public static function get_instance() {

		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function admin_menu_page() {
		add_submenu_page(
			'wacv_sections',
			esc_html__( 'Settings', 'woo-abandoned-cart-recovery' ),
			esc_html__( 'Settings', 'woo-abandoned-cart-recovery' ),
			'manage_woocommerce',
			'wacv_settings',
			array( $this, 'display_settings' )
		);
	}

	public function display_settings() {
		do_action( 'wacv_before_settings' );
		?>
        <div id="wacv-admin-settings">
            <div class="wacv-header">
                <h1 class="vi-ui header"><?php esc_html_e( 'Settings', 'woo-abandoned-cart-recovery' ) ?></h1>
            </div>

            <div id="wacv-settings-container">
                <form class="vi-ui form" method="post">
					<?php echo ent2ncr( self::set_nonce() );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <div class="vi-ui top attached tabular menu">
                        <a class="active item" data-tab="first">
							<?php esc_html_e( 'General', 'woo-abandoned-cart-recovery' ) ?>
                        </a>
                        <a class="item" data-tab="second">
							<?php esc_html_e( 'Email', 'woo-abandoned-cart-recovery' ) ?>
                        </a>
                        <a class="item" data-tab="fourth">
							<?php esc_html_e( 'SMS', 'woo-abandoned-cart-recovery' ) ?>
                        </a>
                        <a class="item" data-tab="fifth">
							<?php esc_html_e( 'Email popup', 'woo-abandoned-cart-recovery' ) ?>
                        </a>
                    </div>
					<?php
					General_Settings::get_instance()->setting_page();
					Email_Settings::get_instance()->setting_page();
					SMS_Settings::get_instance()->setting_page();
					Email_Popup_Settings::get_instance()->setting_page();
					?>
                    <div class="">
                        <button type="submit" class="vi-ui button labeled icon primary wacv-btn wacv-save-settings"
                                name="action" value="save_params">
                            <i class="send icon"></i>
							<?php esc_html_e( 'Save settings', 'woo-abandoned-cart-recovery' ) ?>
                        </button>

                    </div>
                </form>
            </div>
        </div>
		<?php
		do_action( 'villatheme_support_woo-abandoned-cart-recovery', get_current_screen()->id );
	}

	protected static function set_nonce() {
		return wp_nonce_field( 'woo_abandoned_settings', '_woo_abandoned_cart_nonce' );
	}

	public function get_option_for_select( $filed ) {
		$options = array();
		$include = self::get_field( $filed );

		if ( is_array( $include ) && count( $include ) > 0 ) {
			$products = wc_get_products( array( 'include' => $include ) );
			foreach ( $products as $product ) {
				$options[ $product->get_id() ] = $product->get_name();
			}
		}

		return $options;
	}

	public static function get_field( $field ) {

		if ( ! self::$params ) {
			self::$params = Data::get_params();
		}

		return isset( self::$params[ $field ] ) ? self::$params[ $field ] : '';
	}

	public function get_categories() {
		$option = array();
		$args   = array(
			'taxonomy'   => "product_cat",
			'hide_empty' => 0,
			'orderby'    => 'name',
		);

		$categories = get_terms( $args );
		if ( count( $categories ) > 0 ) {
			foreach ( $categories as $category ) {
				$option[ $category->term_id ] = $category->name;
			}
		}

		return $option;
	}

	public function text_option( $field_name, $label = '', $units = '', $multi = false ) {
		$set_name = $this->set_field( $field_name, $multi );
//		$set_unit = $this->set_field( $field_name . '_unit', $multi );
		$class = 'wacv-' . str_replace( '_', '-', $field_name );
		$col   = ! empty( $units ) ? 9 : 12;
		?>
        <tr>
            <td class="col-1">
                <label class=""><?php esc_html_e( $label );// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText ?></label>
            </td>
            <td class="col-2">
                <div>
                    <input type="text" name="<?php echo esc_attr( $set_name ) ?>"
                           value="<?php echo esc_html( stripslashes( self::get_field( $field_name ) ) ) ?>"
                           class="<?php echo esc_attr( $class ) ?> vlt-input vlt-border vlt-none-shadow vlt-round ">
                </div>
            </td>
            <td class="col-3"></td>
        </tr>
		<?php
	}

	public function set_field( $name, $multi = false ) {
		return $multi ? "wacv_params[$name][]" : "wacv_params[$name]";
	}


	public function number_option( $field_name, $label = '', $explain = '', $units = '', $multi = false, $min = 1, $max = 1000, $required = true ) {
		$set_name = $this->set_field( $field_name, $multi );
		$set_unit = $this->set_field( $field_name . '_unit', $multi );
		$class    = 'wacv-' . str_replace( '_', '-', $field_name );
		$col      = ! empty( $units ) ? 11 : 12;
		?>
        <tr>
            <td class="col-1">
                <label><?php esc_html_e( $label );// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText ?></label>
				<?php
				if ( $explain ) {
					printf( '<span class="wacv-explain-group" data-tooltip="%s" data-variation="wide"><i class="question circle icon "></i></span>', esc_attr( $explain ) );
				}
				?>
            </td>
            <td class="col-2">
                <div class="vlt-col s<?php echo esc_attr( $col ) ?>">
                    <input type="number" <?php echo esc_attr( $required ? 'required' : '' ) ?>
                           min="<?php echo esc_attr( $min ) ?>" max="<?php echo esc_attr( $max ) ?>"
                           name="<?php echo esc_attr( $set_name ) ?>"
                           value="<?php echo esc_attr( self::get_field( $field_name ) ) ?>"
                           class="<?php echo esc_attr( $class ) ?>">
                </div>

            </td>
            <td>
                <div>
					<?php
					if ( ! empty( $units ) && is_array( $units ) ) {
						?>
                        <select name='<?php echo esc_attr( $set_unit ) ?>' class='wacv-unit'>
							<?php
							foreach ( $units as $unit ) {
								$selected = self::get_field( $field_name . '_unit' ) == $unit ? 'selected' : '';
								printf( '<option value="%s" %s>%s</option>', esc_attr( $unit ), esc_attr( $selected ), esc_html( $unit ) );
							}
							?>
                        </select>
						<?php
					} elseif ( ! empty( $units ) ) {
						?>
                        <div class='wacv-unit'><?php echo esc_html( $units ) ?></div>
						<?php
					}
					?>
                </div>
            </td>
        </tr>
		<?php
	}

	public function select_option( $field_name, $option = array(), $label = '', $units = '', $multi = false ) {
		$set_name = $this->set_field( $field_name, $multi );
		$class    = 'wacv-' . str_replace( '_', '-', $field_name );
		$col      = ! empty( $units ) ? 9 : 12;

		?>
        <tr>
            <td class="col-1">
                <label><?php esc_html_e( $label );// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText ?></label>
            </td>
            <td class="col-2">
                <div>
                    <select <?php echo esc_attr( $multi ? 'multiple' : '' ) ?>
                            class="<?php echo esc_attr( $class ) ?>"
                            name="<?php echo esc_attr( $set_name ) ?>">
						<?php
						if ( count( $option ) > 0 && is_array( $option ) ) {
							foreach ( $option as $value => $view ) {
								if ( is_array( self::get_field( $field_name ) ) ) {
									$selected = in_array( $value, self::get_field( $field_name ) ) ? 'selected' : '';
								} else {
									$selected = self::get_field( $field_name ) == $value ? 'selected' : '';
								}
								printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), esc_attr( $selected ), esc_html( $view ) );
							}
						} ?>
                    </select>
                </div>
            </td>
            <td class="col-3"></td>
        </tr>
		<?php
	}

	public function checkbox_option( $field_name, $label = '', $explain = '', $subffix = '' ) {
		$set_name = $this->set_field( $field_name );
		$class    = 'wacv-' . str_replace( '_', '-', $field_name );
		?>
        <tr class="">
            <td class="col-1">
                <label><?php esc_html_e( $label );// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText ?> </label>
				<?php if ( $explain ) {
					printf( '<span class="wacv-explain-group" data-tooltip="%s" data-variation="wide"><i class="question circle icon "></i></span>', esc_attr( $explain ) );
				}
				?>
            </td>
            <td class="col-2">
                <div class="vi-ui toggle checkbox">
                    <input type="checkbox" <?php checked( self::get_field( $field_name ), 1 ) ?>
                           value="1"
                           name="<?php echo esc_attr( $set_name ) ?>"
                           class="<?php echo esc_attr( $class ) ?>">
                    <label><?php esc_html_e( $subffix );// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText ?></label>
                </div>
            </td>
            <td class="col-3"></td>
        </tr>
		<?php
	}

	public function get_pro_version( $title ) {
		?>
        <tr>
            <td class="col-1">
                <label class=""><?php esc_html_e( $title );// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText ?></label>
            </td>
            <td class="col-2">
                <a href="<?php echo esc_url( WACV_PRO_URL ) ?>" class="vi-ui button tiny" target="_blank">
					<?php esc_html_e( 'Unlock this feature', 'woo-abandoned-cart-recovery' ) ?>
                </a>
            </td>
            <td class="col-3"></td>
        </tr>
		<?php
	}

	public function save_params() {
		if ( isset( $_POST['wacv_params'] ) ) {
			if ( ! isset( $_POST['_woo_abandoned_cart_nonce'] ) || ! wp_verify_nonce( $_POST['_woo_abandoned_cart_nonce'], 'woo_abandoned_settings' ) ) {
				return;
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_safe_redirect( $_POST['_wp_http_referer'] );
				exit;
			}

			/*wpml*/
			$multi_lang = array();
			if ( is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) ) {
				global $sitepress;
				$default_lang           = $sitepress->get_default_language();
				$wpml_languages              = icl_get_languages( 'skip_missing=N&orderby=KEY&order=DIR&link_empty_to=str' );

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

			$data     = wc_clean( wp_unslash( $_POST['wacv_params'] ) );
			$new_data = array();

			if ( isset( $data['email_rules'] ) ) {
				$email_rule = $data['email_rules'];

				$count = count( $email_rule['time_to_send'] );
				for ( $i = 0; $i < $count; $i ++ ) {
					$email_rule['sort'] [ $i ] = intval( $email_rule['time_to_send'] [ $i ] ) * intval( Data::get_instance()->case_unit( $email_rule['unit'] [ $i ] ) );
				}

				asort( $email_rule['sort'] );
				$j             = 1;
				$email_fn_rule = array();

				foreach ( $email_rule['sort'] as $k => $v ) {
					$email_fn_rule['send_time'][]    = $j;
					$email_fn_rule['time_to_send'][] = $email_rule['time_to_send'] [ $k ];
					$email_fn_rule['unit'][]         = $email_rule['unit'] [ $k ];
					$email_fn_rule['template'][]     = $email_rule['template'] [ $k ];
					$j ++;
				}

				$new_data['email_rules'] = $email_fn_rule;

				if ( $multi_lang ) {
					$email_abd_exits = false;
					if ( ! empty( $data['email_rules'] )) $email_abd_exits = true;
					foreach ( $multi_lang as $key => $value ) {
						if ( $email_abd_exits && isset( $data['email_rules']['template_' . $value] ) ) {
							$new_data['email_rules']['template_' . $value] = $data['email_rules']['template_' . $value];
						}
					}
				}
			}


			if ( isset( $data['messenger_rules'] ) ) {
				$messenger_rules = $data['messenger_rules'];

				$count = count( $messenger_rules['time_to_send'] );
				for ( $i = 0; $i < $count; $i ++ ) {
					$messenger_rules['sort'] [ $i ] = intval( $messenger_rules['time_to_send'] [ $i ] ) * Data::get_instance()->case_unit( $messenger_rules['unit'] [ $i ] );
				}

				asort( $messenger_rules['sort'] );
				$j                 = 1;
				$messenger_fn_rule = array();

				foreach ( $messenger_rules['sort'] as $k => $v ) {
					$messenger_fn_rule['send_time'][]    = $j;
					$messenger_fn_rule['time_to_send'][] = $messenger_rules['time_to_send'] [ $k ];
					$messenger_fn_rule['unit'][]         = $messenger_rules['unit'] [ $k ];
					$messenger_fn_rule['message'][]      = $messenger_rules['message'] [ $k ];
					$j ++;
				}

				$new_data['messenger_rules'] = $messenger_fn_rule;
			}

			$data = ( wp_parse_args( $new_data, $data ) );

			update_option( 'wacv_params', $data );
			Data::$params = '';
		}
	}
}
