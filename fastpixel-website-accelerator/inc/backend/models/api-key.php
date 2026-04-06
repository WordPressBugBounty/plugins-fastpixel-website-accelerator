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
            // to remember for any other use cases: had problems with apikey init that either gave eror either ran too early and ddid not load options
            //so... run slightly later than constructor, but still early
            //here seems to be the sweet-spot :D
            add_action('init', [$this, 'init'], 1);
            //this is for wp_verify_nonce and other wp functions
            add_action('admin_init', [$this, 'init']);
        }

        public function init() {
            $this->maybe_set_hoster_api_key();
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

        /**
         * Generate a temporary API key with temp_ prefix
         * 
         * @return string Temporary API key
         */
        public function generate_temp_key() {
//            // Generate a random string (32 characters)
//            if (function_exists('wp_generate_password')) {
//                $random = wp_generate_password(32, false);
//            } else if (function_exists('random_bytes')) {
//                $random = bin2hex(random_bytes(16));
//            } else {
//                $random = md5(uniqid(rand(), true));
//            }
//            return 'temp_' . $random;
            return 'temp_';
        }

        /**
         * Check if API key is temporary (starts with temp_)
         * 
         * @param string|null $key API key to check (if null, uses current key)
         * @return bool True if temporary
         */
        public function is_temp_key($key = null) {
            $check_key = $key !== null ? $key : $this->api_key;
            if (empty($check_key)) {
                return false;
            }
            return strpos($check_key, 'temp_') === 0;
        }

        /**
         * Get temporary API key expiration time (configurable, default 2 weeks)
         * 
         * @return int Expiration time in seconds
         */
        public function get_temp_key_expiration() {
            // Default: 2 weeks (14 days)
            $default_expiration = 14 * 24 * 3600;
            // filter for customization
            return apply_filters('fastpixel_temp_key_expiration', $default_expiration);
        }

        /**
         * Check if temporary API key is expired
         * 
         * @param int $timestamp Timestamp when temp key was created
         * @return bool True if expired
         */
        public function is_temp_key_expired($timestamp) {
            if (empty($timestamp) || $timestamp <= 0) {
                return false;
            }
            $expiration = $this->get_temp_key_expiration();
            $time_elapsed = time() - $timestamp;
            return $time_elapsed >= $expiration;
        }

        private function maybe_set_hoster_api_key()
        {
            $current_api_key = (string) $this->functions->get_option('fastpixel_api_key', '');
            if ($current_api_key !== '') {
                return;
            }

            $hoster_api_key = '';
            if (defined('FASTPIXEL_DISABLE_ONBOARDING') && FASTPIXEL_DISABLE_ONBOARDING) {
                $hoster_api_key = 'hoster_';
            } elseif (defined('RB_DASHBOARD_BASE_URL')) {
                $hoster_api_key = 'raidbox_';
            }

            if ($hoster_api_key !== '') {
                $this->api_key = $hoster_api_key;
                $this->save_key();
                FASTPIXEL_Debug::log('[ApiKey] hoster API key auto-set from constants', $hoster_api_key);
            }
        }
    }
    new FASTPIXEL_Api_Key();
}
