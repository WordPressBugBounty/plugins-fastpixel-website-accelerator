<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Api_Key')) {
    class FASTPIXEL_Api_Key {

        public static $instance;
        //setting trial key
        protected $api_key;
        protected $redirect = false;
        protected $redirect_to = '';
        protected $functions;

        public function __construct() {
            self::$instance = $this;
            $this->functions = FASTPIXEL_Functions::get_instance();
            //this is for wp_verify_nonce and other wp functions
            add_action('admin_init', [$this, 'init']);
        }

        public function init() {
            //getting api_key
            if (!empty($this->functions->get_option('fastpixel_api_key'))) {
                $this->api_key = $this->functions->get_option('fastpixel_api_key');
            }
        }

        public function get_key() {
            return $this->api_key;
        }

        //added ability to set key for actions(initial screen)
        public function set_key($key)
        {
            if (!empty($key)) {
                $this->api_key = $key;
            }
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Api_Key();
            }
            return self::$instance;
        }

        public function save_key() {
            $this->functions->update_option('fastpixel_api_key', $this->api_key);
        }

        protected function add_redirect($to = false) {
            $this->redirect = true;
            if ($to) {
                $this->redirect_to = $to;
            }
        }

        public function init_new_key() {
            if (is_multisite()) {
                $home = network_site_url();
            } else {
                $home = site_url();
            }
            if (function_exists('wp_rand')) {
                $rand = wp_rand(0, 9999999999);
            } else if (function_exists('random_int')) {
                $rand = random_int(0, 9999999999);
            }
            $this->api_key = md5($home.$rand);
            $this->save_key();
        }
    }
    new FASTPIXEL_Api_Key();
}
