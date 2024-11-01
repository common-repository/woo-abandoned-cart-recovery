<?php
/**
 * Created by PhpStorm.
 * User: Villatheme-Thanh
 * Date: 09-04-19
 * Time: 2:00 PM
 */

namespace WACV\Inc;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ajax {

	protected static $instance = null;

	/**
	 * Setup instance attributes
	 *
	 * @since     1.0.0
	 */
	private function __construct() {
		add_action( 'wp_ajax_wacv_search', array( $this, 'wacv_search' ) );
		add_action( 'wp_ajax_wacv_get_email_history', array( $this, 'wacv_get_email_history' ) );
		add_action( 'wp_ajax_wacv_get_abd_cart_detail', array( $this, 'wacv_get_abd_cart_detail' ) );
		add_action( 'wp_ajax_wacv_remove_record', array( $this, 'wacv_remove_record' ) );

		//Export customer emails
		add_action( 'wp_ajax_wacv_export_customer_emails', array( $this, 'wacv_export_customer_emails' ) );
		add_action( 'admin_init', array( $this, 'update_database' ) );
	}

	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function wacv_search() {
		if ( isset( $_GET['action'] ) && $_GET['action'] == 'wacv_search'
		     && isset( $_GET['nonce'] ) && wp_verify_nonce( $_GET['nonce'], 'wacv_nonce' )
		     && current_user_can( 'manage_woocommerce' ) ) {

			if ( isset( $_GET['param'] ) ) {
				$keyword = isset( $_GET['keyword'] ) ? sanitize_text_field( $_GET['keyword'] ) : '';
				$result  = array();

				switch ( sanitize_text_field( $_GET['param'] ) ) {
					case 'user':
						$args = array( 'orderby' => 'nicenamne', 'order' => 'DESC', 'search' => "*$keyword*", 'search_columns' => array(
							'user_login',
							'user_nicename',
							'user_email',
							'user_url',
							'display_name'
						), );

						$users = get_users( $args );
						foreach ( $users as $user ) {
							$result[] = array( 'id' => $user->ID, 'text' => $user->user_nicename );
						}
						break;
					case 'coupon':
						$args  = array( 'post_type' => 'shop_coupon', 'post_status' => 'publish', 's' => $keyword );
						$items = new \WP_Query( $args );
						if ( $items->have_posts() ) {
							foreach ( $items->posts as $item ) {
								$result[] = array( 'id' => $item->ID, 'text' => $item->post_title );
							}
						}
						break;
					case 'product':
						$args  = array( 'post_type' => 'product', 'post_status' => 'publish', 's' => $keyword );
						$items = new \WP_Query( $args );
						if ( $items->have_posts() ) {
							foreach ( $items->posts as $item ) {
								$result[] = array( 'id' => $item->ID, 'text' => $item->post_title );
							}
						}
						break;
				}
				wp_send_json( $result );
			}
		}
		wp_die();
	}

