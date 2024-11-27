<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Settings_Images')) {
    class FASTPIXEL_Settings_Images 
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
                self::$instance = new FASTPIXEL_Settings_Images();
            }
            return self::$instance;
        }

        public function get_settings(): array {
            if ($this->enabled == true) {
                //images optimization
                $quality_params = array(1 => 'lossy', 2 => 'glossy', 3 => 'lossless');
                $images_optimization = $this->functions->get_option('fastpixel_images_optimization', 1); //default lossy
                //crop images
                $crop = $this->functions->get_option('fastpixel_images_crop', true); // true/false, default true
                $settings = [
                    'imageQuality' => $quality_params[$images_optimization],
                    'cropImages'   => (bool) $crop,
                ];
                return $settings;
            }
            return [];
        }

        public function get_module_settings(): array {
            if ($this->enabled == true) {
                //force images width/height attributes
                $force_dimensions = $this->functions->get_option('fastpixel_force_image_dimensions', false); // true/false, default false
                return [
                    'enabled'  => true,
                    'settings' => [
                        'forceImageDimensions' => (bool) $force_dimensions
                    ]
                ];
            }
            return [];
        }
    }
}
