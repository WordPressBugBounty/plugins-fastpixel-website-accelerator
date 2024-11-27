<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Diag_Test_Rest_Endpoints')) {
    class FASTPIXEL_Diag_Test_Rest_Endpoints extends FASTPIXEL_Diag_Test
    {
        protected $order_id = 17;
        protected $name = 'Postback Endpoint';
        protected $rest_url = '';
        protected $functions;

        public function __construct()
        {
            parent::__construct();
            $this->functions = FASTPIXEL_Functions::get_instance();
        }

        public function test() {
            global $pagenow;
            //doing request only on diagnostics page
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- wordpress admin page is accessed without any nonces, no data is posted.
            $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : false; //phpcs:ignore
            if ($pagenow == 'admin.php' && $page && $page == FASTPIXEL_TEXTDOMAIN . '-settings') {
                if (defined('FASTPIXEL_REST_URL')) {
                    $this->rest_url = FASTPIXEL_REST_URL;
                } else if (function_exists('get_rest_url')) {
                    $this->rest_url = get_rest_url(get_current_blog_id(), FASTPIXEL_TEXTDOMAIN . '/v1/update');
                }
                FASTPIXEL_DEBUG::log('Endpoint test: doing self request using WP_REMOTE_POST');
                if (function_exists('wp_remote_post') && function_exists('get_option')) {
                    $api_key = $this->functions->get_option('fastpixel_api_key');
                    if (empty($api_key)) {
                        $this->passed = false;
                        return false;
                    }
                    $auth_api_key = base64_encode($api_key . ":");
                    //doing self api request
                    $request_data = [];
                    $request_headers = [
                        'Content-Type'  => 'application/json'
                    ];
                    if (defined('FASTPIXEL_USE_SK') && FASTPIXEL_USE_SK) {
                        $request_data['siteKey'] = $auth_api_key;
                    } else {
                        $request_headers['Authorization'] = 'Basic ' . $auth_api_key;
                    }
                    $args = array(
                        'timeout'     => 3,
                        'sslverify'   => false,
                        'data_format' => 'body',
                        'headers'     => $request_headers,
                        'body'        => wp_json_encode($request_data),
                    );
                    $response = wp_remote_post($this->rest_url, $args);
                    if (is_wp_error($response)) {
                        FASTPIXEL_DEBUG::log('Endpoint test: error message', $response->get_error_message());
                        if (($response->get_error_code() == 400) && $response->get_error_message() == 'url parameter is missing') {
                            $this->passed = true;
                            return;
                        }
                        $this->passed = false;
                        return;
                    } else {
                        $body = json_decode($response['body'], true);
                        FASTPIXEL_DEBUG::log('Endpoint test response body', $body);
                        if (isset($body['status']) && $body['status'] == 400 && isset($body['body_response']) && $body['body_response'] == 'url parameter is missing') {
                            $this->passed = true;
                            return;
                        }
                    }
                } else {
                    FASTPIXEL_DEBUG::log('Endpoint test: self request stopped');
                }
            } else {
                //setting to true when page is not diagnostics
                $this->passed = true;
            }
        }

        public function get_display() {
            $api_key = $this->functions->get_option('fastpixel_api_key');
            if ($this->passed == false && !empty($api_key)) {
                $link = wp_nonce_url(admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN . '-settings&fastpixel_diag_action=autofixendpoint'), 'fastpixel-autofixendpoint', 'fastpixel-nonce');
                /* translators: %1$s is REST URL and %2$s is action URL (displayed as button) */
                return sprintf(esc_html__('Rest URL is not available, %s to fix it automatically.', 'fastpixel-website-accelerator'), sprintf('<a class="button" href="%1$s">' . esc_html__('Click Here', 'fastpixel-website-accelerator') . '</a>', esc_url($link)));
            } else if (empty($api_key)) {
                return esc_html__('Rest url is not available because API Key is empty.', 'fastpixel-website-accelerator');
            }
            return true; 
        }

        public function l10n_name()
        {
            if (defined('FASTPIXEL_REST_URL')) {
                $this->rest_url = FASTPIXEL_REST_URL;
            } else if (function_exists('get_rest_url') && function_exists('using_index_permalinks')) {
                $this->rest_url = get_rest_url(get_current_blog_id(), FASTPIXEL_TEXTDOMAIN . '/v1/update');
            }
            /* translators: %s is used to display rest api endpoint url, nothing to translate */
            $this->name = sprintf(esc_html__('Postback Endpoint: %s', 'fastpixel-website-accelerator'), sprintf('<br/> <b>%1$s</b>', esc_url($this->rest_url)));
        }
    }
    //temporary disabled test
    // new FASTPIXEL_Diag_Test_Rest_Endpoints();
}
