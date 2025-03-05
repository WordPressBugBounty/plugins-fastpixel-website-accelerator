<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_TranslatePress_Front')) {
    class FASTPIXEL_TranslatePress_Front
    {
        public static $instance;
        
        public function __construct()
        {
            self::$instance = $this;
            //TODO: check if we need local cache for frontend, now we disable it for TranslatePress
            add_action('plugins_loaded', function () {
                if (class_exists('TRP_Translate_Press')) {
                    $local_cache = FASTPIXEL_Local_Cache::get_instance();
                    remove_action('fastpixel/shutdown', [$local_cache, 'get_buffer'], 10); //removing local cache buffer action
                    remove_action('fastpixel/shutdown/request/before', [$local_cache, 'save'], 10); //removing function that save buffer to file
                }
            });
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_TranslatePress_Front();
            }
            return self::$instance;
        }
    }
    new FASTPIXEL_TranslatePress_Front();
}
