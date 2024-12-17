<?php
namespace FASTPIXEL;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Rest_Api')) {
    class FASTPIXEL_Rest_Api
    {
        protected $debug = false;
        public $functions;
        public static $instance;

        public function __construct()
        {
            self::$instance = $this;
            $this->functions = FASTPIXEL_functions::get_instance();
            register_rest_route(FASTPIXEL_TEXTDOMAIN . '/v1', '/update', 
                array(
                    'methods'             => 'POST',
                    'callback'            => [$this, 'check_request'],
                    'permission_callback' => '__return_true',
                )
            );
            register_rest_route(FASTPIXEL_TEXTDOMAIN . '/v1', '/version', 
                array(
                    'methods'             => 'GET',
                    'callback'            => [$this, 'version'],
                    'permission_callback' => '__return_true',
                )
            );
            register_rest_route(
                FASTPIXEL_TEXTDOMAIN . '/v1', '/gzip',
                array(
                    'methods'             => 'POST',
                    'callback'            => [$this, 'check_gzip'],
                    'permission_callback' => '__return_true',
                )
            );
            register_rest_route(
                FASTPIXEL_TEXTDOMAIN . '/v1',
                '/diag',
                array(
                    'methods'             => 'POST',
                    'callback'            => [$this, 'diag'],
                    'permission_callback' => '__return_true',
                )
            );
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Rest_Api();
            }
            return self::$instance;
        }

        protected function save_files($parameters) // $url, $html, $headers, $css
        {
            //exclusion check moved here to have less duplicate code
            $cache_dir = $this->functions->get_cache_dir();
            $url = new FASTPIXEL_Url($parameters['url']);
            $is_exclusion = apply_filters('fastpixel/rest-api/excluded', false, $url);
            if ($is_exclusion) {
                //trying to delete existing files if page is exclusion and for some reason cached page exists
                $this->functions->delete_cached_files($cache_dir . DIRECTORY_SEPARATOR . $url->get_url_path());
                if ($this->debug) {
                    FASTPIXEL_Debug::log('WP REST API RESPONSE 400: page is excluded from cache');
                }
                return new WP_REST_Response(['status' => 400, 'response' => 'Bad Request', 'body_response' => 'Page is excluded from cache'], 400);
            }

            $path = $this->functions->check_path($parameters['url']);
            if (!$path) {
                if ($this->debug) {
                    FASTPIXEL_Debug::log('REST API: skipping files save because no path returned');
                }
                return false;
            }
            $modified_time = time(); // Make sure modified time is consistent.

            if (strpos($path, '/__fastpixel') !== false) {
                if ($this->debug) {
                    FASTPIXEL_Debug::log('REST API: skipping files save for /__fastpixel/ path');
                }
                return true;
            }

            //initializing filesystem
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . '/wp-admin/includes/file.php';
                WP_Filesystem();
            }
            // Save the response body.
            if (!$wp_filesystem->put_contents($path . DIRECTORY_SEPARATOR . 'index.html', $parameters['html'])) {
                if ($this->debug) {
                    FASTPIXEL_Debug::log('REST API: error occured while putting HTML file content to disk');
                }
            }

            // Save the resonse headers.
            if (!$wp_filesystem->put_contents($path . DIRECTORY_SEPARATOR . 'headers.json', wp_json_encode(isset($parameters['headers']) ? $parameters['headers'] : null))){
                if ($this->debug) {
                    FASTPIXEL_Debug::log('REST API: error occured while putting headers file content to disk');
                }
            }
            if (isset($parameters['css']) && !empty($parameters['css'])) {
                if (!$wp_filesystem->put_contents($path . DIRECTORY_SEPARATOR . 'style.css', $parameters['css'])) {
                    if ($this->debug) {
                        FASTPIXEL_Debug::log('REST API: error occured while putting css file content to disk');
                    }
                }
            }

            $wp_filesystem->touch($path . DIRECTORY_SEPARATOR . 'index.html', $modified_time);
            //need to remove error if it was stored
            $this->functions->error_file($parameters['url'], 'delete');

            //need to update request time if it was deleted previously to display page as cached, instead page will be displayed as stale
            $meta_file = $cache_dir . DIRECTORY_SEPARATOR . $url->get_url_path() . DIRECTORY_SEPARATOR . 'meta';
            if (!file_exists($meta_file)) {
                //adding meta info
                $meta = ['invalidated_time' => false, 'cache_request_time' => $modified_time - 1];
                if (!$wp_filesystem->put_contents($meta_file, wp_json_encode($meta))) {
                    if ($this->debug) {
                        FASTPIXEL_Debug::log('REST API: error occured while saving meta file');
                    }
                }
            }

            do_action('fastpixel/cachefiles/saved', $parameters['url']);
            return true;
        }

        public function check_request(WP_REST_Request $request)
        {
            if ($this->debug) {
                FASTPIXEL_DEBUG::log('WP REST API Request Received');
            }
            //getting request params first, added support for old WP
            if ((method_exists($request, 'is_json_content_type') && !$request->is_json_content_type()) ||
                $request->get_header('content_type') != 'application/json') {
                return new WP_REST_Response(['status' => 415, 'response' => 'Unsupported Media Type', 'body_response' => 'Unsupported Media Type'], 415);
            }
            $parameters = $request->get_json_params();
            //checking for authorization key
            if ((!isset($parameters['siteKey']) || empty($parameters['siteKey'])) && (empty($request->get_header('authorization')))) {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('WP REST API RESPONSE 401');
                }
                return new WP_REST_Response(['status' => 401, 'response' => 'Not Authorized'], 401);
            } 
            //checking for posted authorization sitekey first, then checking authorization headers
            if (isset($parameters['siteKey']) && !empty($parameters['siteKey'])) {
                $site_key_decoded = base64_decode($parameters['siteKey']);
                $request_key = substr($site_key_decoded, 0, strlen($site_key_decoded) - 1);
            } else { 
                //using headers to retrieve authorization info
                $auth_header = $request->get_header('authorization');
                if (!empty($auth_header)) {
                    $request_key = rtrim(base64_decode(preg_replace('/Basic\s+/i', '', $auth_header)), ':');
                }
            }
            //getting api key
            $api_key = $this->functions->get_option('fastpixel_api_key');
            if ($request_key !== $api_key) {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('WP REST API RESPONSE 401');
                }
                return new WP_REST_Response(['status' => 401, 'response' => 'Not Authorized'], 403);
            }
            if (empty($parameters['url'])) {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('WP REST API RESPONSE 400: url parameter is missing');
                }
                return new WP_REST_Response(['status' => 400, 'response' => 'Bad Request', 'body_response' => 'url parameter is missing'], 400);
            }
            try {
                $url = wp_parse_url($parameters['url']);
                if (empty($url['scheme'])) {
                    throw new Exception("No scheme");
                }
                // TODO: temporarily disabled host check
                // if ($url['host'] !== $_SERVER['HTTP_HOST']) {
                //     throw new Exception("Invalid host");
                // }
            } catch (Exception $e) {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('WP REST API RESPONSE 400: invalid url parameter');
                }
                return new WP_REST_Response(['status' => 400, 'response' => 'Bad Request', 'body_response' => 'invalid url parameter: ' . $e->getMessage()], 400);
            }
            //checking for error and writing error file if exists
            if (isset($parameters['error']) && !empty($parameters['error'])) {
                $this->functions->error_file($parameters['url'], 'add', ['error' => $parameters['error']]);
                return new WP_REST_Response(['status' => 200, 'response' => 'ok', 'body_response' => 'Error saved'], 200);
            }
            if (empty($parameters['html'])) {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('WP REST API RESPONSE 400: html parameter is missing');
                }
                return new WP_REST_Response(['status' => 400, 'response' => 'Bad Request', 'body_response' => 'html parameter is missing'], 400);
            }
            if ($this->debug) {
                FASTPIXEL_DEBUG::log('WP REST API RESPONSE 200 OK');
            }
            if ($this->save_files($parameters)) {
                return new WP_REST_Response(['status' => 'ok'], 200);
            } else {
                return new WP_REST_Response(['status' => 400, 'response' => 'Bad Request', 'body_response' => 'Request data processing failed'], 400);
            }
        }

        public function version() {
            return new WP_REST_Response(['version' => FASTPIXEL_VERSION], 200);
        }

        public function check_gzip(WP_REST_Request $request) {
            $data = $request->get_json_params();
            if (!empty($data)) {
                return new WP_REST_Response(['status' => 'ok'], 200);
            }
            return new WP_REST_Response(['status' => 400, 'response' => 'Bad Request', 'body_response' => 'Request data processing failed'], 400);
        }

        public function diag(WP_REST_Request $request) {
            //checking for authorization key
            if ((method_exists($request, 'is_json_content_type') && !$request->is_json_content_type()) ||
                $request->get_header('content_type') != 'application/json') {
                return new WP_REST_Response(['status' => 415, 'response' => 'Unsupported Media Type', 'body_response' => 'Unsupported Media Type'], 415);
            }
            $parameters = $request->get_json_params();
            //checking for authorization key
            if ((!isset($parameters['siteKey']) || empty($parameters['siteKey'])) && (empty($request->get_header('authorization')))) {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('WP REST API RESPONSE 401');
                }
                return new WP_REST_Response(['status' => 401, 'response' => 'Not Authorized'], 401);
            }
            $site_key = $request->get_param('siteKey');
            if (empty($site_key)) {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('REST DIAGNOSTICS REQUEST: RESPONSE 401');
                }
                return new WP_REST_Response(['status' => 401, 'response' => 'Not Authorized'], 401);
            }
            //checking for posted authorization sitekey first, then checking authorization headers
            $site_key_decoded = base64_decode($site_key);
            $request_key = substr($site_key_decoded, 0, strlen($site_key_decoded) - 1);
            //getting api key
            $api_key = $this->functions->get_option('fastpixel_api_key');
            if ($request_key !== $api_key) {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('REST DIAGNOSTICS REQUEST: RESPONSE 401');
                }
                return new WP_REST_Response(['status' => 401, 'response' => 'Not Authorized'], 403);
            }
            $data = [];
            //getting information
            if (function_exists('is_multisite')) {
                $data['multisite'] = is_multisite();
            } else {
                $data['multisite'] = 'Function "is_multisite" is not available';
            }
            if (function_exists('wp_get_active_and_valid_plugins')) {
                $plugins = wp_get_active_and_valid_plugins();
                foreach ($plugins as &$plugin) {
                    $plugin = basename(dirname($plugin)) . '/' . basename($plugin);
                }
                $data['active_plugins'] = $plugins;
            } else {
                $data['active_plugins'] = 'Function "wp_get_active_and_valid_plugins" not exists';
            }
            if (class_exists('FASTPIXEL\FASTPIXEL_Diag')) {
                $diag = FASTPIXEL_Diag::get_instance();
                $diag->run_tests();
                $tests = $diag->get_tests_results();
                foreach($tests as &$test) {
                    foreach($test as $key => $value) {
                        if (!in_array($key, ['name', 'status'])) {
                            unset($test[$key]);
                        }
                    }
                }
                $data['tests'] = $tests;
            }
            return new WP_REST_Response($data, 200);
        }
    }
    new FASTPIXEL_Rest_Api();
}
