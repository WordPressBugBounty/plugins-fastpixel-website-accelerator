<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Config_Model')) {
    class FASTPIXEL_Config_Model {

        public static $instance;
        protected $options = [
            'fastpixel_serve_stale'                             => false,
            'fastpixel_display_cached_for_logged'               => false,
            'fastpixel_javascript_optimization'                 => 1, //1 => 'optimize', 2 => 'delaycritical', 3 => 'donotoptimize'
            'fastpixel_cache_lifetime'                          => 1, //1 => unlimited, 2 => 24H, 3 => 12H
            'fastpixel_enabled_modules'                         => [],
            'fastpixel_exclusions'                              => false,
            'fastpixel_exclude_all_params'                      => false,
            'fastpixel_params_exclusions'                       => false,
            'fastpixel_force_trailing_slash'                    => true, //used in single site install, usually is set to true
            'fastpixel_wpml_use_directory_for_default_language' => false
        ];
        protected $config_dir;
        protected $config_file;
        protected $functions;
        protected $api_key;

        public function __construct() {
            //creating instance
            if (!empty(self::$instance)) {
                return;
            }
            self::$instance = $this;
            //creating functions instance
            $this->functions = FASTPIXEL_Functions::get_instance();
            //setting config dir
            $this->config_dir = $this->functions->get_cache_dir();
            //setting config file
            $this->config_file =  $this->config_dir . DIRECTORY_SEPARATOR . 'config.json';

            //reading options from file if file exists
            if (file_exists($this->config_file)) {
                /*
                 * can't use here wordpress native functions(WP_Filesystem) because this function fires early in advanced-cache.php
                 */
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- none available before WordPress is loaded.
                $json = json_decode(file_get_contents($this->config_file), true); //phpcs:ignore
                $this->options = array_merge($this->options, ($json != null ? $json : []));
            }
            //function that create/update config file
            if (is_admin()) {
                add_action('admin_init', [$this, 'update_config']);
            }
        }

        public static function get_instance() {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Config_Model();
            }
            return self::$instance;
        }

        public function save_file() {
            do_action('fastpixel/settings/config/save', $this);
            if (!file_exists($this->config_dir)) {
                wp_mkdir_p($this->config_dir);
            }
            //initializing filesystem
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . '/wp-admin/includes/file.php';
                WP_Filesystem();
            }
            //saving config file
            if (!$wp_filesystem->put_contents($this->config_file, wp_json_encode($this->options))) {
                FASTPIXEL_DEBUG::log('Unable to save configuration file');
            }
        }

        public function update_config() {
            //checking for post request and fastpixel-nonce
            if (sanitize_text_field($_SERVER['REQUEST_METHOD']) == 'POST' && isset($_POST['fastpixel-nonce'])) {
                if (wp_verify_nonce(sanitize_key($_POST['fastpixel-nonce']), 'fastpixel-settings')) {
                    $options = [
                        'fastpixel_serve_stale',
                        'fastpixel_display_cached_for_logged',
                        'fastpixel_javascript_optimization',
                        'fastpixel_cache_lifetime',
                        'fastpixel_exclusions',
                        'fastpixel_params_exclusions',
                        'fastpixel_exclude_all_params'
                    ];
                    foreach($options as $option_name) {
                        //validating checkboxes
                        if (in_array($option_name, [
                            'fastpixel_serve_stale',
                            'fastpixel_display_cached_for_logged',
                            'fastpixel_exclude_all_params'
                        ])) {
                            if (isset($_POST[$option_name]) && $_POST[$option_name]) {
                                $value = true;
                            } else {
                                $value = false;
                            }
                            $r = $this->set_option($option_name, $value);
                        } else
                        //other fields
                        {
                            if (isset($_POST[$option_name])) {
                                $this->set_option($option_name, sanitize_text_field($_POST[$option_name]));
                            }
                        }
                    }
                    $this->check_permalinks();
                }
                $this->save_file();
            }
        }

        public function get_option($option_name) {
            if (isset($this->options[$option_name])) {
                return $this->options[$option_name];
            }
            return false;
        }

        public function set_option($option_name, $option_value = false)
        {
            if (array_key_exists($option_name, $this->options)) {
                $this->options[$option_name] = $option_value;
                return true;
            }
            return false;
        }

        protected function check_permalinks() {
            //check permalinks
            if (function_exists('is_multisite') && is_multisite()) {
                return false;
            }
            $permalink_stucture = $this->functions->get_option('permalink_structure');
            if (preg_match('/\/$/', $permalink_stucture)) {
                $this->set_option('fastpixel_force_trailing_slash', true);
            } else {
                $this->set_option('fastpixel_force_trailing_slash', false);
            }
        }
    }
}
