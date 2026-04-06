<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Excluded_By_Cookie')) {
    class FASTPIXEL_Excluded_By_Cookie
    {
        protected $debug = false;
        public static $instance;
        protected $functions;
        protected $config;
        protected $default_cookies = [
            'bfw_ref_cookie_set' => null
        ];

        public function __construct()
        {
            self::$instance = $this;
            //initializing functions and config
            $this->functions = FASTPIXEL_Functions::get_instance();
            $this->config = FASTPIXEL_Config_Model::get_instance();
            add_filter('fastpixel/init/excluded', [$this, 'is_excluded'], 11, 2);
            add_filter('fastpixel/is_cache_request_allowed/excluded', [$this, 'is_excluded'], 11, 2);
            add_filter('fastpixel/rest-api/excluded', [$this, 'is_excluded'], 11, 2);
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Excluded_By_Cookie();
            }
            return self::$instance;
        }

        public function is_excluded($excluded, $url) {
            if ($excluded == true) {
                return $excluded;
            }

            foreach ($this->get_excluded_cookies() as $cookie_name => $cookie_value) {
                if (!array_key_exists($cookie_name, $_COOKIE)) {
                    continue;
                }

                if ($cookie_value !== null && (string) $_COOKIE[$cookie_name] !== (string) $cookie_value) {
                    continue;
                }

                return true;
            }

            return false;
        }

        protected function get_excluded_cookies(): array
        {
            if (function_exists('get_option')) {
                $raw_cookies = $this->functions->get_option('fastpixel_cookie_exclusions', '');
            } else {
                $raw_cookies = $this->config->get_option('fastpixel_cookie_exclusions');
            }

            $cookies = $this->default_cookies;
            $rows = preg_split('/[\r\n\s]+/', trim((string) $raw_cookies));
            if (!is_array($rows)) {
                return $cookies;
            }

            foreach ($rows as $row) {
                $row = trim((string) $row);
                if ($row === '') {
                    continue;
                }

                $parts = explode('=', $row, 2);
                $cookie_name = trim((string) $parts[0]);
                if ($cookie_name === '') {
                    continue;
                }

                // User-defined cookie exclusions are presence-based even if a value is provided.
                $cookies[$cookie_name] = null;
            }

            return $cookies;
        }
    }
    new FASTPIXEL_Excluded_By_Cookie();
}
