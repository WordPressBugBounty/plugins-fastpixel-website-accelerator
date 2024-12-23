<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Excluded_IP_Addresses')) {
    class FASTPIXEL_Excluded_IP_Addresses
    {
        protected $debug = false;
        public static $instance;
        protected $functions;
        protected $config;
        protected $current_request_ip;
        protected $excluded_ip_addresses = [ //only IPv4 should be used for now
            '3.248.28.214', //cookieyes, from https://www.cookieyes.com/documentation/whitelisting-cookieyes-scanner/
            '54.77.122.154', //cookieyes, from https://www.cookieyes.com/documentation/whitelisting-cookieyes-scanner/
        ];

        public function __construct()
        {
            self::$instance = $this;
            //initializing functions and config
            $this->functions = FASTPIXEL_Functions::get_instance();
            $this->config = FASTPIXEL_Config_Model::get_instance();
            add_filter('fastpixel/init/excluded', [$this, 'is_excluded'], 9, 2); //check on page request
            add_filter('fastpixel/is_cache_request_allowed/excluded', [$this, 'is_excluded'], 10, 2); //additional check on cache request
            $this->get_current_request_ip();
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Excluded_File_Extensions();
            }
            return self::$instance;
        }

        protected function get_current_request_ip() {
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                $this->current_request_ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $this->current_request_ip = trim(reset($ip_list));
            } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
                $this->current_request_ip = $_SERVER['REMOTE_ADDR'];
            }
        }

        public function is_excluded($excluded, $url)
        {
            if ($excluded == true) {
                return $excluded;
            }
            if (in_array($this->current_request_ip, $this->excluded_ip_addresses)) {
                $excluded = true;
            }
            return $excluded;
        }
    }
    new FASTPIXEL_Excluded_IP_Addresses();
}
