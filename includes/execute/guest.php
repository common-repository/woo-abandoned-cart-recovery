<?php
/**
 * Created by PhpStorm.
 * User: Villatheme-Thanh
 * Date: 20-03-19
 * Time: 9:35 AM
 */

namespace WACV\Inc\Execute;

use WACV\Inc\Query_DB;
use WACV\Inc\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Guest {

	protected static $instance = null;

	public $query;
	public $settings;
	protected $language;

	private function __construct() {
		$this->settings = Data::get_params();
		$this->query = Query_DB::get_instance();

		if ( ! isset( $_COOKIE['wacv_skip_track_data'] ) ) {

			add_action( 'wp_ajax_nopriv_wacv_get_info', array( $this, 'get_info' ) );

			add_action( 'wp_ajax_wacv_get_info', array( $this, 'get_info' ) );

			add_action( 'wp_ajax_nopriv_wacv_update_gdpr', array( $this, 'update_gdpr' ) );
			add_action( 'wp_ajax_wacv_update_gdpr', array( $this, 'update_gdpr' ) );

			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_file' ) );

//		add_filter( 'woocommerce_checkout_fields', array( $this, 'guest_checkout_fields' ) );
			add_filter( 'woocommerce_checkout_get_value', [ $this, 'guest_checkout_fields' ], 20, 2 );

			add_action( 'init', array( $this, 'get_email' ) );

			add_action( 'woo_lucky_wheel_get_email', array( $this, 'woo_lucky_wheel_get_email' ), 10, 3 );
		}
	}


	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function enqueue_file() {
		if ( is_checkout() ) {
			wp_enqueue_script( WACV_SLUG . '-get-guest-info', WACV_JS . 'get-guest-info.js', array( 'jquery' ), WACV_VERSION, true );
			wp_localize_script( WACV_SLUG . '-get-guest-info', 'wacv_localize',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php?action=wacv_ajax' ),
					'nonce'    => wp_create_nonce( 'wacv_nonce' ),
					'enable_gdpr' => esc_attr( $this->settings['enable_checkout_gdpr'] ),
					'checkout_gdpr_message' => esc_html( $this->settings['checkout_gdpr_message'] ),
					'checkout_gdpr_cancel' => esc_html( $this->settings['checkout_gdpr_cancel'] ),
				) );
		}
	}

	public function get_email() {
		if ( isset( $_REQUEST['_wacv_admin_nonce'] ) && ! wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['_wacv_admin_nonce'] ) ), 'wacv_admin_nonce' ) ) {
			return;
		}

		if ( class_exists( 'Woocommerce_Lucky_Wheel' ) ) {
			//Compatible with Lucky wheel
			if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'wlwl_get_email' ) {
				$data                       = wc_clean( $_REQUEST );
				$data['billing_email']      = isset( $data['user_email'] ) ? $data['user_email'] : '';
				$data['billing_first_name'] = isset( $data['user_name'] ) ? $data['user_name'] : '';
				$this->subscribe_data( $data );
			}
		}
		//Compatible with coupon box
		if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'wcb_email' ) {
			$data                  = wc_clean( $_REQUEST );
			$data['billing_email'] = isset( $data['email'] ) ? $data['email'] : '';
			$this->subscribe_data( $data );
		}

		if ( is_plugin_active( 'mailchimp-for-wp/mailchimp-for-wp.php' ) && isset( $_POST['_mc4wp_form_id'] ) ) {
			$data                       = wc_clean( $_POST );
			$data['billing_email']      = isset( $data['EMAIL'] ) ? $data['EMAIL'] : '';
			$data['billing_phone']      = isset( $data['PHONE'] ) ? $data['PHONE'] : '';
			$data['billing_city']       = isset( $data['ADDRESS']['city'] ) ? $data['ADDRESS']['city'] : '';
			$data['billing_country']    = isset( $data['ADDRESS']['country'] ) ? $data['ADDRESS']['country'] : '';
			$data['billing_first_name'] = isset( $data['FNAME'] ) ? $data['FNAME'] : '';
			$data['billing_last_name']  = isset( $data['LNAME'] ) ? $data['LNAME'] : '';
			$this->subscribe_data( $data );
		}
	}

	public function woo_lucky_wheel_get_email( $email, $name, $mobile ) {
		if ( ! ( $email || $mobile ) ) {
			return;
		}
		if ( isset( $_REQUEST['_wacv_admin_nonce'] ) && ! wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['_wacv_admin_nonce'] ) ), 'wacv_admin_nonce' ) ) {
			return;
		}
		//Compatible with Lucky wheel
		if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'wlwl_get_email' ) {
			$data['billing_email']      = $email;
			$data['billing_first_name'] = $name;
			$data['billing_phone']      = $mobile;
			$this->subscribe_data( $data );
		}
	}

	public function subscribe_data( $data ) {
		$this->save_info( $data );
	}

	public function save_info( $data ) {
		$update_user = '';
		$cookie_time = current_time( 'timestamp' ) + 86400;
		setCookie( 'wacv_get_email', true, $cookie_time, '/' );

		if ( ! is_user_logged_in() ) {

			if ( ! WC()->session->has_session() ) {
				WC()->session->set_customer_session_cookie( true );
			}

			$guest_info = array();
			$patterns   = array(
				'billing_first_name',
				'billing_last_name',
				'billing_company',
				'billing_address_1',
				'billing_address_2',
				'billing_city',
				'billing_state',
				'billing_postcode',
				'billing_country',
				'billing_phone',
				'billing_email',
				'order_notes',
				'shipping_first_name',
				'shipping_last_name',
				'shipping_company',
				'shipping_address_1',
				'shipping_address_2',
				'shipping_city',
				'shipping_state',
				'shipping_postcode',
				'shipping_country',
				'ship_to_billing',
				'user_ref'
			);
			foreach ( $data as $key => $value ) {
				if ( in_array( $key, $patterns ) && $data[ $key ] != '' ) {
					$guest_info[ $key ] = sanitize_text_field( $data[ $key ] );
				}
			}

			$this->query::set_session( 'guest_info', $guest_info );
			$guest_info = $this->query::get_session( 'guest_info' );

			$email_address = $guest_info['billing_email'] ?? '';
			$user_ref      = $guest_info['user_ref'] ?? '';
			$user_phone    = $guest_info['billing_phone'] ?? '';

			$user_id = '';
			if ( is_email( $email_address ) || ! empty( $user_ref ) || $user_phone ) {
				if ( $this->query::get_session( 'user_id' ) ) {
					$user_id     = $this->query::get_session( 'user_id' );
					$update_user = $this->query->update_guest_info( $user_id, $guest_info );
				} else {
					$user_id = ! $user_id && $email_address ? $this->query->get_user_by_email( $email_address ) : '';
					$user_id = ! $user_id && $user_phone ? $this->query->get_user_by_phone( $user_phone ) : $user_id;
					$user_id = ! $user_id && $user_ref ? $this->query->get_user_by_user_ref( $user_ref ) : $user_id;

					if ( $user_id ) {
						$update_user = $this->query->update_guest_info( $user_id, $guest_info );
					} else {
						$user_id = $this->query->insert_guest_info( $guest_info );
					}
				}
				if ( is_email( $email_address ) ) {
					wc()->customer->set_billing_email( $email_address );
				}
				wc()->session->set( 'user_id', $user_id );
				$abdc_id = wc()->session->get( 'wacv_cart_record_id' );
				$this->query->update_abd_cart_record(
					array( 'abandoned_cart_time' => WACV_CURRENT_TIME, 'user_id' => $user_id ),
					array( 'id' => $abdc_id ) );

				if ( $email_address ) {
					$guests = $this->query->get_guest_info_rows( $email_address );
					if ( $guests ) {
						foreach ( $guests as $guest ) {
							if ( count( $this->query->get_guest_same_email( $guest->id, 0 ) ) ) {
								if ( $guest->id == $user_id ) {
									continue;
								}
								$this->query->update_abd_cart_record(
									array( 'cart_ignored' => 1 ),
									array( 'user_id' => $guest->id, 'user_type' => 'guest' )
								);
							}
						}
					}
				}

				$get_cookie = WC()->session->get_session_cookie();

				if ( isset( $get_cookie[0] ) && '' != $get_cookie[0] ) {
					$this->query->update_cart_log( array( 'user_id' => $user_id ), array( 'user_id' => $get_cookie[0] ) );
				}

			}

		} else {
			if ( isset( $data['user_ref'] ) ) {
				$user_ref    = sanitize_text_field( $data['user_ref'] );
				$user_id     = get_current_user_id();
				$update_user = update_user_meta( $user_id, 'wacv_user_ref', $user_ref );
				$guest_info  = array( 'user_ref' => $user_ref );
				$this->query::set_session( 'guest_info', $guest_info );
			}
		}


		return $update_user;
	}

	public function get_info() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wacv_nonce' ) ) {
			wp_die();
		}

		$data = wc_clean( $_POST );

		if ( empty( $data['billing_country'] ) ) {
			$ip                      = \WC_Geolocation::get_ip_address();
			$user_geo                = \WC_Geolocation::geolocate_ip( $ip );
			$data['billing_country'] = $user_geo['country'];
		}
		$update_user = $this->save_info( $data );

		wp_send_json_success( $update_user );
		wp_die();
	}

	public function guest_checkout_fields( $value, $input ) {
		$guest_info = $this->query::get_session( 'guest_info' );
		if ( ! empty( $guest_info ) ) {
			if ( ! empty( $guest_info[ $input ] ) ) {
				if ( $input == 'billing_email' ) {
					$value = sanitize_email( $guest_info[ $input ] );
				} else {
					$value = sanitize_text_field( $guest_info[ $input ] );
				}
			}
		}

		return $value;
	}

	public function update_gdpr() {
		check_ajax_referer( 'wacv_nonce', 'nonce' );

		$abdc_id = wc()->session->get( 'wacv_cart_record_id' );

		$result = $this->query->get_abdc_record( $abdc_id ); //select record with cart_ignore = 0
		if ( $result ) {
			$this->query->remove_abd_record( $abdc_id );
		}

		setcookie( 'wacv_skip_track_data', 'true', 0, '/' );

		wp_send_json_success();
	}
}

