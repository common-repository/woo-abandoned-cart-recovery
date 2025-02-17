<?php
/**
 * Created by PhpStorm.
 * User: Villatheme-Thanh
 * Date: 23-03-19
 * Time: 2:08 PM
 */

namespace WACV\Inc\Reports;

use WACV\Inc\Functions;
use WACV\Inc\Query_DB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Abandoned_Report_Table extends \WP_List_Table {

	protected static $instance = null;

	public $base_url;

	public $total_count;

	public $query;

	public $start;

	public $end;

	public $per_page;

	public $current_page;

	public $count_abd_items;


	public function __construct() {
		parent::__construct( array(
			'singular' => 'abandoned',     //singular name of the listed records
			'plural'   => 'abandoneds',    //plural name of the listed records
			'ajax'     => true        //does this table support ajax?
		) );
		$this->query = Query_DB::get_instance();

	}

	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * It will add the bulk action function and other variable needed for the class.
	 * @since 2.5.2
	 * @see WP_List_Table::__construct()
	 */
	public function abandoned_table() {
		if ( isset( $_REQUEST['_wacv_admin_nonce'] ) && ! wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['_wacv_admin_nonce'] ) ), 'wacv_admin_nonce' ) ) {
			return;
		}
		$this->per_page     = $this->get_items_per_page( 'wacv_acr_per_page', 30 );
		$this->current_page = $this->get_pagenum();

		$class_all_customer = $class_member = $class_guest = '';
		$section            = isset( $_GET['abd_table'] ) ? sanitize_text_field( $_GET['abd_table'] ) : '';

		switch ( $section ) {
			case 'all_customer':
			case '':
				$class_all_customer = 'current';
				break;
			case 'member':
				$class_member = 'current';
				break;
			case 'guest':
				$class_guest = 'current';
				break;
		}

		?>
        <h3><?php esc_html_e( 'Abandoned Carts', 'woo-abandoned-cart-recovery' ) ?></h3>
		<?php do_action( 'wacv_notices' ) ?>
        <div class="wacv-abanoned-cart-page">
            <ul class="subsubsub">
                <li><a href="<?php echo esc_attr( admin_url( 'admin.php?page=wacv_sections&abd_table=all_customer' ) ) ?>"
                       class="<?php echo esc_html( $class_all_customer ) ?>"><?php esc_html_e( 'All', 'woo-abandoned-cart-recovery' ) ?></a>
                    |
                </li>
                <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wacv_sections&abd_table=member' ) ) ?>"
                       class="<?php echo esc_html( $class_member ) ?>"><?php esc_html_e( 'Member', 'woo-abandoned-cart-recovery' ) ?></a>
                    |
                </li>
                <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wacv_sections&abd_table=guest' ) ) ?>"
                       class="<?php echo esc_html( $class_guest ) ?>"><?php esc_html_e( 'Guest', 'woo-abandoned-cart-recovery' ) ?></a>
                </li>
            </ul>
			<?php
			$this->prepare_items();
			$this->show_record();
			?>
        </div>
		<?php
	}

	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->process_bulk_action();
		$data = $this->get_items();

