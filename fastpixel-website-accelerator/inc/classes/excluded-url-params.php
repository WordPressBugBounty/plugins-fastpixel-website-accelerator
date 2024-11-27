<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Excluded_Url_Params')) {
    class FASTPIXEL_Excluded_Url_Params
    {
        protected $debug = false;
        public static $instance;
        protected $functions;
        protected $config;
        protected $default_excluded_params = [
            'action'              => 'elementor', // Elementor
            'elementor-preview'   => '', // Elementor
            'bricks'              => '', // Bricks plugin
            'brizy-edit-iframe'   => '', // Brizy plugin
            'builder'             => '', // Fusion Builder
            'ct_builder'          => '', // Oxygen
            'et_fb'               => '', // Divi
            'fb-edit'             => '', // Fusion Builder
            'fl_builder'          => '', // Beaver Builder
            'preview'             => '', // Blockeditor & Gutenberg
            'tb-preview'          => '', // Themify
            'tve'                 => '', // Thrive
            'uxb_iframe'          => '', // Flatsome UX Builder
            'vc_action'           => '', // WP Bakery
            'vc_editable'         => '', // WP Bakery
            'vcv-action'          => '', // WP Bakery
            'wyp_mode'            => '', // Yellowpencil plugin
            'wyp_page_type'       => '', // Yellowpencil plugin
            'zionbuilder-preview' => '', // Zion Builder plugin
            '_wp-find-template'   => '', //default wp/gutenberg function
            'add-to-cart'         => '', //shopping
            'add_to_cart'         => '', //shopping
            'tagverify'           => '', //site kit by google
        ];

        public function __construct()
        {
            self::$instance = $this;
            //initializing functions and config
            $this->functions = FASTPIXEL_Functions::get_instance();
            $this->config = FASTPIXEL_Config_Model::get_instance();
            add_filter('fastpixel/init/excluded', [$this, 'is_excluded'], 14, 2);
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Excluded_Url_Params();
            }
            return self::$instance;
        }

        public function is_excluded($excluded, $url) {
            if ($excluded == true) {
                return $excluded;
            }
            /**
             * checking excluded urls by params
             */
            $excluded_params = $this->config->get_option('fastpixel_params_exclusions');
            if (is_string($excluded_params)) {
                $excluded_params_exploded = array_filter(explode(" ", $excluded_params));
            } else if (is_array($excluded_params)) {
                $excluded_params_exploded = $excluded_params;
            } else {
                $excluded_params_exploded = [];
            }
            $excluded_params = $this->default_excluded_params;
            if (!empty($excluded_params_exploded) && is_array($excluded_params_exploded)) {
                //generating array of excluded params
                foreach ($excluded_params_exploded as $param) {
                    if (!empty(trim($param))) {
                        parse_str($param, $parsed_param);
                        $key = strtolower(trim(key($parsed_param)));
                        $excluded_params[$key] = strtolower(trim($parsed_param[$key]));
                    }
                }
            }
            $excluded_params_keys = array_keys($excluded_params);
            if (!empty($url->get_query())) {
                //parsing request params
                parse_str($url->get_query(), $parsed_request_params);
                //checking url params with excluded keys
                foreach ($parsed_request_params as $key => $value) {
                    //check if key is present in excluded array
                    if (in_array($key, $excluded_params_keys)) {
                        //check for excluded value,
                        //if value is present and it matches then set exclusion to true, otherwise set exclusion to true
                        if (!empty($excluded_params[$key])) {
                            if (strtolower($excluded_params[$key]) == strtolower($value)) {
                                //need to delete if url is excluded
                                $this->functions->delete_cached_files($url->get_url_path());
                                return true;
                            } else {
                                continue;
                            }
                        } else {
                            //need to delete if url is excluded
                            $this->functions->delete_cached_files($url->get_url_path());
                            return true;
                        }
                    }
                }
            }
            return false;
        }
    }
    new FASTPIXEL_Excluded_Url_Params();
}