jQuery(document).ready(function ($) {
    'use strict';
    if ('1' === wacv_localize.enable_gdpr && !$('#wacv_checkout_gdpr_block').length) {
        $('input#billing_email').after(
            "<span id='wacv_checkout_gdpr_block'> <span style='font-size: x-small'> " +
            wacv_localize.checkout_gdpr_message +
            " <a style='cursor: pointer' id='wacv_checkout_gdpr_cancel'> " +
            wacv_localize.checkout_gdpr_cancel +
            ' </a></span></span>'
        );
    }
    $('#wacv_checkout_gdpr_cancel').on('click', function () {
        set_cookie();
    });

    function set_cookie() {
        let data = 'nonce=' + wacv_localize.nonce + '&action=wacv_update_gdpr';

        jQuery.post( wacv_localize.ajax_url, data, function ( response ) {
            if ( response.success ) {
                $('#wacv_checkout_gdpr_block').empty();
            }
        } );
    }

    $('input#billing_email').on('change', function () {
        var pattern = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;

        if (pattern.test($(this).val())) {
            wacv_send_get_guest_info();
        }
    });

    $('input#billing_phone').on('change', function () {
        wacv_send_get_guest_info();
    });

    function wacv_send_get_guest_info() {
        var data = $('form.woocommerce-checkout').serialize() + '&action=wacv_get_info&nonce=' + wacv_localize.nonce;
        $.ajax({
            url: wacv_localize.ajax_url,
            data: data,
            type: 'POST',
            xhrFields: {
                withCredentials: true
            },
            success: function (res) {
                // console.log(res);
            },
            error: function (res) {
                // console.log(res);
            }
        });
    }
});