//		usort( $data, array( $this, 'usort_reorder' ) );

		$total_items = $this->count_abd_items;

		$per_page = $this->per_page;

		$this->items = $data;

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			//WE have to calculate the total number of items
			'per_page'    => $per_page,
			//WE have to determine how many items to show on a page
			'total_pages' => ceil( $total_items / $per_page )
			//WE have to calculate the total number of pages
		) );
	}

	public function get_columns() {
		$columns = array(
			'checkbox'    => "<input class='wacv-check-all' type='checkbox'>",
			'date'        => esc_html__( 'Date', 'woo-abandoned-cart-recovery' ),
			'customer'    => esc_html__( 'Customer', 'woo-abandoned-cart-recovery' ),
			'cart_detail' => esc_html__( 'Cart Detail', 'woo-abandoned-cart-recovery' ),
			'stt'         => esc_html__( 'Status', 'woo-abandoned-cart-recovery' ),
			'reminder'    => esc_html__( 'Reminder logs', 'woo-abandoned-cart-recovery' ),
			'from'        => esc_html__( 'From', 'woo-abandoned-cart-recovery' )
		);

		return apply_filters( 'wcal_abandoned_orders_columns', $columns );
	}

	public function get_sortable_columns() {
		$sortable_columns = array(
			'id' => array( 'id', true ),       //true means it's already sorted
		);

		return $sortable_columns;
	}

	public function process_bulk_action() {
	}

	public function get_items() {
		if ( isset( $_REQUEST['_wacv_admin_nonce'] ) && ! wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['_wacv_admin_nonce'] ) ), 'wacv_admin_nonce' ) ) {
			return [];
		}
		$section = 'all_customer';

//		if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'wacv-filter' ) && current_user_can( 'manage_woocommerce' ) ) {
        $section = isset( $_GET['abd_table'] ) ? sanitize_text_field( $_GET['abd_table'] ) : $section;
//		}

		$this->start = Functions::get_time()['start'];
		$this->end   = Functions::get_time()['end'];

		$export_data = $results = array();

		$this->count_abd_items = $this->query->count_abd_items( $section, $this->start, $this->end );
		$offset                = ( $this->current_page - 1 ) * $this->per_page;
		$results               = $this->query->get_abd_list( $section, $this->start, $this->end, $this->per_page, $offset );

		$i = 0;
		foreach ( $results as $key => $result ) {
			$user_email      = $result->user_email;
			$name            = $result->user_login;
			$user_ref        = $result->user_ref;
			$user_phone      = $result->billing_phone;
			$billing_country = $result->billing_country;

			if ( $result->user_type == 'member' ) {
				$user_ref        = get_user_meta( $result->user_id, 'wacv_user_ref', true );
				$user_phone      = get_user_meta( $result->user_id, 'billing_phone', true );
				$billing_country = get_user_meta( $result->user_id, 'billing_country', true );
			}

			if ( $result->user_type == 'guest' ) {
				$name       = ! empty( $result->billing_first_name ) || ! empty( $result->billing_last_name ) ? esc_html( $result->billing_first_name . ' ' . $result->billing_last_name ) : esc_html__( "Guest", 'woo-abandoned-cart-recovery' );
				$user_email = $result->billing_email;
			}

			$cart = json_decode( $result->abandoned_cart_info );

			$line_total = $line_tax = $item_qty = 0;

			if ( ! is_object( $cart->cart ) || empty( $cart->cart ) ) {
				continue;
			}
			$show_tax     = 'incl' === get_option( 'woocommerce_tax_display_cart' ) ? 1 : 0;
			$currency = isset( $cart->currency ) && ! empty( $cart->currency ) ? strval( $cart->currency ) : '';
			foreach ( $cart->cart as $item ) {
				$line_item_tax = $show_tax ? $item->line_tax : 0;
				$line_total += floatval( $item->line_total + $line_item_tax );
				$line_tax   += $item->line_tax;
				$item_qty   += $item->quantity;
			}

			$date_format = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $result->abandoned_cart_time );

			if ( $result->order_type == 1 ) {
				$stt = '<span class="wacv-recovered-stt">' . esc_html__( 'Recovered', 'woo-abandoned-cart-recovery' ) . '</span>';
			} else {
				$stt = $result->unsubscribe_link ? '<span class="wacv-unsubscribed-stt">' . esc_html__( 'Unsubscribed', 'woo-abandoned-cart-recovery' ) . '</span>' : '<span class="wacv-abandoned-stt">' . esc_html__( 'Abandoned', 'woo-abandoned-cart-recovery' ) . '</span>';
			}

			$_SERVER['HTTP_CF_IPCOUNTRY'] = $_SERVER['GEOIP_COUNTRY_CODE'] = $_SERVER['HTTP_X_COUNTRY_CODE'] = '';

			$code = ! empty( \WC_Geolocation::geolocate_ip( $result->customer_ip )['country'] ) ? \WC_Geolocation::geolocate_ip( $result->customer_ip )['country'] : 'unknown';

			$export_data[ $i ]                     = new \stdClass();
			$export_data[ $i ]->id                 = $result->id;
			$export_data[ $i ]->customer           = $name;
			$export_data[ $i ]->email              = $user_email;
			$export_data[ $i ]->phone              = $user_phone;
			$export_data[ $i ]->valid_phone        = $result->valid_phone == 1 ? false : true;
			$export_data[ $i ]->user_ref           = $user_ref ? 'vlt-ico-messenger' : '';
			$export_data[ $i ]->total              = $line_total;
			$export_data[ $i ]->tax                = $line_tax;
			$export_data[ $i ]->item_qty           = $item_qty;
			$export_data[ $i ]->date               = $date_format;
			$export_data[ $i ]->stt                = $stt;
			$export_data[ $i ]->email_reminder     = $result->number_of_mailing;
			$export_data[ $i ]->messenger_reminder = $result->messenger_sent;
			$export_data[ $i ]->sms_reminder       = $result->sms_sent;
			$export_data[ $i ]->customer_ip        = $result->customer_ip;
			$export_data[ $i ]->country_name       = isset( WC()->countries->countries[ $code ] ) ? WC()->countries->countries[ $code ] : '';
			$export_data[ $i ]->country_flag       = strtolower( $code );
			$export_data[ $i ]->os_platform        = $result->os_platform;
