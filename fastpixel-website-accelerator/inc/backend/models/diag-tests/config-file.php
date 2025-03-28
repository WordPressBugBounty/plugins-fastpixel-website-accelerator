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
        protected $functions;
        protected $config_file;

        public function __construct() {
            parent::__construct();
            $this->functions = FASTPIXEL_Functions::get_instance();
            $this->config_file = FASTPIXEL_Config_Model::get_instance();
        }

        public function test() {
            $this->passed = true;
            //checking if config file params match database params, if not then updating config to database params
            $options = ['fastpixel_serve_stale', 'fastpixel_display_cached_for_logged', 'fastpixel_cache_lifetime'];
            $modified = false;

            foreach ($options as $option_name) {
                $option_value = $this->functions->get_option($option_name, false);
                if ($this->config_file->get_option($option_name) != $option_value) {
                    $this->config_file->set_option($option_name, $option_value);
                    $modified = true;
                }   
            }
            if ($this->check_permalinks()) {
                $modified = true;
            }
            if ($this->check_wpml()) {
                $modified = true;
            }
            if ($modified) {
                $this->config_file->save_file();
            }
        }

        public function activation_test() {
            return true;
        }

        public function l10n_name() {
            $this->name = esc_html__('Configuration File', 'fastpixel-website-accelerator');
        }

        protected function check_permalinks() {
            //skip check when multisite
            if (function_exists('is_multisite') && is_multisite()) {
                return false;
            }
            //check permalinks
            $modified = false;
            $permalink_stucture = $this->functions->get_option('permalink_structure');
            if (preg_match('/\/$/', $permalink_stucture)) {
                if ($this->config_file->get_option('fastpixel_force_trailing_slash') != true) {
                    $this->config_file->set_option('fastpixel_force_trailing_slash', true);
                    $modified = true;
                }
            } else {
                if ($this->config_file->get_option('fastpixel_force_trailing_slash') != false) {
                    $this->config_file->set_option('fastpixel_force_trailing_slash', false);
                    $modified = true;
                }
            }
            return $modified;
        }

        protected function check_wpml() {
            //probably WPML should not be used with multisite
            if (function_exists('is_multisite') && is_multisite()) {
                return false;
            }
            $force_redirect_for_default_language = $this->config_file->get_option('fastpixel_wpml_use_directory_for_default_language');
            //if wpml is enabled
            if (defined('ICL_SITEPRESS_VERSION')) {
                $settings = get_option('icl_sitepress_settings');
                $use_directory = false;
                if (!empty($settings['urls']['directory_for_default_language'])) {
                    $use_directory = $settings['urls']['directory_for_default_language'];
                }
                if ($use_directory == true && $force_redirect_for_default_language == false) {
                    $this->config_file->set_option('fastpixel_wpml_use_directory_for_default_language', true);
                    return true;
                } elseif ($use_directory == false && $force_redirect_for_default_language == true) {
                    $this->config_file->set_option('fastpixel_wpml_use_directory_for_default_language', false);
                    return true;
                }
                return false;
            } else {
                if ($force_redirect_for_default_language == true) {
                    $this->config_file->set_option('fastpixel_wpml_use_directory_for_default_language', false);
                    return true;
                }
            }
        }
    }
    new FASTPIXEL_Diag_Test_Config();
}
