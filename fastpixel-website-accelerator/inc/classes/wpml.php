<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_WPML_Frontend')) {
    class FASTPIXEL_WPML_Frontend
    {

        public static $instance;

        public function __construct()
        {
            self::$instance = $this;
            add_action('init', [$this, 'remove_wpml_browser_redirect'], 100);
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_WPML_Frontend();
            }
            return self::$instance;
        }

        public function remove_wpml_browser_redirect() {
            if (class_exists('WPML_Browser_Redirect')) {
                if (
                    isset($_SERVER['HTTP_USER_AGENT']) && !empty($_SERVER['HTTP_USER_AGENT']) &&
                    (strpos($_SERVER['HTTP_USER_AGENT'], 'FastPixel') !== false)
                ) {
                    add_filter('wpml_enqueue_browser_redirect_language', '__return_false', 99, 1);
                }
            } 
        }
    }
    new FASTPIXEL_WPML_Frontend();
}