//			$export_data[ $i ]->browser            = $result->browser;
			$export_data[ $i ]->billing_country    = $billing_country;
			$export_data[ $i ]->currency           = $currency;
			$i ++;

		}

		return ( $export_data );
	}


	public function show_record() {
		$this->display();
	}

	public function usort_reorder( $a, $b ) {
		if ( isset( $_REQUEST['_wacv_admin_nonce'] ) && ! wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['_wacv_admin_nonce'] ) ), 'wacv_admin_nonce' ) ) {
			return 1;
		}
		$orderby = ( ! empty( $_REQUEST['orderby'] ) ) ? sanitize_text_field( $_REQUEST['orderby'] ) : 'id'; //If no sort, default to title
		$order   = ( ! empty( $_REQUEST['order'] ) ) ? sanitize_text_field( $_REQUEST['order'] ) : 'asc'; //If no order, default to asc
		$result  = strcmp( $a->$orderby, $b->$orderby ); //Determine sort order

		return ( $order === 'desc' ) ? $result : - $result; //Send final sort direction to usort
	}

	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'checkbox':
				$html = "<input type='checkbox' data-id='$item->id' data-time='$item->email_reminder' class='wacv-checkbox-bulk-action {$item->id}'>";

				return $html;

			case 'id':
				return $item->id;

			case 'customer':
				$phone = $item->valid_phone ? $item->phone : "<del>{$item->phone}</del>";

				return "<div class='wacv-customer-info'>$item->customer</div><div class='wacv-customer-info'>$item->email</div><div class='wacv-customer-info'>$phone</div>"; //<div class='$item->user_ref wacv-messenger-subscribe wacv-customer-info'></div>

			case 'cart_detail':
				$unit = $item->item_qty > 1 ? esc_html__( 'items', 'woo-abandoned-cart-recovery' ) : esc_html__( 'item', 'woo-abandoned-cart-recovery' );
				$out  = "<a href='javascript:void(0)' class='wacv-get-abd-cart-detail $item->id' data-id='$item->id'>" . wc_price( $item->total, array( 'currency' => $item->currency ) ) . " ({$item->item_qty} {$unit})<i class='icon'></i></a>";

				return $out;

			case 'date':
				return $item->date;

			case 'stt':
				return $item->stt;

			case 'reminder':
				$email_color = ! empty( $item->email ) ? 'blue' : '';
				$fb_color    = ! empty( $item->user_ref ) ? 'blue' : '';
				$sms_color   = ! empty( $item->phone ) && $item->billing_country && $item->valid_phone ? 'blue' : '';

				$reminder = "<table class='wacv-email-reminder-group' >";
				$reminder .= "<tr class='wacv-get-logs $item->id' data-id='$item->id'>";
				$reminder .= "<td style='position: absolute; left:-10px;'><i class='wacv-loading icon'></i><span class='wacv-email-reminder-popup $item->id'></span></td>";
				$reminder .= "<td ><span class='wacv-reminder-number {$item->id}'>$item->email_reminder</span><span class='wacv-reminder-icon  dashicons dashicons-email-alt {$email_color}' data-id='$item->id'></span></td>";
