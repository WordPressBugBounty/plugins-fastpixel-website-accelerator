<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Excluded_Urls')) {
    class FASTPIXEL_Excluded_Urls
    {
        protected $debug = false;
        public static $instance;
        protected $functions;
        protected $config;
        protected $default_url_exclusions = [
            '/robots.txt',
            '/.htaccess',
            '/favicon.ico',
        ];

        public function __construct()
        {
            self::$instance = $this;
            //initializing functions and config
            $this->functions = FASTPIXEL_Functions::get_instance();
            $this->config = FASTPIXEL_Config_Model::get_instance();
            add_filter('fastpixel/init/excluded', [$this, 'is_excluded'], 13, 2);
            add_filter('fastpixel/init/excluded', [$this, 'is_excluded_by_trailing_slash'], 8, 2);
            add_filter('fastpixel/init/excluded', [$this, 'is_excluded_by_wpml'], 14, 2);
            if (is_admin()) {
                add_filter('fastpixel/backend_functions/cache_status_display/excluded', [$this, 'admin_check_is_excluded'], 13, 2);
                add_filter('fastpixel/backend/purge/single/post/is_excluded', [$this, 'admin_check_is_excluded'], 13, 2);
                add_filter('fastpixel/backend/purge/single/term/is_excluded', [$this, 'admin_check_is_excluded'], 13, 2);
                add_filter('fastpixel/backend/purge/single/by_url/excluded', [$this, 'admin_check_is_excluded'], 13, 2);
            }
            add_filter('fastpixel/admin_bar/purge_this_button_exclude', [$this, 'admin_bar_check_url_is_excluded'], 20, 2);
            add_filter('fastpixel/rest-api/excluded', [$this, 'is_excluded'], 13, 2);
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Excluded_Urls();
            }
            return self::$instance;
        }

        public function is_excluded($excluded, $url) {
            if ($excluded == true) {
                return $excluded;
            }
            /**
             * checking excluded urls
             */
            if (function_exists('get_option')) {
                $user_url_exclusions = $this->functions->get_option('fastpixel_exclusions');
                $url_exclusions = explode(chr(13), $user_url_exclusions);
            } else {
                $user_url_exclusions = $this->config->get_option('fastpixel_exclusions');
                $url_exclusions = explode(" ", $user_url_exclusions);
            }
            $url_exclusions = array_merge($this->default_url_exclusions, $url_exclusions);
            $excluded_urls = array_filter(apply_filters('fastpixel_exclude_url', $url_exclusions));
            foreach ($excluded_urls as $excluded_url) {
                $excluded_url = trim($excluded_url);
                if (!empty($excluded_url)) {
                    //regular expression match, for now we wil support only *
                    if (preg_match('/\*/', $excluded_url)) {
                        $prepared_req_url = $url->get_path() != '/' ? strtolower(rtrim($url->get_path(), '/')) . '/' : '/';
                        $pattern = '/' . preg_replace('/\\\\?\*/i', '.*?', preg_quote($excluded_url, '/')) . '/';
                        if ($this->debug) {
                            FASTPIXEL_Debug::log('Excluded Urls regexp: url', $prepared_req_url);
                            FASTPIXEL_Debug::log('Excluded Urls regexp: pattern', $pattern);
                            FASTPIXEL_Debug::log('Excluded Urls regexp: match', preg_match($pattern, $prepared_req_url));
                        }
                        if (!empty($prepared_req_url) && preg_match($pattern, $prepared_req_url)) {
                            //need to delete cache if url is excluded
                            $this->functions->delete_cached_files($url->get_url_path());
                            return true;
                        }
                    } else {
                        $prepared_exc_url = $excluded_url != '/' ? strtolower(rtrim($excluded_url, '/')) : '/';
                        $prepared_req_url = $url->get_path() != '/' ? strtolower(rtrim($url->get_path(), '/')) : '/';
                        if ($this->debug) {
                            FASTPIXEL_Debug::log('Excluded Urls direct: excluded_url', $prepared_exc_url);
                            FASTPIXEL_Debug::log('Excluded Urls direct: requested_url', $prepared_req_url);
                            FASTPIXEL_Debug::log('Excluded Urls direct: match', $prepared_req_url == $prepared_exc_url);
                        }
                        if (!empty($prepared_req_url) && $prepared_req_url == $prepared_exc_url) {
                            //need to delete if url is excluded
                            $this->functions->delete_cached_files($url->get_url_path());
                            return true;
                        }
                    }
                }
            }
            return false;
        }

        public function admin_check_is_excluded($status, $args) {
            if ($status) {
                return $status;
            }
            if (!empty($args['url'])) {
                $url = new FASTPIXEL_Url($args['url']);
                $status = $this->is_excluded($status, $url);
            }
            return $status; 
        }

        public function admin_bar_check_url_is_excluded($status, $args) {
            if ($status) {
                return $status;
            }
            if (empty($args['url'])) {
                return false;
            }
            $url = new FASTPIXEL_Url($args['url']);
            $status = $this->is_excluded($status, $url);
            return $status;
        }

        public function is_excluded_by_trailing_slash($excluded, $url) {
            if ($excluded == true) {
                return $excluded;
            }
            if (!$this->is_home()) {
                //temporary solution, if multisite then skip check
                if (is_multisite()) {
                    return $excluded;
                }
                $force = $this->config->get_option('fastpixel_force_trailing_slash');
                if ($force && !preg_match('/\/$/', $url->get_path())) { //case when we need to force trailing slash and url does not have it
                    return true;
                }
                if (!$force && preg_match('/\/$/', $url->get_path())) { //case when we need to force no trailing slash and url has it
                    return true;
                }
            }
            return false;
        }

        public function is_excluded_by_wpml($excluded, $url)
        {
            if ($excluded == true) {
                return $excluded;
            }
            /**
             * checking excluded urls
             */

            $use_directory = $this->config->get_option('fastpixel_wpml_use_directory_for_default_language');
            if ($use_directory && $this->is_home()) {
                return true; //we need to redirect homepage to directory, example / -> /en/
            }
            return false;
        }

        protected function is_home()
        {
            if (!empty($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] == '/') {
                return true;
            }
            return false;
        }
    }
    new FASTPIXEL_Excluded_Urls();
}
