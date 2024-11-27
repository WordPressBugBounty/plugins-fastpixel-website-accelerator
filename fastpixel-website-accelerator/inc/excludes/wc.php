<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Exclude_Wc')) {
    class FASTPIXEL_Exclude_Wc extends FASTPIXEL_Exclude
    {
        public function check_is_exclusion($url) {
            $home_url = home_url();
            $current_url = preg_replace('/\?.*/i', '', $url->get_url());
            //checking for woocommerce
            if (function_exists('wc_get_cart_url')) {
                //comparing with cart url
                if (wc_get_cart_url() != $home_url && wc_get_cart_url() == $current_url) {
                    return true;
                }
            }
            if (function_exists('wc_get_checkout_url')) {
                //comparing with checkout url
                if (wc_get_checkout_url() != $home_url && wc_get_checkout_url() == $current_url) {
                    return true;
                }
            }
            if (function_exists('wc_get_endpoint_url')) {
                //comparing with new order url
                $order_received_url = wc_get_endpoint_url('order-received', null, wc_get_checkout_url());
                if ($order_received_url != $home_url && strpos($current_url, $order_received_url) !== false) {
                    return true;
                }
            }
            if (function_exists('wc_get_page_id')) {
                //comparing with my-account url
                $my_account_url = get_permalink(wc_get_page_id('myaccount'));
                if ($my_account_url && $my_account_url != $home_url && $my_account_url == $current_url) {
                    return true;
                }
            }
            if (function_exists('is_account_page')) {
                //comparing with account url
                if (is_account_page()) {
                    return true;
                }
            }
            return false;
        }
    }
    new FASTPIXEL_Exclude_Wc();
}
