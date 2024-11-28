<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Request')) {
    class FASTPIXEL_Request 
    {
        public static $instance;
        protected $debug_request = false; //if enabled, request data should be logged
        protected $display_notices = false; //if enabled, admin notices should be added, TODO: check if this option is required
        protected $functions; //variable to store functions class
        protected $notices; //variable to store notices class
        protected $connection_timeout = 5; //connection timeout variable

        protected $api_url;
        protected $api_key;
        protected $auth_key;

        protected $reset_url; //url that should be cached/reset
        protected $request_data = []; //data that is used in request
        protected $headers = [
            'Content-Type' => 'application/json'
        ];


        public function __construct() 
        {
            $this->functions = FASTPIXEL_Functions::get_instance(); //getting functions class
            //getting notices class only for admin
            if (is_admin()) {
                $this->notices   = FASTPIXEL_Notices::get_instance(); //getting notices class
            }
            
            //getting api key and encoding it
            $this->api_key  = $this->functions->get_option('fastpixel_api_key'); //api key
            $this->auth_key = base64_encode($this->api_key . ":"); //encoded api key for later use
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Request();
            }
            return self::$instance;
        }

        public function set_display_notices($display = false): void {
            $this->display_notices = $display;
        }

        protected function prepare_request_params(): void {
            $this->request_data = [
                'url'         => $this->reset_url,
                'postbackUrl' => FASTPIXEL_REST_URL,
                'settings'    => [
                    'modules' => []
                ]
            ];
            //getting javascript settings
            if (class_exists('FASTPIXEL\FASTPIXEL_Settings_Javascript')) {
                $script_settings = FASTPIXEL_Settings_Javascript::get_instance();
                $this->request_data['settings']['modules']['ScriptRewrite'] = $script_settings->get_module_settings();
            }
            //gettting images settings
            if (class_exists('FASTPIXEL\FASTPIXEL_Settings_Images')) {
                $images_settings = FASTPIXEL_Settings_Images::get_instance();
                $this->request_data['settings'] = array_merge($this->request_data['settings'], $images_settings->get_settings());
                $this->request_data['settings']['modules']['ImageRewrite'] = $images_settings->get_module_settings();
            }
            //getting fonts settings
            if (class_exists('FASTPIXEL\FASTPIXEL_Settings_Fonts')) {
                $fonts_settings = FASTPIXEL_Settings_Fonts::get_instance();
                $this->request_data['settings']['modules']['ReducedFonts'] = $fonts_settings->get_module_settings();
            }

            //adding plugin version to all requests
            $this->request_data['plugin_version'] = FASTPIXEL_VERSION;
        }

        public function cache_request($url = null, $headers = []): bool 
        {
            if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                $this->reset_url = $url;
            } else {
                if ($this->display_notices) {
                    $this->notices->add_flash_notice('Url is empty', 'error');
                }
                return false;
            }
            $this->api_url = FASTPIXEL_API_URL;
            if (!$this->validate()) {//initial validation
                return false;
            }
            if (!empty($headers)) {
                $this->headers = array_merge($this->headers, $headers);
            }
            $this->prepare_request_params(); //preparing request data
            return $this->do_request();
        }

        public function purge_all_request(): bool
        {
            $this->api_url = FASTPIXEL_API_HOST . '/api/v1/purge_all';
            if (is_multisite()) {
                $this->request_data['url'] = network_home_url();
            } else {
                $this->request_data['url'] = home_url();
            }
            return $this->do_request();
        }


        protected function do_request(): bool
        {
            //first we need to check for default wordpress CURL wrapper function and use it (required by CODEX)
            if (function_exists('wp_remote_post')) {
                if ($this->debug_request) {
                    FASTPIXEL_DEBUG::log('REQUEST Class: Starting request using wp_remote_post, $api_url', $this->api_url);
                }
                $args = array(
                    'timeout'     => $this->connection_timeout,
                    'sslverify'   => false,
                    'data_format' => 'body',
                    'headers'     => $this->headers,
                    'body'        => '', //initial body
                );
                if (defined('FASTPIXEL_USE_SK') && FASTPIXEL_USE_SK === true) {
                    if ($this->debug_request) {
                        FASTPIXEL_DEBUG::log('REQUEST Class: Using SiteKey');
                    }
                    $this->request_data['siteKey'] = $this->auth_key; //using post param
                } else {
                    $args['headers']['Authorization'] = 'Basic ' . $this->auth_key; //using default authentication method
                }
                $args['body'] = wp_json_encode($this->request_data); //adding body after adding siteKey
                if ($this->debug_request) {
                    FASTPIXEL_DEBUG::log('REQUEST Class: Request Params', $args);
                }
                $response = wp_remote_post($this->api_url, $args);
                // $response = ['response' => ['status' => 'OK']];
                if (is_wp_error($response)) {
                    if ($this->debug_request) {
                        FASTPIXEL_DEBUG::log('REQUEST Class: Response Error ', $response);
                    }
                    return false;
                }
                if ($this->debug_request) {
                    FASTPIXEL_DEBUG::log('REQUEST Class: Response', $response['response']);
                }
                //validating server response
                if (class_exists('FASTPIXEL\FASTPIXEL_Response_Handler')) { //checking for response handler class
                    $response_handler = FASTPIXEL_Response_Handler::get_instance();
                    if (!$response_handler->handle_default_api_response($response['response']['code'], $response['body'], $this->reset_url)) { 
                        return false;
                    }
                } else {
                    return false; //returning false if class not exists
                }
                return true;
            } else {
                if ($this->debug_request) {
                    FASTPIXEL_Debug::log('REQUEST Class: Error, wp_remote_post is not available', function_exists('wp_remote_post'));
                }
            }
            return false;
        }

        protected function validate(): bool 
        {
            //validating if API url is present
            if (empty($this->api_url)) {
                if ($this->display_notices) {
                    $this->notices->add_flash_notice(__('FASTPIXEL_API_URL is not defined or is empty', 'fastpixel-website-accelerator'), 'error');
                }
                if ($this->debug_request) {
                    FASTPIXEL_DEBUG::log('REQUEST Class: Error, FASTPIXEL_API_URL is not defined or is empty');
                }
                return false;
            }
            if ($this->debug_request) {
                FASTPIXEL_DEBUG::log('REQUEST Class: $api_url', $this->api_url);
            }
            //validating if API key is present
            if (empty($this->api_key)) {
                if ($this->display_notices) {
                    $this->notices->add_flash_notice(__('Empty API KEY', 'fastpixel-website-accelerator'), 'error');
                }
                if ($this->debug_request) {
                    FASTPIXEL_DEBUG::log('REQUEST Class: Error, Empty API KEY ', $this->api_key);
                }
                return false;
            }
            //validating if reset_url have http or https
            if (empty($this->reset_url) || preg_match('/https?:\/\/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/i', $this->reset_url)) {
                if ($this->display_notices) {
                    $this->notices->add_flash_notice(__('Bad Reset Url', 'fastpixel-website-accelerator'), 'error');
                }
                if ($this->debug_request) {
                    FASTPIXEL_DEBUG::log('REQUEST Class: Error, Bad Reset Url', $this->reset_url);
                }
                return false;
            } 
            // else {
            //     if (!preg_match('/http/i', $this->reset_url)) {
            //         $this->reset_url = (is_ssl() ? 'https://' : 'http://') . $this->reset_url;
            //     }
            // }
            //validating if postback url(rest api) is defined
            if (!defined('FASTPIXEL_REST_URL') || empty(FASTPIXEL_REST_URL)) {
                if ($this->display_notices) {
                    $this->notices->add_flash_notice(__('FASTPIXEL_REST_URL is not defined or is empty', 'fastpixel-website-accelerator'), 'error');
                }
                if ($this->debug_request) {
                    FASTPIXEL_DEBUG::log('REQUEST Class: Error, FASTPIXEL_REST_URL is not defined or is empty');
                }
                return false;
            }

            //checking if wpml is installed and url format is different domain
            if ($this->functions->get_option('fastpixel_skip_url_match', false) || (function_exists('is_multisite') && is_multisite())) {
                FASTPIXEL_DEBUG::log('REQUEST Class: fastpixel_skip_url_match is set, skipping url match');
                return true; 
            }

            //comparing reset url domain with rest domain
            $compare_reset_url_domain = wp_parse_url($this->reset_url, PHP_URL_HOST);
            $compare_rest_url_domain = wp_parse_url(FASTPIXEL_REST_URL, PHP_URL_HOST);
            if (!preg_match('/^' . $compare_rest_url_domain . '/i', $compare_reset_url_domain)) {
                if ($this->display_notices) {
                    $this->notices->add_flash_notice('Request url domain don\'t match postback domain, request stopped', 'error');
                }
                if ($this->debug_request) {
                    FASTPIXEL_DEBUG::log('REQUEST Class: Error, $reset_url not match FASTPIXEL_REST_URL, request stopped');
                    FASTPIXEL_DEBUG::log('REQUEST Class: Error, ' . var_export($compare_rest_url_domain, true) . ', ' . var_export($compare_reset_url_domain, true), ', ' . var_export(FASTPIXEL_REST_URL, true));
                }
                return false;
            }

            return true;
        }

        public function feedback($request_data): bool
        {
            $this->api_url = FASTPIXEL_API_HOST . '/api/v1/uninstall';
            $this->request_data = [
                'site'   => get_home_url(),
                'reason' => isset($request_data['reason']) && !empty($request_data['reason']) ? $request_data['reason'] : '',
                'details' => isset($request_data['details']) && !empty($request_data['details']) ? $request_data['details'] : '',
            ];
            if (isset($request_data['anonymous']) && is_bool($request_data['anonymous']) && $request_data['anonymous'] == false) {
                $admin = get_user_by('email', get_bloginfo('admin_email'));
                if (is_a($admin, 'WP_User')) {
                    $this->request_data['data']['user']['email'] = $admin->user_email;
                    $this->request_data['data']['user']['first_name'] = $admin->first_name;
                    $this->request_data['data']['user']['last_name'] = $admin->last_name;
                } else {
                    $this->request_data['user']['email'] = get_bloginfo('admin_email');
                }
            }
            return $this->do_request();
        }

    }
}
