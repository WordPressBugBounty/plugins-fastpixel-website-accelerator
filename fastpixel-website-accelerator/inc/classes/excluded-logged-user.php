<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Excluded_Logged_User')) {
    class FASTPIXEL_Excluded_Logged_User
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
            add_filter('fastpixel/init/excluded', [$this, 'is_excluded'], 12, 2);
            add_filter('fastpixel/is_cache_request_allowed/excluded', [$this, 'is_excluded'], 12, 2);
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Excluded_Logged_User();
            }
            return self::$instance;
        }

        public function is_excluded($excluded, $url) {
            if ($excluded == true) {
                return $excluded;
            }
            /**
             * do not serve cached pages for logged in users if enabled
             */
            if ($this->functions->user_is_logged_in() && !$this->config->get_option('fastpixel_display_cached_for_logged')) {
                return true;
            }
            return false;
        }
    }
    new FASTPIXEL_Excluded_Logged_User();
}
