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
        protected $cookies = [
            'bfw_ref_cookie_set' => null
        ];

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
                self::$instance = new FASTPIXEL_Excluded_By_Cookie();
            }
            return self::$instance;
        }

        public function is_excluded($excluded, $url) {
            if ($excluded == true) {
                return $excluded;
            }
            //loop through cookies array
            foreach ($this->cookies as $cookie_name => $cookie_value) {
                //checking if we have required cookie by it's name
                if (!empty($_COOKIE[$cookie_name])) {
                    //checking if we have specific value for cookie and if it matches
                    if (!empty($cookie_value) && $_COOKIE[$cookie_name] == $cookie_value) {
                        return true;
                    } elseif (empty($cookie_value)) {
                        return true;
                    }
                }
            }
            return false;
        }
    }
    new FASTPIXEL_Excluded_By_Cookie();
}
