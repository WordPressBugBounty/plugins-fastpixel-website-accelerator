<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Plugin_Excludes')) {
    class FASTPIXEL_Plugin_Excludes extends FASTPIXEL_Exclude
    {

        protected $excluded_pages = [];
        protected $excluded_params = [
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
            '_wp-find-template'   => '' //default wp/gutenberg function
        ];
        protected $excluded_params_keys = [];

        public function __construct() {
            parent::__construct();
            //initializing excludes
            $functions = FASTPIXEL_Functions::get_instance();

            //getting exclusions
            $exclusions = $functions->get_option('fastpixel_exclusions');
            if (is_string($exclusions)) {
                $this->excluded_pages = explode("\r\n", $exclusions);
            } else if (is_array($exclusions)) {
                $this->excluded_pages = $exclusions;
            }

            //getting excluded params
            $excluded_params = $functions->get_option('fastpixel_params_exclusions', '');
            //getting array of excluded params
            if (is_string($excluded_params)) {
                $excluded_array = explode("\r\n", $excluded_params);
            } else if (is_array($excluded_params)) {
                $excluded_array = $excluded_params;
            } else {
                $excluded_array = [];
            }
            if (!empty($excluded_array) && is_array($excluded_array)) {
                //generating array of excluded params
                foreach ($excluded_array as $param) {
                    if (!empty(trim($param))) {
                        parse_str($param, $parsed_param);
                        $key = strtolower(trim(key($parsed_param)));
                        $this->excluded_params[$key] = strtolower(trim($parsed_param[$key]));
                    }
                }
                //getting excluded keys for check
                $this->excluded_params_keys = array_merge($this->excluded_params_keys, array_keys($this->excluded_params));
            }
        }

        public function check_is_exclusion($url) {
            if (empty($url)) {
                FASTPIXEL_Debug::log('Empty url recieved, check skipped');
                return;
            }
            $test_url = $url->get_url();
            if ($this->specific_check_by_homepage_and_params($test_url)) {
                return true;
            }

            if($this->check_by_params($test_url)) {
                return true;
            }

            if ($this->check_by_url($test_url)) {
                return true;
            }
        }

        //very specific case, not sure if this is bot, but it generates a lot of unnecessary pages
        protected function specific_check_by_homepage_and_params($url) {
            $request_url = wp_parse_url($url, PHP_URL_QUERY);
            if (!empty($request_url) && function_exists('is_front_page') && is_front_page() && preg_match('/x\d{3}=[0-9a-zA-Z]{4}/i', $request_url)) {
                return true;
            }
            return false;
        }

        protected function check_by_params($url) {
            $request_query = wp_parse_url($url, PHP_URL_QUERY);
            if (empty($request_query)) {
                return false;
            }
            //parsing request params
            parse_str($request_query, $parsed_request_params);
            //checking url params with excluded keys
            foreach($parsed_request_params as $key => $value) {
                //check if key is present in excluded array
                if(in_array($key, $this->excluded_params_keys)) {
                    //check for excluded value,
                    //if value is present and it matches then set exclusion to true, otherwise set exclusion to true
                    if(!empty($this->excluded_params[$key])) {
                        if (strtolower($this->excluded_params[$key]) == strtolower($value)) {
                            return true;
                        } else {
                            continue;
                        }
                    } else {
                        return true;
                    }
                }
            }
        
            return false;
        }

        protected function check_by_url($url) {
            $request_url_path = rtrim(wp_parse_url($url, PHP_URL_PATH), '/');
            if (!empty($this->excluded_pages)) {
                foreach ($this->excluded_pages as $exclusion) {
                    if (empty($exclusion)) {
                        continue;
                    }
                    if (preg_match('/\*/', $exclusion)) {
                        $pattern = '/' . preg_replace('/\\\\?\*/i', '.*?', preg_quote($exclusion, '/')) . '/';
                        if (!empty($request_url_path) && preg_match($pattern, $request_url_path)) {
                            return true;
                        }
                    } else if ($request_url_path == rtrim(trim($exclusion), '/')) {
                        return true;
                    }
                }
            }
            return false;
        }
    }

    new FASTPIXEL_Plugin_Excludes();
}
