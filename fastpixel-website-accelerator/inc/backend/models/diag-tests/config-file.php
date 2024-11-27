<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Diag_Test_Config')) {
    class FASTPIXEL_Diag_Test_Config extends FASTPIXEL_Diag_Test 
    {
        protected $order_id = 10;
        protected $name = 'Configuration File';
        protected $activation_check = false;
        protected $display_notifications = false;
        protected $visible_on_diagnostics_page = false;


        public function __construct() {
            parent::__construct();
        }

        public function test() {
            $this->passed = true;
            //checking if config file params match database params, if not then updating config to database params
            $options = ['fastpixel_serve_stale', 'fastpixel_display_cached_for_logged', 'fastpixel_cache_lifetime'];
            $modified = false;
            $functions = FASTPIXEL_Functions::get_instance();
            $config_file = FASTPIXEL_Config_Model::get_instance();
            foreach ($options as $option_name) {
                $option_value = $functions->get_option($option_name, false);
                if ($config_file->get_option($option_name) != $option_value) {
                    $config_file->set_option($option_name, $option_value);
                    $modified = true;
                }   
            }
            if ($modified) {
                $config_file->save_file();
            }
        }

        public function activation_test() {
            return true;
        }

        public function l10n_name() {
            $this->name = esc_html__('Configuration File', 'fastpixel-website-accelerator');
        }
    }
    new FASTPIXEL_Diag_Test_Config();
}
