<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Excluded_Url_Params')) {
    class FASTPIXEL_Excluded_Url_Params
    {
        public static $instance;
        protected $functions;
        protected $config;
        protected $exclude_all_params = false;
        protected $default_excluded_params = [
            's'                   => '', // WordPress search
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
            '_wp-find-template'   => '', // default wp/gutenberg function
            'add-to-cart'         => '', // shopping
            'add_to_cart'         => '', // shopping
            'tagverify'           => '', // site kit by google
            'wc-ajax'             => '', // WooCommerce
            'bfwkey'              => '',
            'wpml-app'            => '', // WPML app   
        ];

        public function __construct()
        {
            self::$instance = $this;
            //initializing functions and config
            $this->functions = FASTPIXEL_Functions::get_instance();
            $this->config = FASTPIXEL_Config_Model::get_instance();
            add_filter('fastpixel/init/excluded', [$this, 'is_excluded'], 14, 2);
            add_filter('fastpixel/is_cache_request_allowed/excluded', [$this, 'is_excluded'], 14, 2);
            add_filter('fastpixel/rest-api/excluded', [$this, 'is_excluded'], 14, 2);
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
            if ($url->params_stripped()) {
                $excluded_params = $this->default_excluded_params;
            } else {
                if (function_exists('get_option')) {
                    $excluded_params = $this->functions->get_option('fastpixel_params_exclusions');
                    $excluded_params_exploded = explode(chr(13), trim($excluded_params));
                } else {
                    $excluded_params = $this->config->get_option('fastpixel_params_exclusions');
                    $excluded_params_exploded = explode(" ", trim($excluded_params));
                }
                $user_excluded_params = [];
                foreach ($excluded_params_exploded as $value) {
                    $exploded_param = explode('=', $value);
                    $key = ltrim($exploded_param[0], "\r\n");
                    if (!empty($exploded_param[1])) {
                        $user_excluded_params[$key] = $exploded_param[1];
                    } else {
                        $user_excluded_params[$key] = '';
                    }
                }
                $excluded_params = array_merge($this->default_excluded_params, $user_excluded_params);
            }
            $excluded_params_keys = array_keys($excluded_params);
            if (!$url->params_stripped()) {
                $url_checked = $url;
            } else if ($url->params_stripped()) {
                $url_checked = new FASTPIXEL_Url($url->get_original_url());
            }
            if ($url_checked->get_query()) {
                //parsing request params
                parse_str($url_checked->get_query(), $parsed_request_params);
                //checking url params with excluded keys
                foreach ($parsed_request_params as $key => $value) {
                    //check if key is present in excluded array
                    if (in_array($key, $excluded_params_keys)) {
                        //check for excluded value,
                        //if value is present and it matches then set exclusion to true, otherwise set exclusion to true
                        if (!empty($excluded_params[$key])) {
                            if (strtolower($excluded_params[$key]) == strtolower($value)) {
                                return true;
                            } else {
                                continue;
                            }
                        } else {
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
