jQuery(document).ready(function ($) {
    'use strict';
    var couponSetting = {
        init: function () {

            $('.wacv-use-coupon-generate').on('change', function () {
                $('.wacv-generate-coupon').toggle(400);
                $('.wacv-select-wc-coupon').toggle(400);
            });

            // selectCoupon();
            $('.wacv-gnr-coupon-products').select2({
                placeholder: "Search for a product...",
                allowClear: true,
                width: '100%',
                ajax: {
                    url: wacv_ls.ajax_url + '?action=wacv_search&param=product&nonce='+wacv_ls.nonce,
                    dataType: 'json',
                    type: "GET",
                    quietMillis: 50,
                    delay: 250,
                    data: function (params) {
                        return {
                            keyword: params.term,
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data
                        };
                    },
                    cache: true,
                },
                escapeMarkup: function (markup) {
                    return markup;
                }, // let our custom formatter work
                minimumInputLength: 2,
            });

            $('.wacv-gnr-coupon-exclude-products').select2({
                placeholder: "Search for a product...",
                allowClear: true,
                width: '100%',
                ajax: {
                    url: wacv_ls.ajax_url + '?action=wacv_search&param=product&nonce='+wacv_ls.nonce,
                    dataType: 'json',
                    type: "GET",
                    quietMillis: 50,
                    delay: 250,
                    data: function (params) {
                        return {
                            keyword: params.term,
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data
                        };
                    },
                    cache: true,
                },
                escapeMarkup: function (markup) {
                    return markup;
                }, // let our custom formatter work
                minimumInputLength: 2,
            });

            $('.wacv-gnr-coupon-categories').select2({
                width: '100%',
                placeholder: "Any category",
            });
            $('.wacv-gnr-coupon-exclude-categories').select2({
                width: '100%',
                placeholder: "No categories",
            });

            $('.wacv-wc-coupon').select2({
                placeholder: "Select a coupon",
                allowClear: true,
                width: '100%',
                ajax: {
                    url: wacv_ls.ajax_url + '?action=wacv_search&param=coupon&nonce='+wacv_ls.nonce,
                    dataType: 'json',
                    type: "GET",
                    quietMillis: 50,
                    delay: 250,
                    data: function (params) {
                        return {
                            keyword: params.term,
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data
                        };
                    },
                    // cache: true,
                },
                escapeMarkup: function (markup) {
                    return markup;
                }, // let our custom formatter work
                minimumInputLength: 2,
            });
        }
    }
    couponSetting.init();
});