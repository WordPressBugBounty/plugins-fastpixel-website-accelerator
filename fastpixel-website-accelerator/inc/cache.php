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
            //registering shutdown callback function
            register_shutdown_function([$this, 'on_shutdown']);

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
            add_action('fastpixel/shutdown', [$this, 'request_page_cache'], 20);
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
             * check if url have domain
             */
            if (empty($requested_url->get_host())) {
                return false;
            }
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
            if (!preg_match('/index\.php/i', $this->functions->sanitize_text_field($_SERVER['SCRIPT_NAME']))) {
                return false;
            }
            /**
            * checking for excludes, no need to serve them
            */
            $is_excluded = apply_filters('fastpixel/init/excluded', false, $requested_url);
            if ($is_excluded) {
                header('X-FastPixel-Cache: BYPASS'); //return bypass header if page is excluded
                if ($this->debug) {
                    FASTPIXEL_Debug::log('Class FASTPIXEL_Cache: excluded on init', $requested_url->get_url());
                }
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
            global $post, $wp_query;
            if (!isset($wp_query)) {
                return false; // no need to do cache request if wp_query is not set
            }
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
            //if any of the functions is not available, do not allow cache request because we can't detect if page should be cached
            // and probably wordress is not loaded properly
            if (
                !function_exists('is_404')
                || !function_exists('is_search')
                || !function_exists('post_password_required')
                || !function_exists('is_feed')
                || !function_exists('is_login')
            ) {
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
            //Step 5: Check for excluded post types
            if (is_singular()) {
                //Do not cache not published posts
                if (!empty($post->post_status) && $post->post_status != 'publish') {
                    return false;
                }
                $fastpixel_excluded_post_types = $this->functions->get_option('fastpixel_excluded_post_types', []);
                $excluded_post_types = apply_filters('fastpixel/is_cache_request_allowed/excluded/post_types', $fastpixel_excluded_post_types);
                if (!empty($post->ID) && is_numeric($post->ID)) {
                    $post_type = get_post_type($post->ID);
                    if (in_array($post_type, $excluded_post_types)) {
                        if ($this->debug) {
                            FASTPIXEL_DEBUG::log('Class FASTPIXEL_Cache: request_page_cache, Stopped, post type is excluded from cache');
                        }
                        return false;
                    }
                }
            }
            //Step 6: check for pages that should be excluded from cache by different classes
            $is_excluded = apply_filters('fastpixel/is_cache_request_allowed/excluded', false, $this->url);
            if ($is_excluded) {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('Class FASTPIXEL_Cache: Page is excluded from cache using filter.');
                }
                return false;
            }

            return true; //request allowed
        }

        public function request_page_cache()
        {
            if (!$this->is_cache_request_allowed()) {
                //trying to delete only on outside requests, when users not logged in, no need to delete if we can't determine user is logged in
                if (function_exists('is_user_logged_in') && !is_user_logged_in()) {
                    if ($this->url->params_stripped()) {
                        $original_url = new FASTPIXEL_Url($this->url->get_original_url());
                        $url_for_deletion = $original_url->get_url_path();
                    } else {
                        $url_for_deletion = $this->url->get_url_path();
                    }
                    if ($this->debug) {
                        FASTPIXEL_DEBUG::log('Class FASTPIXEL_Cache: request not allowed, trying to delete cached if exists for path', $url_for_deletion);
                    }
                    $this->functions->delete_cached_files($url_for_deletion);
                }
                return false;
            }

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
            if ($redirected_url != $requested_url) {
                //removing action when redirected
                remove_action('fastpixel/shutdown', [$this, 'request_page_cache'], 20);
            }
            return $redirected_url;
        }

    }
    new FASTPIXEL_Cache();
}