	public function wacv_get_email_history() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wacv_nonce' ) || ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die();
		}
		if ( isset( $_POST['id'] ) ) {
			$id       = sanitize_text_field( $_POST['id'] );
			$query    = Query_DB::get_instance();
			$results  = $query->get_email_history( $id );
			$date_fm  = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
			$response = array();

			foreach ( $results as $result ) {
				$response[] = array(
					'type'      => $result->type,
					'sent_time' => $result->sent_time > 0 ? date_i18n( $date_fm, $result->sent_time ) : '',
					'opened'    => $result->opened > 0 ? date_i18n( $date_fm, $result->opened ) : '',
					'clicked'   => $result->clicked > 0 ? date_i18n( $date_fm, $result->clicked ) : '',
				);
			}
			wp_send_json( $response );
		}
		wp_die();
	}

	public function wacv_get_abd_cart_detail() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wacv_nonce' ) || ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die();
		}
		if ( isset( $_POST['id'] ) ) {
			$id     = sanitize_text_field( $_POST['id'] );
			$query  = Query_DB::get_instance();
			$result = $query->get_abd_cart_detail( $id );
			if ( $result ) {
				$cart     = json_decode( $result->abandoned_cart_info );
				$currency_symbol = array( 'currency' => $cart->currency ? $cart->currency : '' );
				$response = array();
				$show_tax = 'incl' === get_option( 'woocommerce_tax_display_cart' ) ? 1 : 0;
				foreach ( $cart->cart as $item ) {
					$pid = $item->variation_id ? $item->variation_id : $item->product_id;
					$pd  = wc_get_product( $pid );
					if ( $pd ) {
						$p_name     = $pd->get_name();
						$line_item_tax = $show_tax ? $item->line_tax : 0;
						$line_total = floatval( $item->line_total + $line_item_tax );

						$image_url = wp_get_attachment_image_src( get_post_thumbnail_id( $pid ), 'thumbnail' );
						$image_url = $image_url ? $image_url : wp_get_attachment_image_src( get_post_thumbnail_id( $item->product_id ), 'thumbnail' );
						$image_url = $image_url ? $image_url : array( wc_placeholder_img_src( 'thumbnail' ) );

						$response[] = array(
							'name'     => $p_name,
							'amount'   => wc_price( $line_total, $currency_symbol ),
							'quantity' => $item->quantity,
							'img'      => $image_url[0]
						);
					} else {
						$response[] = array(
							'name'     => esc_html__( 'This product is not exist', 'woo-abandoned-cart-recovery' ),
							'amount'   => '',
							'quantity' => '',
							'img'      => ''
						);
					}
				}
				wp_send_json( $response );
			}
		}
		wp_die();
	}

	public function update_database() {
		$this->update_column( 'wacv_abandoned_cart_record', 'email_complete', 'number_of_mailing', "enum('0','1')" );
		$this->update_column( 'wacv_abandoned_cart_record', 'sms_complete', 'sms_sent', "enum('0','1')" );
		$this->update_column( 'wacv_abandoned_cart_record', 'messenger_complete', 'messenger_sent', "enum('0','1')" );
		$this->modify_column( 'wacv_guest_info_record', 'billing_email', 'text null' );
		$this->update_column( 'wacv_abandoned_cart_record', 'current_lang', 'browser', "text" );
	}


	public function update_column( $table, $col, $after, $format ) {
		global $wpdb;
		$update = 3;
		if ( ! get_option( 'wacv_update_db_' . $update . $col ) ) {
			$sql = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '{$wpdb->prefix}{$table}' AND COLUMN_NAME = '{$col}'";

			$check_exist = $wpdb->query( $sql );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery

			if ( ! $check_exist ) {
				$sql_add_col = " ALTER TABLE {$wpdb->prefix}{$table} ADD $col {$format}  AFTER {$after}";
				$result      = $wpdb->query( $sql_add_col );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
				if ( $result ) {
					update_option( 'wacv_update_db_' . $update . $col, 1 );
				}
			} else {
				update_option( 'wacv_update_db_' . $update . $col, 1 );
			}
		}
	}

	public function modify_column( $table, $col, $format ) {
		global $wpdb;
		$update = 3;
		if ( ! get_option( 'wacv_modify_column_' . $update . $col ) ) {
			$sql = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '{$wpdb->prefix}{$table}' AND COLUMN_NAME = '{$col}'";

			$check_exist = $wpdb->query( $sql );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
			if ( $check_exist ) {
				$mod_sql = "ALTER TABLE {$wpdb->prefix}{$table} MODIFY {$col} {$format}";
				$result  = $wpdb->query( $mod_sql );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
				if ( $result ) {
					update_option( 'wacv_modify_column_' . $update . $col, 1 );
				}
			}
		}
	}

	public function wacv_remove_record() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wacv_nonce' ) || ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( esc_html__( 'You have no permission to do this action', 'woo-abandoned-cart-recovery' ) );
		}

		$id = ! empty( $_POST['id'] ) ? sanitize_text_field( $_POST['id'] ) : '';

		if ( ! $id ) {
			wp_send_json_error( esc_html__( 'No id to remove record', 'woo-abandoned-cart-recovery' ) );
		}

		$query  = Query_DB::get_instance();
		$result = $query->remove_abd_record( $id ,true);
		if ( $result ) {
			wp_send_json_success( $result );
		}

		wp_die();
	}

	public function wacv_export_customer_emails() {
		if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wacv_export_emails' ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$type = ! empty( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : 'partial';

		$fields = [];
		switch ( $_POST['field'] ) {
			case 'both':
				$fields = [ 'billing_email', 'billing_phone' ];
				break;
			case 'email':
				$fields = [ 'billing_email' ];
				break;
			case 'phone':
				$fields = [ 'billing_phone' ];
				break;
		}

		if ( $type == 'partial' ) {
			$last_id = get_option( 'wacv_last_export_customer_emails' );
			$last_id = $last_id ? $last_id : 100000000;
			$query   = Query_DB::get_instance();
			$args    = [
				'fields'       => $fields,
				'where_clause' => "id > {$last_id}",
				'limit'        => 100,
			];
			$query->get_customer_emails( $args );

		}

		wp_die();
	}
}