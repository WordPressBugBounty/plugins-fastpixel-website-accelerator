<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Nonces')) {
    class FASTPIXEL_Nonces
    {
        public static $instance;
        protected $functions;
        protected $life_time = 3600 * 24 * 30; 

        public function __construct()
        {
            self::$instance = $this;
            $this->functions = FASTPIXEL_Functions::get_instance();
            //setting nonce life time to 30 days
            add_filter('nonce_life', function ($time, $action) {
                $referer = empty($_SERVER["HTTP_REFERER"]) ? '' : $_SERVER["HTTP_REFERER"];
                if ($this->functions->user_is_logged_in() || preg_match('/wp-admin/', $referer)) {
                    return $time;
                }
                return $this->life_time;
            }, 99999, 2);
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Nonces();
            }
            return self::$instance;
        }
    }
    new FASTPIXEL_Nonces();
}
