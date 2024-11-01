<?php
/**
 * Created by PhpStorm.
 * User: Villatheme-Thanh
 * Date: 20-06-19
 * Time: 9:47 AM
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php echo wp_nonce_field( 'wacv-filter', '_wpnonce', false );// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<div class="wacv-select-time-group">
    <span class="wacv-select-time-1">
        <select name="wacv_time_range" class="wacv-select-time-report vlt-none-shadow vlt-height-32">
			<?php
			$options = array(
				"today"     => esc_html__( 'Today', 'woo-abandoned-cart-recovery' ),
				"yesterday" => esc_html__( 'Yesterday', 'woo-abandoned-cart-recovery' ),
				"30days"    => esc_html__( '30 days', 'woo-abandoned-cart-recovery' ),
				"90days"    => esc_html__( '90 days', 'woo-abandoned-cart-recovery' ),
				"365days"   => esc_html__( '365 days', 'woo-abandoned-cart-recovery' ),
				"custom"    => esc_html__( 'Custom', 'woo-abandoned-cart-recovery' ),
			);
			foreach ( $options as $key => $value ) {
				$select = $selected == $key ? 'selected' : '';
				printf( '<option value="%s" %s>%s</option>', esc_attr( $key ), $selected == $key ? 'selected' : '', esc_html( $value ) );
			}
			?>
        </select>
    </span>
    <span class="wacv-custom-time-range">
                <input type="date" class="wacv-date-from  vlt-none-shadow vlt-height-32"
                       name="wacv_start" value="<?php echo esc_attr( date_i18n( wc_date_format(), intval( $start ) ) ) ?>">
                <input type="date" class="wacv-date-to  vlt-none-shadow vlt-height-32"
                       name="wacv_end" value="<?php echo esc_attr( date_i18n( wc_date_format(), intval( $end ) ) ) ?>">
                <button type="<?php echo esc_attr( $button ) ?>" value="filter" name="action"
                        class="wacv-view-reports vlt-button vlt-height-32 vlt-border">
					<?php esc_html_e( 'View', 'woo-abandoned-cart-recovery' ) ?></button>
            </span>
</div>
