<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Cache')) {
    class FASTPIXEL_Cache 
    {
        protected $debug = false;
        protected static $instance;
        protected $config;
        protected $functions;
        protected $cache_dir;
        protected $url;
        protected $url_path;
        protected $html_path;
        protected $json_path;
        protected $header_path;
        protected $meta_path;
        // protected $request_wait_time = 90;
        protected $debug_mode = false;
        protected $buffer;
        protected $x_queue_mode = false;


        public function __construct() {
            self::$instance  = $this;
            //checking for classes to avoid breakdown
            if (!class_exists('FASTPIXEL\FASTPIXEL_Debug') || !class_exists('FASTPIXEL\FASTPIXEL_Url') ||
                !class_exists('FASTPIXEL\FASTPIXEL_Functions') || !class_exists('FASTPIXEL\FASTPIXEL_Config_Model')) {
                return false;
            }
            $this->functions = FASTPIXEL_Functions::get_instance();
            $this->config    = FASTPIXEL_Config_Model::get_instance();
            
            //loading modules (need them in advanced-cache.php)
            $this->load_modules();
            //define FASTPIXEL_REST_URL for backend actions, using hook because during include rest_url throw error
            add_action('init', function () {
                if (!defined('FASTPIXEL_REST_URL') && function_exists('rest_url')) {
                    define('FASTPIXEL_REST_URL', get_rest_url(get_current_blog_id(), 'fastpixel-website-accelerator' . '/v1/update'));
                }
            });
            if (!defined('FASTPIXEL_API_URL')) {
                define('FASTPIXEL_API_URL', FASTPIXEL_API_HOST . '/api/v1/enqueue');
            }
            //loading classes
            $this->load_classes(FASTPIXEL_PLUGIN_DIR . '/inc/classes/');

            do_action('fastpixel/init/early');

            //running initialization to check for cached page and return it early if exists
            $this->init();
        }

        public function init() {
            //initializing url
            $this->url = new FASTPIXEL_Url(null, $this->config->get_option('fastpixel_exclude_all_params'));

            if (!$this->check_request_agent()) {
                return;
            }
            if (!$this->check_request()) {
                return;
            }
            $this->check_request_headers();

            do_action('fastpixel/init');

            //registering cache request on shutdown
            add_action('fastpixel/shutdown', [$this, 'request_page_cache']);
            //registering shutdown callback function
            register_shutdown_function([$this, 'on_shutdown']);
            //registering function that detect canonical redirect
            add_filter('redirect_canonical', [$this, 'check_redirect_canonical'], 10, 2);
            //returning cached file if it exists
            $cached_files = FASTPIXEL_Cache_Files::get_instance();
            $cached_files->serve($this->url);
        }

        public static function get_instance() {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Cache();
            }
            return self::$instance;
        }

        protected function check_request_agent() {
            /** 
            * To avoid loops, otherwise request from service will also trigger it
            */
            if (isset($_SERVER['HTTP_USER_AGENT']) && !empty($_SERVER['HTTP_USER_AGENT']) && 
            (strpos($this->functions->sanitize_text_field($_SERVER['HTTP_USER_AGENT']), 'FastPixel') !== false)
            ) {
                //need to check if this is required
                return false;
            }
            return true;
        }

        protected function check_request() {
            /**
            * if fastpixeldebug query is present set it for later usage
            */
            if (isset($_SERVER['QUERY_STRING']) &&
                strpos($this->functions->sanitize_text_field($_SERVER['QUERY_STRING']), 'fastpixeldebug') !== false) {
                $this->debug_mode = true;
            }
            /**
            * Don't ever cache non-GET requests.
            */
            if (!isset($_SERVER['REQUEST_METHOD']) || 'GET' !== $this->functions->sanitize_text_field($_SERVER['REQUEST_METHOD'])) {
                return false;
            }
            /**
            * No need to activate this class in wordpress cli or cron or ajax request 
            */
            if (defined('WP_CLI') || 
                defined('DOING_CRON') || 
                defined('DOING_AJAX') ||
                (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($this->functions->sanitize_text_field($_SERVER['HTTP_X_REQUESTED_WITH'])) == 'xmlhttprequest')
            ) {
                return false;
            }
            /*
             * don't ever cache Admin requests
             */
            if (is_admin()) {
                return false;
            }
            /**
             * don't cache request with nocache query
             */
            if (isset($_SERVER['QUERY_STRING']) && strpos($this->functions->sanitize_text_field($_SERVER['QUERY_STRING']), 'fastpixeldisable') !== false) {
                return false;
            }
            /**
            * getting request url
            */
            $requested_url = $this->url;
            /**
            * no need to activate class on api request or if wp-includes is present in the url
            */
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- none available before WordPress is loaded.
            if (isset($_GET['rest_route']) || strpos($requested_url->get_url(), 'wp-json') > 0 || strpos($requested_url->get_url(), 'wp-includes') > 0) { //phpcs:ignore
                return false;
            }
            /**
            * run only for index.php
            */
            if (!in_array($this->functions->sanitize_text_field($_SERVER['SCRIPT_NAME']), ['/index.php'])) {
                return false;
            }
            /**
            * checking for excludes, no need to serve them
            */
            $is_excluded = apply_filters('fastpixel/init/excluded', false, $requested_url);
            if ($is_excluded) {
                return false;
            }
            return true;
        }

        protected function check_request_headers() {
            if (isset($_SERVER['HTTP_X_QUEUE_MODE']) && !empty($_SERVER['HTTP_X_QUEUE_MODE'])) {
                $this->x_queue_mode = $this->functions->sanitize_text_field($_SERVER['HTTP_X_QUEUE_MODE']);
            }
        }

        //function runs on register_shutdown_function
        public function on_shutdown() {
            if ($this->debug) {
                FASTPIXEL_Debug::log('Class FASTPIXEL_Cache: Shutdown');
            }
            do_action('fastpixel/shutdown');
        }

        protected function is_cache_request_allowed() {
            global $post;
            //Step 1: check if there are no Fastpixel errors.
            if ($this->debug && $this->debug_mode) {
                FASTPIXEL_Debug::log('Class FASTPIXEL_Cache: Basic Validation: Stopped. Debug request.');
                return false;
            }
            if (class_exists('FASTPIXEL\FASTPIXEL_Diag')) {
                $diag = FASTPIXEL_Diag::get_instance();
                if (!$diag->run_activation_tests()) {
                    if ($this->debug) {
                        FASTPIXEL_DEBUG::log('Class FASTPIXEL_Cache: Basic Validation: Stopped. Diagnostics errors');
                    }
                    return false;
                }
            }
            //Step 2: Check if it is not admin area
            if (is_admin()) {
                return false;
            }
            //Step 3: Check if user is logged in, do not cache pages when user is logged
            if (function_exists('is_user_logged_in') && is_user_logged_in()) {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('Class FASTPIXEL_Cache: request_page_cache, stopping request for logged in user');
                }
                return false;
            }
            //Step 4: Check if page is 404, search, feed, password protected, or login page
            if (is_404() //do not cache 404 page
                || is_search() //do not cache search page
                || post_password_required() //do not cache password protected pages
                || is_feed() //do not cache feed
                || is_login() //do not cache login page
            ) {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('Class FASTPIXEL_Cache: request_page_cache, Stopped, is 404 or search or feed or password protected page');
                }
                return false;
            }
            //TODO: check this
            // Extra check for rest request, don't run cache on API request
            if (defined('REST_REQUEST') && REST_REQUEST && !defined('FASTPIXEL_SAVE_POST')) {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('Class FASTPIXEL_Cache: REST REQUEST', REST_REQUEST);
                }
                return false;
            }
            //Step 5: 
            //Do not cache not published posts
            if (is_single() && $post->post_status != 'publish') {
                return false;
            }
            //Step 6: Check for excluded post types
            $excluded_post_types = apply_filters('fastpixel_excluded_post_types', []);
            if (!empty($post_id) && is_numeric($post_id)) {
                $post_type = get_post_type($post_id);
                if (in_array($post_type, $excluded_post_types)) {
                    if ($this->debug) {
                        FASTPIXEL_DEBUG::log('Class FASTPIXEL_Cache: request_page_cache, Stopped, post type is excluded from cache');
                    }
                    return false;
                }
            }
            //Step 7: check for pages that should be excluded from cache
            $excludes = FASTPIXEL_Excludes::get_instance();
            $is_exclusion = $excludes->check_is_exclusion($this->url->get_url());
            if ($is_exclusion) {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('Class FASTPIXEL_Cache: Excludes and Meta Validation: Stopped. Page is excluded from cache.');
                }
                //trying to delete existing files if page is exclusion and for some reason cached page exists, or it was created before exclusion was added
                $this->functions->delete_cached_files($this->url->get_url_path());
                return false;
            }

            return true; //request allowed
        }

        public function request_page_cache()
        {
            if (!$this->is_cache_request_allowed()) {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('Class FASTPIXEL_Cache: request not allowed, trying to delete cached if exists for path', $this->url->get_url_path());
                }
                //trying to delete only on outside requests, when users not logged in
                if (!is_user_logged_in()) {
                    $this->functions->delete_cached_files($this->url->get_url_path());
                }
                return false;
            }

            //TODO: check if we need cache lifetime
            // $cache_lifetime = $this->config->get_option('fastpixel_cache_lifetime');
            // //checking cache lifetime
            // if ($cache_lifetime && is_numeric($cache_lifetime) && in_array($cache_lifetime, array(2, 3)) && !empty($this->url) && is_object($this->url)) {
            //     $cache_status = $this->functions->check_post_cache_status($this->url->get_url());
            //     //checking 24H
            //     if ($cache_lifetime == 2 && ($cache_status['have_cache'] && !$cache_status['need_cache']) && time() > $cache_status['html_created_time'] + 24 * 3600) {
            //         $this->functions->update_post_cache($this->url_path, true);
            //     } else 
            //     //checking 12H
            //     if ($cache_lifetime == 3 && ($cache_status['have_cache'] && !$cache_status['need_cache']) && time() > $cache_status['html_created_time'] + 12 * 3600) {
            //         $this->functions->update_post_cache($this->url_path, true);
            //     }
            // }

            do_action('fastpixel/shutdown/request/before', $this->url);
            //Doing Page Cache request
            $request = FASTPIXEL_Request::get_instance();
            $request_headers = ['x-queue-mode' => $this->x_queue_mode ? $this->x_queue_mode : 'push'];
            $requested = $request->cache_request($this->url->get_url(), $request_headers);
            if ($requested) {
                $this->functions->update_post_cache($this->url->get_url_path(), false, true);
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('Class FASTPIXEL_Cache: request_page_cache, Ended Successfully');
                }
                return true;
            } else {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('Class FASTPIXEL_Cache: request_page_cache, Ended with error');
                }
                return false;
            }
        }

        // //function that check api endpoints
        // public function check_endpoints()
        // {
        //     $api_key = $this->functions->get_option('fastpixel_api_key');
        //     if (empty($api_key)) {
        //         return false;
        //     }
        //     if (file_exists(FASTPIXEL_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'endpoint.php')) {
        //         $last_request_time = filemtime(FASTPIXEL_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'endpoint.php');
        //         if (time() < $last_request_time + $this->request_wait_time) {
        //             if ($this->debug) {
        //                 FASTPIXEL_DEBUG::log('Class FASTPIXEL_Cache: Detect Endpoints: Skipping request, need to wait ' . $this->request_wait_time . ' seconds. ');
        //             }
        //             return false;
        //         }
        //     }
        //     $auth_api_key = base64_encode($api_key . ":");

        //     if (function_exists('wp_remote_post')) {
        //         $request_data = ['url' => get_home_url()];
        //         $request_headers = [
        //             'Content-Type'  => 'application/json'
        //         ];
        //         if (defined(FASTPIXEL_USE_SK) && FASTPIXEL_USE_SK) {
        //             $request_data['siteKey'] = $auth_api_key;
        //         } else {
        //             $request_headers['Authorization'] = 'Basic ' . $auth_api_key;
        //         }
        //         $args = array(
        //             'timeout'     => 15,
        //             'sslverify'   => false,
        //             'data_format' => 'body',
        //             'headers'     => $request_headers,
        //             'body'        => wp_json_encode($request_data),
        //         );
        //         $response = wp_remote_post(FASTPIXEL_API_HOST.'/api/v1/detect_endpoints', $args);
        //         $notices = FASTPIXEL_Notices::get_instance();
        //         if (is_wp_error($response) || $response['response']['code'] != 200) {
        //             /* translators: %s is used for support link */
        //             $notices->add_flash_notice(sprintf(esc_html__('Automatic endpoint fix failed, please contact %s.', 'fastpixel-website-accelerator'), '<a href=\'https://fastpixel.io/#contact\'>' . esc_html__('support', 'fastpixel-website-accelerator') . '</a>'), 'error', true);
        //             return false;
        //         }
        //         $body = json_decode($response['body'], true);
        //         if (is_array($body) && !empty($body)) {
        //             $this->functions->update_ac_file($body[0]);
        //             $notices->add_flash_notice(esc_html__('Endpoint updated', 'fastpixel-website-accelerator'), 'success');
        //             return true;
        //         }
        //         /* translators: %s is used for support link */
        //         $notices->add_flash_notice(sprintf(esc_html__('Automatic endpoint fix failed, please contact %s.', 'fastpixel-website-accelerator'), '<a href=\'https://fastpixel.io/#contact\'>'. esc_html__('support', 'fastpixel-website-accelerator') . '</a>'), 'error', true);
        //         return false;
        //     }
        // }

        public function load_modules()
        {
            //checking if file exists before loading
            if (!class_exists('FASTPIXEL\FASTPIXEL_Module')
                && file_exists(FASTPIXEL_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'module.php')) {
                include_once FASTPIXEL_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'module.php';
            } else {
                return false;
            }
            //including modules
            if (class_exists('FASTPIXEL\FASTPIXEL_Module') && file_exists(FASTPIXEL_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'modules')) {
                if ($handle = opendir(FASTPIXEL_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'modules')) {
                    while (false !== ($entry = readdir($handle))) {
                        if (!in_array($entry, ['.', '..'])) {
                            try {
                                include_once FASTPIXEL_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . $entry;
                            } catch (\Exception $e) {
                                if ($this->debug) {
                                    FASTPIXEL_DEBUG::log('Class FASTPIXEL_Cache: load_modules, Exception message -> ', $e->getMessage());
                                }
                            }
                        }
                    }
                    closedir($handle);
                }
            }
        }

        protected function load_classes($path): void {
            //include classes
            if ($handle = opendir($path)) {
                while (false !== ($entry = readdir($handle))) {
                    if (!in_array($entry, ['.', '..'])) {
                        try {
                            include_once $path . $entry;
                        } catch (\Exception $e) {
                            if ($this->debug) {
                                FASTPIXEL_DEBUG::log('Class FASTPIXEL_Cache: load_classes, Error occure while including class -> ', $e->getMessage());
                            }
                        }
                    }
                }
                closedir($handle);
            }
        }

        public function check_redirect_canonical($redirected_url, $requested_url) {
            if ($this->debug) {
                FASTPIXEL_Debug::log('Class FASTPIXEL_Cache_files: Checking canonical redirect $redirected_url', $redirected_url);
                FASTPIXEL_Debug::log('Class FASTPIXEL_Cache_files: Checking canonical redirect $requested_url', $requested_url);
            }
            if ($redirected_url != $requested_url) {
                if (untrailingslashit($redirected_url) == $requested_url) {
                    //removing action when redirected
                    remove_action('fastpixel/shutdown', [$this, 'request_page_cache']);
                }
            }
            return $redirected_url;
        }

    }
    new FASTPIXEL_Cache();
}
