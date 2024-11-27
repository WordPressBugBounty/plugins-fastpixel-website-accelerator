<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Excluded_Woo_Cart')) {
    class FASTPIXEL_Excluded_Woo_Cart
    {
        protected $debug = false;
        public static $instance;
        protected $functions;
        protected $config;

        public function __construct()
        {
            self::$instance = $this;
            //initializing functions and config
            $this->functions = FASTPIXEL_Functions::get_instance();
            $this->config = FASTPIXEL_Config_Model::get_instance();
            add_filter('fastpixel/init/excluded', [$this, 'is_excluded'], 11, 2);
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Excluded_Woo_Cart();
            }
            return self::$instance;
        }

        public function is_excluded($excluded, $url) {
            if ($excluded == true) {
                return $excluded;
            }
            /**
             * https://stackoverflow.com/questions/38546354/woocommerce-cookies-and-sessions-get-the-current-products-in-cart
             * also confirmed by aguidrevitch at https://outletdepanales.com/ on 23th Jan 2024
             */
            if (!empty($_COOKIE['woocommerce_items_in_cart']) && intval($_COOKIE['woocommerce_items_in_cart']) > 0) {
                return true;
            }
            return false;
        }
    }
    new FASTPIXEL_Excluded_Woo_Cart();
}