//				$reminder .= "<td ><span class='wacv-reminder-number'>$item->messenger_reminder</span><span class='wacv-reminder-icon vlt-ico-messenger {$fb_color}' data-id='$item->id'> </span>";
				$reminder .= "<td ><span class='wacv-reminder-number'>$item->sms_reminder</span><span class='wacv-reminder-icon dashicons dashicons-smartphone {$sms_color}' data-id='$item->id'></span></td></tr></table>";

				return $reminder;

			case 'from':
				$from = "<div class='wacv-from'>
                            <div class='wacv-country-flag-group wacv-inline-block'>
                            <i class='flag {$item->country_flag}'></i>
                                <div class='wacv-from-detail'>
                                    <p>{$item->country_name}</p>
                                    <p><a href='https://tools.keycdn.com/geo?host={$item->customer_ip}' target='_blank'>{$item->customer_ip}</a></p>
                                </div>
                            </div>
                            <div class='wacv-user-agent wacv-inline-block'>
                                <i class='dashicons dashicons-info'></i>
                                <span class='wacv-user-agent-detail'>
                                    {$item->os_platform}
                                </span>    
                            </div>
                        </div>";

				return $from;

			default:
				return;
		}

	}

	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			'wacv_abandoned_id',
			$item->id  //$abd_id
		);
	}

	public function extra_tablenav( $which ) {
		if ( $which == 'top' ) {
			$selected = get_option( 'wacv_time_range' );
			if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'wacv-filter' ) && current_user_can( 'manage_woocommerce' ) ) {
				$selected = isset( $_GET['wacv_time_range'] ) ? sanitize_text_field( $_GET['wacv_time_range'] ) : $selected;
				update_option( 'wacv_time_range', $selected );
			}

			$button = 'submit';
			?>
            <form method="get" id="wacv-abandoned-cart" action="#">
                <input type="hidden" name="page" value="wacv_sections">
				<?php wc_get_template(
					'html-date-picker.php',
					array( 'start' => $this->start, 'end' => $this->end, 'button' => $button, 'selected' => $selected ),
					'', WACV_TEMPLATES ) ?>
            </form>
            <div class="wacv-send-email-manual-group">
                <select name="wacv-template" class="wacv-template  vlt-none-shadow vlt-height-32">
					<?php
					$templates = Functions::get_email_template();
					foreach ( $templates as $temp ) {
						printf( '<option value="%s">%s</option>', esc_attr( $temp['id'] ), esc_html( $temp['value'] ) );
					}
					?>
                </select>

                <button type="button" name="action" value="wacv_send_email_manual"
                        class="wacv-send-email-manual vlt-button vlt-height-32 vlt-border ">
					<?php esc_html_e( 'Send abandoned cart email', 'woo-abandoned-cart-recovery' ) ?>
                </button>
                <button type="button" name="action" class="wacv-remove-record vlt-button vlt-height-32 vlt-border ">
					<?php esc_html_e( 'Delete', 'woo-abandoned-cart-recovery' ) ?>
                </button>
            </div>
			<?php
		}
	}
}


