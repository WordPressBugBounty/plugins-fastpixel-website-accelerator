<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Settings_Fonts')) {
    class FASTPIXEL_Settings_Fonts 
    {
        public static $instance;
        protected $enabled = false;
        protected $functions;

        public function __construct()
        {
            if (!class_exists('FASTPIXEL\FASTPIXEL_Functions')) {
                return; //stop early if class not exists
            }
            $this->enabled = true;
            $this->functions = FASTPIXEL_Functions::get_instance();
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Settings_Fonts();
            }
            return self::$instance;
        }

        public function get_module_settings(): array {
            if ($this->enabled == true) {
                //fonts params
                $settings['settings'] = [
                    'soft' => false, //default value
                ];
                $force_soft_fonts = $this->functions->get_option('fastpixel_fonts_soft', false); // true/false, default false
                if ($force_soft_fonts) {
                    $settings['settings']['soft'] = true;
                }
                return $settings;
            }
            return [];
        }
    }
}
