<?php
/**
 * Created by PhpStorm.
 * User: Villatheme-Thanh
 * Date: 25-03-19
 * Time: 5:00 PM
 */

namespace WACV\Inc\Email;

use WACV\Inc\Aes_Ctr;
use WACV\Inc\Data;
use WACV\Inc\Query_DB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Send_Email_Cron {

	protected static $instance = null;

	public $query;

	public $data;
	public $wc_email;

	public $new_email_settings;

	public $old_email_settings;
	public $last_time = false;
	public $render_data = [];
	public $email_type = 'abandoned_cart';

	protected $characters_array;
	protected $country;
	protected $record_data;
	protected $cart_items;

	private function __construct() {
		$this->query = Query_DB::get_instance();
		add_action( 'wacv_cron_send_email_abd_cart', array( $this, 'send_reminder_mail' ) );
		add_action( 'wacv_cron_send_email_abd_order', array( $this, 'send_reminder_order' ) );

		add_action( 'wp_ajax_wacv_send_abd_order', array( $this, 'wacv_send_abd_order' ) );
		add_action( 'wp_ajax_send_email_abd_manual', array( $this, 'send_email_abd_manual' ) );
		add_filter( 'woocommerce_email_styles', array( $this, 'custom_css' ) );
		add_action( 'admin_init', [ $this, 'debug' ] );
	}

	public function debug() {
		if ( isset( $_REQUEST['_wacv_admin_nonce'] ) && ! wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['_wacv_admin_nonce'] ) ), 'wacv_admin_nonce' ) ) {
			return;
		}
		if ( isset( $_GET['debug'] ) && $_GET['debug'] === 'abandoned_cart' ) {
			$this->send_reminder_mail();
		}
		if ( isset( $_GET['debug'] ) && $_GET['debug'] === 'abandoned_order' ) {
			$this->send_reminder_order();
		}
	}

	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function send_reminder_mail() {

		if ( ! class_exists( 'WC_Email' ) ) {
			include_once WC_ABSPATH . 'includes/emails/class-wc-email.php';
		}

		$this->wc_email = new \WC_Email();

		$this->data = Data::get_params();

		if ( ! empty( $this->data['email_rules'] ) ) {
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

			$email_rules = $this->data['email_rules'];
			$count       = count( $email_rules['send_time'] );
			for ( $i = 0; $i < $count; $i ++ ) {
				$this->last_time = ( $count - 1 ) == $i ? true : false;
				$time_to_send    = current_time( 'timestamp' ) - intval( $email_rules['time_to_send'][ $i ] ) * Data::get_instance()->case_unit( $email_rules['unit'][ $i ] );

				$lists = $this->query->get_list_email_to_send( $time_to_send, $email_rules['send_time'][ $i ] );

				if ( is_array( $lists ) && count( $lists ) > 0 ) {
					if ( isset( $email_rules['template'][ $i ] ) ) {
						foreach ( $lists as $id => $item ) {
							$email_temp = $email_rules['template'] [ $i ];
							if ( $multi_lang && isset( $item->current_lang ) && ! empty( $item->current_lang ) ) {
								if ( isset( $email_rules[ 'template_' . $item->current_lang ] )
								     && isset( $email_rules[ 'template_' . $item->current_lang ][ $i ] )
								     && ! empty( $email_rules[ 'template_' . $item->current_lang ][ $i ] ) ) {
									$email_temp = $email_rules[ 'template_' . $item->current_lang ][ $i ];
								}
							}
							$this->email_content( $item, $email_temp );
						}
					}
				}
			}
		}
	}

	public function email_content( $item, $temp_id ) {
		$result = '';
		if ( ! empty( $item->user_id ) ) {

			$email            = $item->user_email;
			$customer_name    = $item->user_login;
			$customer_surname = '';
			$country          = $item->user_id < 100000000 ? get_user_meta( $item->user_id, 'billing_country', true ) : '';

			if ( $item->user_type == 'guest' ) {
				$results_guest = $this->query->get_guest_info( $item->user_id );
				if ( count( $results_guest ) > 0 ) {
					$email            = $results_guest[0]->billing_email;
					$customer_name    = ! empty( trim( $results_guest[0]->billing_first_name ) ) ? trim( $results_guest[0]->billing_first_name ) : '';
					$customer_surname = ! empty( trim( $results_guest[0]->billing_last_name ) ) ? trim( $results_guest[0]->billing_last_name ) : '';
					$country          = ! empty( $results_guest[0]->billing_country ) ? $results_guest[0]->billing_country : '';
				}
			} else {
				$customer_surname = get_user_meta( $item->user_id, 'last_name', true ) ? : '';
			}

			if ( is_email( $email ) ) {

				$cart        = json_decode( $item->abandoned_cart_info );
				$cart_detail = $pd_arr = array();
				$cart_total  = 0;

				if ( $country && $this->data['price_incl_tax'] ) {
					$this->country = $country;
					add_filter( 'woocommerce_matched_rates', array( $this, 'add_tax_rate' ) );
				}

				foreach ( $cart->cart as $cart_item_key => $cart_item ) {
					$pid     = $cart_item->variation_id ? $cart_item->variation_id : $cart_item->product_id;
					$product = wc_get_product( $pid );
					if ( $product ) {
						$pd_name     = $product->get_name();
						$image       = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' );
						$image_url   = $image ? $image : wc_placeholder_img_src( 'woocommerce_thumbnail' );
						$description = explode( '.', $product->get_short_description() );
						$description = isset( $description[0] ) ? $description[0] : '';
						$qty         = $cart_item->quantity;

						$price = wc_get_price_to_display( $product );

						$amount     = $price * $qty;
						$cart_total += $amount;
						$pd_arr[]   = $cart_item->product_id;
						$pd_url     = $product->get_permalink();

						$cart_detail[] = array(
							'image'        => $image_url,
							'name'         => $pd_name,
							'desc'         => $description,
							'price'        => wc_price( $amount ),
							'url'          => $pd_url,
							'quantity'     => $qty,
							'product_id'   => $cart_item->product_id,
							'variation_id' => $cart_item->variation_id ?? '',
						);
					}
				}

				if ( ! empty( $cart_detail ) ) {
					$this->cart_items = $cart_detail;

					$temp_obj = get_post( $temp_id );

					if ( ! $temp_obj ) {
						return false;
					}

					$acr_id        = $item->id;
					$sent_email_id = uniqid() . $acr_id;

					$this->record_data = array(
						'acr_id'           => $acr_id,
						'cart_total'       => $cart_total,
						'temp_id'          => $temp_id,
						'product_arr'      => $pd_arr,
						'mail_count'       => $item->number_of_mailing,
						'email'            => $email,
						'customer_name'    => $customer_name,
						'customer_surname' => $customer_surname,
						'sent_email_id'    => $sent_email_id,
					);

					$coupon_code              = $coupon_expire = '';
					$coupon_default_params    = Data::get_instance()->get_coupon_params();
					$this->new_email_settings = get_post_meta( $temp_id, 'wacv_email_settings_new', true );
					$this->old_email_settings = get_post_meta( $temp_id, 'wacv_email_settings', true );
					$email_settings           = $this->new_email_settings ? $this->new_email_settings : $this->old_email_settings;
					$email_settings           = wp_parse_args( $email_settings, $coupon_default_params );

					if ( $email_settings['use_coupon'] ) {
						$coupon_code = $this->get_coupon_to_send( $email_settings );
					}

					if ( $coupon_code ) {
						$coupon = new \WC_Coupon( $coupon_code );
						if ( $coupon ) {
							if ( $coupon->get_date_expires() ) {
								$coupon_expire = $coupon->get_date_expires()->date_i18n( wc_date_format() . ' ' . wc_time_format() );
							}
						}
					}

					$this->record_data['coupon_code']        = $coupon_code;
					$this->record_data['coupon_expire']      = $coupon_expire;
					$this->record_data['link']               = $this->create_link( $coupon_code, $acr_id, $sent_email_id, $temp_id );
					$this->record_data['tracking_open_link'] = $this->create_tracking_open_link( $acr_id, $sent_email_id );
					$this->record_data['unsubscribe_link']   = $this->create_unsubscribe_link( $acr_id );

					$message = '';
					if ( $temp_obj->post_type === 'wacv_email_template' ) {
						$email_subject                = isset( $email_settings['subject'] ) ? $email_settings['subject'] : $this->data['email_subject'];
						$this->record_data['subject'] = $this->replace_shortcodes( $email_subject );

						$message = $this->create_email_content( $cart_detail, $temp_id );
					}

					if ( $message ) {
						$result = $this->send_mail( $message );
					}
				}
			}
		}

		return $result;
	}

	public function replace_shortcodes( $message ) {
		$data = $this->record_data;
		$arr  = array(
			'{wacv_coupon}'           => $data['coupon_code'] ?? '',
			'{wacv_coupon_expire}'    => $data['coupon_expire'] ?? '',
			'{wacv_checkout_btn}'     => $data['link'],
			'{site_title}'            => get_bloginfo(),
			'{customer_name}'         => $data['customer_name'],
			'{wacv_customer_name}'    => $data['customer_name'],
			'{customer_surname}'      => $data['customer_surname'],
			'{wacv_customer_surname}' => $data['customer_surname'],
			'{site_address}'          => WC()->countries->get_base_address(),
			'{store_address}'         => WC()->countries->get_base_address(),
			'{admin_email}'           => get_bloginfo( 'admin_email' ),
			'{site_url}'              => site_url(),
			'{home_url}'              => home_url(),
			'{shop_url}'              => get_permalink( wc_get_page_id( 'shop' ) ),
			'{wacv_coupon_start}'     => '',
			'{wacv_coupon_end}'       => '',
			'{unsubscribe_link}'      => $data['unsubscribe_link'],
			'{coupon_code}'           => $data['coupon_code'] ?? '',
			'{coupon_expire}'         => $data['coupon_expire'] ?? '',
		);

		return str_replace( array_keys( $arr ), array_values( $arr ), $message );
	}

	public function create_email_content( $cart_detail, $temp_id ) {
		$message      = '';
		$template_obj = get_post( $temp_id );

		if ( $template_obj ) {
			$template = wp_specialchars_decode( get_post( $temp_id )->post_content );
		} else {
			$temp_id = '';
			ob_start();
			wc_get_template( 'email-default.php', array(), '', WACV_TEMPLATES );
			$template                     = ob_get_clean();
			$this->record_data['subject'] = $this->data['email_subject'];
		}

		if ( $template ) {
			$out     = '';
			$pattern = '/{wacv_cart_detail_start}([\s\S]+){wacv_cart_detail_end}/';

			if ( preg_match( ( $pattern ), $template, $match ) ) {

				foreach ( $cart_detail as $item ) {
					$item_link        = $this->data['email_item_link'] ? "href='{$item['url']}'" : '';
					$item['price']    = esc_html__( 'Price:', 'woo-abandoned-cart-recovery' ) . ' ' . $item['price'];
					$item['url']      = "<a " . $item_link . " style='font-weight: inherit; color:inherit;'>{$item['name']}</a>";
					$item['quantity'] = esc_html__( 'Quantity:', 'woo-abandoned-cart-recovery' ) . ' ' . $item['quantity'];

					//modify presentation of order items
					$item = apply_filters( 'wacv_cart_item', $item );

					$search = array(
						'{wacv_image_product}',
						'{wacv_name_&_qty_product}',
						'{wacv_short_description}',
						'{product_amount}',
						'{product_name}',
						'{product_quantity}'
					);

					$out .= str_replace( $search, $item, $match[1] );
				}

				$template = str_replace( $match[0], $out, $template );
			}

			$message = $this->replace_shortcodes( $template );
			$message = $this->complete_message( $message );
		}

		return $message;
	}

	public function get_coupon_to_send( $email_settings ) {
		$coupon_code = '';

		if ( ! empty( $email_settings['use_coupon_generate'] ) ) {
			$coupon_code = $this->generate_coupon( $email_settings );
		} elseif ( ! empty( $email_settings['wc_coupon'] ) ) {
			$coupon_code = wc_get_coupon_code_by_id( $email_settings['wc_coupon'] );
		}

		return $coupon_code;
	}

	public function generate_coupon( $option ) {
		return wc_get_coupon_code_by_id( $this->create_coupon( $option ) );
	}

	public function create_coupon( $option ) {
		$code   = $this->create_code( $option );
		$coupon = new \WC_Coupon( $code );
		$desc   = isset( $option['gnr_coupon_desc'] ) ? $option['gnr_coupon_desc'] : '';
		$coupon->set_description( $desc );
		$coupon->set_discount_type( $option['gnr_coupon_type'] );
		$coupon->set_amount( $option['gnr_coupon_amount'] );
		if ( $option['gnr_coupon_date_expiry'] ) {
			$coupon->set_date_expires( $option['gnr_coupon_date_expiry'] * 24 * 60 * 60 + current_time( 'timestamp' ) );
		}
		$coupon->set_minimum_amount( $option['gnr_coupon_min_spend'] );
		$coupon->set_maximum_amount( $option['gnr_coupon_max_spend'] );
		$coupon->set_individual_use( isset( $option['gnr_coupon_individual'] ) ? $option['gnr_coupon_individual'] : 0 );
		$coupon->set_free_shipping( isset( $option['gnr_coupon_free_shipping'] ) ? $option['gnr_coupon_free_shipping'] : 0 );
		$coupon->set_exclude_sale_items( isset( $option['gnr_coupon_exclude_sale_items'] ) ? $option['gnr_coupon_exclude_sale_items'] : 0 );
		$coupon->set_product_ids( $option['gnr_coupon_products'] );
		$coupon->set_excluded_product_ids( $option['gnr_coupon_exclude_products'] );
		$coupon->set_product_categories( $option['gnr_coupon_categories'] );
		$coupon->set_excluded_product_categories( $option['gnr_coupon_exclude_categories'] );
		$coupon->set_usage_limit( $option['gnr_coupon_limit_per_cp'] );
		$coupon->set_limit_usage_to_x_items( $option['gnr_coupon_limit_x_item'] );
		$coupon->set_usage_limit_per_user( $option['gnr_coupon_limit_user'] );

		return $coupon->save();
	}

	public function create_code( $option ) {
		$code = $option['gnr_coupon_prefix'];

		for ( $i = 0; $i < 8; $i ++ ) {
			$code .= $this->rand();
		}

		$args      = array(
			'post_type'      => 'shop_coupon',
			'post_status'    => 'publish',
			'posts_per_page' => - 1,
			'title'          => $code
		);
		$the_query = new \WP_Query( $args );
		if ( $the_query->have_posts() ) {
			$code = $this->create_code( $option );
		}
		wp_reset_postdata();

		return $code;
	}

	protected function rand() {
		if ( $this->characters_array === null ) {
			$this->characters_array = array_merge( range( 0, 9 ), range( 'a', 'z' ) );
		}
		$rand = rand( 0, count( $this->characters_array ) - 1 );// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_rand

		return $this->characters_array[ $rand ];
	}

	public function create_link( $coupon_code, $acr_id, $sent_email_id, $temp_id ) {
		$coupon      = $coupon_code ? '&' . $coupon_code : '';
		$template_id = $temp_id ? '&' . $temp_id : '&0';
		$pass        = get_option( 'wacv_private_key' );
		$url_encode  = Aes_Ctr::encrypt( $acr_id . '&' . $sent_email_id . $template_id . $coupon, $pass, 256 );

		return site_url( '?wacv_recover=cart_link&valid=' ) . $url_encode;
	}

	public function create_tracking_open_link( $acr_id, $sent_email_id ) {
		$pass       = get_option( 'wacv_private_key' );
		$url_encode = Aes_Ctr::encrypt( $acr_id . '&' . $sent_email_id, $pass, 256 );

		return "<img width='0' height='0' style='width:0; height:0;' src='" . site_url( '?wacv_open_email=' ) . $url_encode . "' >";
	}

	public function create_unsubscribe_link( $acr_id ) {
		$pass       = get_option( 'wacv_private_key' );
		$url_encode = Aes_Ctr::encrypt( $acr_id, $pass, 256 );

		return site_url( '?wacv_unsubscribe=' ) . $url_encode;
	}

	public function complete_message( $template ) {
		$mailer = WC()->mailer();

		if ( $this->new_email_settings && ! isset( $this->new_email_settings['woo_header'] ) ) {
			$message = $this->wc_email->style_inline( $this->wrap_message( $template ) );
		} elseif ( $this->new_email_settings && isset( $this->new_email_settings['woo_header'] ) ) {
			$heading     = ! empty( $this->new_email_settings['heading'] ) ? $this->new_email_settings['heading'] : '';
			$message     = $this->wc_email->style_inline( $mailer->wrap_message( $heading, $template ) );
			$padding     = array( 'padding: 12px;', 'padding: 48px 48px 32px' );
			$new_padding = array( 'padding:0;', 'padding:0' );
			$message     = str_replace( $padding, $new_padding, $message );
		} else {
			$heading = ! empty( $this->old_email_settings['heading'] ) ? $this->old_email_settings['heading'] : '';
			$message = $this->wc_email->style_inline( $mailer->wrap_message( $heading, $template ) );
		}

		return $message;
	}

	public function wrap_message( $message ) {
		ob_start();

		wc_get_template( 'email-header.php', '', '', WACV_TEMPLATES );

		echo wp_kses_post( $message );

		wc_get_template( 'email-footer.php', '', '', WACV_TEMPLATES );

		$message = ob_get_clean();

		return $message;
	}

	public function send_mail( $message ) {
		if ( ! $message ) {
			return false;
		}

		$acr_id = $this->record_data['acr_id'];
		$email  = $this->record_data['email'];

		$param = Data::get_params();

		$headers [] = "Content-Type: text/html";

		if ( $param['email_reply_address'] ) {
			$headers [] = "Reply-To: " . $param['email_reply_address'];
		}

		$message .= $this->record_data['tracking_open_link'];

		$result_sent_mail = $this->wc_email->send( $email, $this->record_data['subject'], $message, $headers, '' );
		$complete         = $this->last_time ? '1' : null;

		$this->query->update_abd_cart_record(
			array(
				'send_mail_time'    => current_time( 'timestamp' ),
				'number_of_mailing' => $this->record_data['mail_count'] + 1,
				'email_complete'    => $complete
			),
			array( 'id' => $acr_id ) );

		$this->query->insert_email_history( 'email', $acr_id, $this->record_data['sent_email_id'], $this->record_data['temp_id'], $email, $this->record_data['coupon_code'] );

		return $result_sent_mail;
	}

	public function add_tax_rate( $rate ) {
		if ( ! wc_prices_include_tax() && $this->country ) {
			$rate = \WC_Tax::find_rates(
				array(
					'country'   => $this->country,
					'state'     => '',
					'postcode'  => '',
					'city'      => '',
					'tax_class' => '',
				)
			);
		}

		return $rate;
	}

	/******** Reminder for order ********/
	public function send_reminder_order() {
		$this->data = Data::get_params();

		if ( ! $this->data['enable_reminder_order'] ) {
			return;
		}

		if ( ! empty( $this->data['abd_orders'] ) ) {
			$email_rules = $this->data['abd_orders'];

			for ( $i = 0; $i < count( $email_rules['send_time'] ); $i ++ ) {

				$time_to_send = current_time( 'timestamp' ) - intval( $email_rules['time_to_send'][ $i ] ) * Data::get_instance()->case_unit( $email_rules['unit'][ $i ] );

				$args = array(
					'post_type'   => 'shop_order',
					'post_status' => $this->data['order_stt'],
					'date_query'  => array(
						array(
							'before' => array(
								'year'   => date_i18n( 'Y', $time_to_send ),
								'month'  => date_i18n( 'm', $time_to_send ),
								'day'    => date_i18n( 'd', $time_to_send ),
								'hour'   => date_i18n( 'H', $time_to_send ),
								'minute' => date_i18n( 'i', $time_to_send ),
								'second' => date_i18n( 's', $time_to_send ),
							),
							'column' => 'post_modified',
						),
					),
					'meta_query'  => array(// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => '_wacv_send_reminder_email',
							'value' => $email_rules['send_time'] [ $i ] - 1,
						),
						array(
							'key'   => '_wacv_reminder_unsubscribe',
							'value' => '',
						),
					)
				);

				$the_query = new \WP_Query( $args );

				if ( $the_query->have_posts() ) :
					foreach ( $the_query->get_posts() as $order ) {
						$this->create_email_reminder_order( $order->ID, $email_rules['template'] [ $i ], $email_rules['send_time'] [ $i ] );
					}
				endif;
			}
		}
	}

	public function create_email_reminder_order( $order_id, $template_id, $time ) {
		if ( ! class_exists( 'WC_Email' ) ) {
			include_once dirname( WC_PLUGIN_FILE ) . '/includes/emails/class-wc-email.php';
		}
		$this->wc_email = new \WC_Email();

		$this->email_type = 'abandoned_order';
		$send_mail        = $subject = '';
		$temp_obj         = get_post( $template_id );
		$order            = wc_get_order( $order_id );
		$email            = $order->get_billing_email();

		if ( $temp_obj && is_email( $email ) ) {
			$this->new_email_settings = get_post_meta( $template_id, 'wacv_email_settings_new', true );
			$this->old_email_settings = get_post_meta( $template_id, 'wacv_email_settings', true );

			$sent_email_id      = '&' . uniqid() . $order_id;
			$pass               = get_option( 'wacv_private_key' );
			$recover_url_encode = Aes_Ctr::encrypt( $order_id . $sent_email_id, $pass, 256 );
			$unsub_url_encode   = Aes_Ctr::encrypt( $order_id, $pass, 256 );

			$this->record_data = array(
				'link'             => site_url( '?wacv_recover=order_link&valid=' ) . $recover_url_encode,
				'unsubscribe_link' => site_url( '?unsubscribe=' ) . $unsub_url_encode,
				'customer_name'    => $order->get_billing_first_name(),
				'customer_surname' => $order->get_billing_last_name(),
			);

			$order_detail = $order->get_items();
			if ( ! empty( $order_detail ) ) {
				$cart_detail = [];
				foreach ( $order_detail as $item ) {
					$item      = $item->get_data();
					$pid       = $item['variation_id'] ? $item['variation_id'] : $item['product_id'];
					$product   = wc_get_product( $pid );
					$image     = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' );
					$image_url = $image ? $image : wc_placeholder_img_src( 'woocommerce_thumbnail' );

					if ( ! $product ) {
						continue;
					}

					$desc = explode( '.', $product->get_short_description() );
					$desc = isset( $desc[0] ) ? $desc[0] : '';

					$cart_detail[] = array(
						'image'        => $image_url,
						'name'         => $item['name'],
						'desc'         => $desc,
						'price'        => wc_price( $item['subtotal'] ),
						'url'          => get_permalink( $pid ),
						'quantity'     => $item['quantity'],
						'product_id'   => $item['product_id'],
						'variation_id' => $item['variation_id'],
					);
				}
				$this->cart_items = $cart_detail;
			}

			$message = '';

			if ( $temp_obj->post_type === 'wacv_email_template' ) {
				$email_settings = $this->new_email_settings ? $this->new_email_settings : $this->old_email_settings;
				$subject        = isset( $email_settings['subject'] ) ? $email_settings['subject'] : '';
				$message        = $this->render_email_content_for_order_reminder( $temp_obj );
				$message        = $this->complete_message( $message );
			}

			if ( $temp_obj->post_type === 'viwec_template' ) {
				if ( class_exists( 'VIWEC_Render_Email_Template' ) ) {
					$args             = [ 'template_id' => $template_id ];
					$email_customizer = new \VIWEC_Render_Email_Template( $args );

					ob_start();
					$email_customizer->get_content();
					$message = ob_get_clean();

					$subject = $email_customizer->get_subject();
				}
			}

			if ( $message ) {
				$subject            = $this->replace_shortcodes( $subject );
				$tracking_open_link = $this->create_tracking_open_link( $order_id, $sent_email_id );
				$message            .= $tracking_open_link;
				$message            = $this->replace_shortcodes( $message );
				$send_mail          = $this->send_email_reminder_order( $email, $subject, $message );
				if ( $send_mail ) {
					$order->update_meta_data( '_wacv_send_reminder_email', $time );
					$order->save_meta_data();
					$this->query->insert_email_history( 'order', $order_id, $sent_email_id, $template_id );
				}
			}
		}

		return $send_mail;
	}

	public function render_email_content_for_order_reminder( $temp_obj ) {
		$template = wp_specialchars_decode( $temp_obj->post_content );

		$out = '';

		$pattern = '/{wacv_cart_detail_start}([\s\S]+){wacv_cart_detail_end}/';        //replace order items with shortcode

		if ( preg_match( $pattern, $template, $match ) ) {
			if ( ! empty( $this->cart_items ) ) {

				foreach ( $this->cart_items as $item ) {
					$item_link        = $this->data['email_item_link'] ? "href='{$item['url']}'" : '';
					$item['name']     = $item['name'] . ' x ' . $item['quantity'];
					$item['url']      = "<a " . $item_link . " style='font-weight: inherit'>${item['name']}</a>";
					$item['price']    = esc_html__( 'Price:', 'woo-abandoned-cart-recovery' ) . $item['price'];
					$item['quantity'] = esc_html__( 'Quantity:', 'woo-abandoned-cart-recovery' ) . $item['quantity'];

					//modify presentation of order items
					$item = apply_filters( 'wacv_cart_item', $item );

					$search = array(
						'{wacv_image_product}',
						'{wacv_name_&_qty_product}',
						'{wacv_short_description}',
						'{product_amount}',
						'{product_name}',
						'{product_quantity}'
					);
					$out    .= str_replace( $search, $item, $match[1] );
				}
				$template = str_replace( $match[0], $out, $template );

			}
		}

		return $template;
	}

	public function send_email_reminder_order( $email, $subject, $message ) {

		$param = Data::get_params();

		$headers [] = "Content-Type: text/html";

		if ( $param['email_reply_address'] ) {
			$headers [] = "Reply-To: " . $param['email_reply_address'];
		}

		$mailer = new \WC_Email();
		$result = $mailer->send( $email, $subject, $message, $headers, '' );

		return $result;
	}

	public function wacv_send_abd_order() {
		check_ajax_referer( 'wacv_order_nonce', 'nonce' );

		if ( isset( $_POST['id'], $_POST['temp'] ) ) {
			$result    = true;
			$settings  = Data::get_params();
			$order_id  = sanitize_text_field( $_POST['id'] );
			$order     = wc_get_order( $order_id );
			$order_stt = $order->get_status();
			if ( in_array( 'wc-' . $order_stt, $settings['order_stt'] ) ) {
				$template = sanitize_text_field( $_POST['temp'] );
				$time     = $order->get_meta( '_wacv_send_reminder_email', true ) + 1;
				$result   = $this->create_email_reminder_order( $order_id, $template, $time );
			}
			wp_send_json( $result );
		}
		wp_die();
	}

	public function send_email_abd_manual() {
		if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wacv_nonce' ) || ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die();
		}

		$result = '';
		if ( isset( $_POST['id'], $_POST['temp'], $_POST['time'] ) ) {

			if ( ! class_exists( 'WC_Email' ) ) {
				include_once dirname( WC_PLUGIN_FILE ) . '/includes/emails/class-wc-email.php';
			}

			$this->data     = Data::get_params();
			$this->wc_email = new \WC_Email();
			$id             = sanitize_text_field( $_POST['id'] );
			$temp           = sanitize_text_field( $_POST['temp'] );
			$item           = $this->query->get_abd_cart_by_id( $id );
			$result         = $this->email_content( $item, $temp );
		}
		wp_send_json( $result );
		wp_die();
	}

	public function custom_css( $css ) {
		$custom_css = '';
		$custom_css .= 'p{margin:0 !important;line-height:1.8;}';
		$custom_css .= 'a{text-decoration: none; color: inherit !important; font-weight:inherit;} a:hover{color:#007CFF}';
		$custom_css .= ' .ExternalClass p, .ExternalClass span, .ExternalClass font, .ExternalClass td {line-height: 100%;} .ExternalClass {width: 100%;}';
		$css        = $css . $custom_css;

		return $css;
	}
}