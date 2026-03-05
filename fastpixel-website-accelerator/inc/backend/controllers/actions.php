<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Backend_Actions')) {
    class FASTPIXEL_Backend_Actions extends FASTPIXEL_Backend_Controller
    {
        private $action;
        public function __construct()
        {
            parent::__construct();
            //checking if any fastpixel action requested
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- wordpress page is accessed without any nonces, no data is posted.
            $this->action = isset($_REQUEST['fastpixel-action']) ? sanitize_key(wp_unslash($_REQUEST['fastpixel-action'])) : null;
            //setting action name to variable
            if ($this->action) {
                //adding action run at later time
                add_action('admin_init', [$this, 'run_action']);
            }
            
            // AJAX handlers for onboarding
            add_action('wp_ajax_fastpixel_request_new_key', [$this, 'ajax_request_new_key']);
            add_action('wp_ajax_fastpixel_validate_key', [$this, 'ajax_validate_key']);
            add_action('wp_ajax_fastpixel_check_domain', [$this, 'ajax_check_domain']);
        }

        public function run_action() 
        {
            //generating class name
            $class_name = 'FASTPIXEL\FASTPIXEL_Action_' . $this->action;
            //checking if class exists
            if (class_exists($class_name)) {
                //creating new class instance
                $action_class = new $class_name($this->action);
                //extra check if class have do_action method
                if (method_exists($action_class, 'do_action')) {
                    //running action
                    $action_class->do_action();
                    //getting action results
                    $status = $action_class->get_status();
                    //displaying error if set
                    if ($status['error']) {
                        $this->notices->add_flash_notice($status['message'], $status['message_type']);
                    }
                    //doing redirect if set
                    if ($status['do_redirect']) {
                        $this->do_redirect($status['redirect_to']);
                    }
                }
            }
        }

        /**
         * AJAX handler for requesting new API key (free signup)
         */
        public function ajax_request_new_key()
        {
            // Enable error logging for debugging
            $debug_mode = defined('FASTPIXEL_DEBUG') && FASTPIXEL_DEBUG;
            $debug_info = array();

            // check nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'fastpixel-onboarding')) {
                wp_send_json_error(['message' => esc_html__('Security check failed. Please try again.', 'fastpixel-website-accelerator')]);
                return;
            }

            // get email
            $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
            $debug_info['received_email'] = $email;
            
            // validate email
            if (empty($email) || !is_email($email)) {
                wp_send_json_error(['message' => esc_html__('Please provide a valid e-mail address.', 'fastpixel-website-accelerator')]);
                return;
            }

            // Terms of Service
            if (!isset($_POST['tos']) || $_POST['tos'] != '1') {
                wp_send_json_error(['message' => esc_html__('You must agree to the Terms of Service and Privacy Policy.', 'fastpixel-website-accelerator')]);
                return;
            }

            // prepare multipart form data for signup
            $dashboard_host = defined('FASTPIXEL_DASHBOARD_HOST') ? FASTPIXEL_DASHBOARD_HOST : 'https://dash.fastpixel.io';
            $signup_url = $dashboard_host . '/free-sign-up-plugin';
            $debug_info['signup_url'] = $signup_url;

            // Use same boundary format as browser
            $boundary = '----WebKitFormBoundary' . wp_generate_password(16, false);
            $body = '';
            
            // Add submit field
            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Disposition: form-data; name="submit"' . "\r\n\r\n";
            $body .= 'submit' . "\r\n";
            
            // Add agreement field
            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Disposition: form-data; name="agreement"' . "\r\n\r\n";
            $body .= '1' . "\r\n";
            
            // Add email field
            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Disposition: form-data; name="email"' . "\r\n\r\n";
            $body .= $email . "\r\n";
            
            // Close boundary
            $body .= '--' . $boundary . '--' . "\r\n";

            $debug_info['form_data'] = array(
                'submit' => 'submit',
                'agreement' => '1',
                'email' => $email
            );
            $debug_info['multipart_body_length'] = strlen($body);

            // Use wp_remote_post for proper WordPress compatibility
            $response = wp_remote_post($signup_url, array(
                'headers' => array(
                    'Accept' => 'application/json',
                    'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
                    'Content-Length' => strlen($body),
                    // Use same origin/referer as dashboard expects (dev.fastpixel.io)
                    'Origin' => 'https://dev.fastpixel.io',
                    'Referer' => 'https://dev.fastpixel.io/free-sign-up-plugin',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'
                ),
                'body' => $body,
                'timeout' => 30,
                'sslverify' => true,
                'redirection' => 5
            ));

            // Handle wp_remote_post errors
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $debug_info['wp_error'] = $error_message;
                $debug_info['wp_error_code'] = $response->get_error_code();
                
                if ($debug_mode) {
                    FASTPIXEL_Debug::log('Free signup wp_remote_post error', $error_message);
                    FASTPIXEL_Debug::log('Free signup debug info', $debug_info);
                }
                wp_send_json_error([
                    'message' => esc_html__('Connection error. Please try again later.', 'fastpixel-website-accelerator'),
                    'debug' => $debug_mode ? $debug_info : null
                ]);
                return;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $debug_info['response_code'] = $response_code;
            $debug_info['response_body'] = $response_body;

            // Parse response
            $response_data = json_decode($response_body, true);
            $debug_info['response_data'] = $response_data;

            // Log response for debugging
            if ($debug_mode) {
                FASTPIXEL_Debug::log('Free signup response code', $response_code);
                FASTPIXEL_Debug::log('Free signup response body', $response_body);
                FASTPIXEL_Debug::log('Free signup response data', $response_data);
                FASTPIXEL_Debug::log('Free signup full debug info', $debug_info);
            }

            // Check for new format: Status="success" and Details contains API key
            // Or old format: success=true and APIKEY contains API key
            $is_success = false;
            $api_key_value = '';
            
            if ($response_code === 200) {
                // New format from /free-sign-up-plugin
                if (isset($response_data['Status']) && $response_data['Status'] === 'success' && !empty($response_data['Details'])) {
                    $is_success = true;
                    $api_key_value = $response_data['Details'];
                }
                // Old format (for backward compatibility)
                elseif (isset($response_data['success']) && $response_data['success'] === true && !empty($response_data['APIKEY'])) {
                    $is_success = true;
                    $api_key_value = $response_data['APIKEY'];
                }
            }
            
            if ($is_success) {
                // signup successful, save API key
                $api_key = sanitize_text_field($api_key_value);
                $api_key_model = FASTPIXEL_Api_Key::get_instance();
                $api_key_model->set_key($api_key);
                $api_key_model->save_key();
                
                // Clear skip timestamp when API key is saved
                $functions = FASTPIXEL_Functions::get_instance();
                $functions->update_option('fastpixel_skip_onboarding_timestamp', 0);

                $notices = FASTPIXEL_Notices::get_instance();
                $notices->add_flash_notice(esc_html__('Account created successfully! API Key has been saved.', 'fastpixel-website-accelerator'), 'success');

                // return success with redirect URL
                $redirect_url = admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN . '-settings');
                wp_send_json_success([
                    'message' => esc_html__('Account created successfully! API Key has been saved.', 'fastpixel-website-accelerator'),
                    'redirect_url' => $redirect_url
                ]);
            } else {
                // signup failed - detect specific error types
                $error_message = esc_html__('Signup failed. Please try again.', 'fastpixel-website-accelerator');
                
                // Check for error message in various response formats
                if (isset($response_data['message']) && !empty($response_data['message'])) {
                    $error_message = $response_data['message'];
                } elseif (isset($response_data['error']) && !empty($response_data['error'])) {
                    $error_message = $response_data['error'];
                } elseif (isset($response_data['Status']) && $response_data['Status'] !== 'success' && !empty($response_data['Status'])) {
                    $error_message = $response_data['Status'];
                }
                
                // Check if response indicates email already exists
                $response_text = strtolower($response_body);
                $error_text = strtolower($error_message);
                $email_exists_keywords = array('already exists', 'already registered', 'email already', 'duplicate email', 'account exists', 'user exists', 'email is already');
                
                foreach ($email_exists_keywords as $keyword) {
                    if (strpos($response_text, $keyword) !== false || strpos($error_text, $keyword) !== false) {
                        $error_message = esc_html__('An account with this email address already exists. Please use a different email or log in with your existing account.', 'fastpixel-website-accelerator');
                        break;
                    }
                }
                
                // Include ALL debug info in response for troubleshooting (always, not just in debug mode)
                $error_response = [
                    'message' => $error_message,
                    'response_code' => $response_code,
                    'response_body' => $response_body,
                    'response_data' => $response_data,
                    'signup_url' => $signup_url,
                    'form_data_sent' => $debug_info['form_data'],
                    'multipart_body_preview' => substr($body, 0, 500) . '...', // First 500 chars of multipart body
                ];
                
                // Add wp_error info
                if (isset($debug_info['wp_error'])) {
                    $error_response['wp_error'] = $debug_info['wp_error'];
                    $error_response['wp_error_code'] = $debug_info['wp_error_code'];
                }
                
                if ($debug_mode) {
                    $error_response['debug_info'] = $debug_info;
                }
                
                wp_send_json_error($error_response);
            }
        }

        /**
         * AJAX handler for validating/saving existing API key
         */
        public function ajax_validate_key()
        {
            // check nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'fastpixel-onboarding')) {
                wp_send_json_error(['message' => esc_html__('Security check failed. Please try again.', 'fastpixel-website-accelerator')]);
                return;
            }

            // get API key
            $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
            
            // Validate API key
            $validation_result = FASTPIXEL_Action_Validate_Key::validate_api_key($api_key);

            if (!$validation_result['valid']) {
                wp_send_json_error([
                    'message' => $validation_result['error']
                ]);
                return;
            }

            // save API key
            $api_key_model = FASTPIXEL_Api_Key::get_instance();
            $api_key_model->set_key($api_key);
            $api_key_model->save_key();
            
            // Clear skip timestamp when API key is saved
            $functions = FASTPIXEL_Functions::get_instance();
            $functions->update_option('fastpixel_skip_onboarding_timestamp', 0);

            $notices = FASTPIXEL_Notices::get_instance();
            $notices->add_flash_notice(esc_html__('API Key validated and saved successfully!', 'fastpixel-website-accelerator'), 'success');

            // return success with redirect URL
            $redirect_url = admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN . '-settings');
            wp_send_json_success([
                'message' => esc_html__('API Key validated and saved successfully!', 'fastpixel-website-accelerator'),
                'redirect_url' => $redirect_url,
                'user' => $validation_result['user']
            ]);
        }

        /**
         * AJAX handler for checking if the current domain is already associated with a FastPixel account
         */
        public function ajax_check_domain()
        {
            // check nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'fastpixel-onboarding')) {
                wp_send_json_error(['message' => esc_html__('Security check failed. Please try again.', 'fastpixel-website-accelerator')]);
                return;
            }

            // Determine current site domain (host only)
            $home_url = home_url();
            $parsed   = wp_parse_url($home_url);
            $domain   = isset($parsed['host']) ? $parsed['host'] : '';

            if (empty($domain)) {
                wp_send_json_error(['message' => esc_html__('Could not determine site domain.', 'fastpixel-website-accelerator')]);
                return;
            }

            /**
             * Filter the domain used for FastPixel domain association check.
             *
             * @param string $domain The detected domain.
             */
            $domain = apply_filters('fastpixel/onboarding/domain_check_domain', $domain);

            $api_url = 'https://cdn.fastpixel.io/read-domain/' . rawurlencode($domain); //live
            $api_url = 'https://devapi.fastpixel.io/read-domain/' . rawurlencode($domain); //dev

            /**
             * Filter the API URL used for FastPixel domain association check.
             *
             * @param string $api_url The API URL.
             * @param string $domain  The domain being checked.
             */
            $api_url = apply_filters('fastpixel/onboarding/domain_check_url', $api_url, $domain);

            $response = wp_remote_get(
                $api_url,
                [
                    'timeout' => 10,
                ]
            );

            if (is_wp_error($response)) {
                wp_send_json_error([
                    'message' => esc_html__('Could not contact FastPixel domain service.', 'fastpixel-website-accelerator'),
                ]);
                return;
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if ($code < 200 || $code >= 300 || empty($body)) {
                wp_send_json_error([
                    'message' => esc_html__('Unexpected response from FastPixel domain service.', 'fastpixel-website-accelerator'),
                ]);
                return;
            }

            $data = json_decode($body, true);

            if (!is_array($data)) {
                wp_send_json_error([
                    'message' => esc_html__('Invalid response from FastPixel domain service.', 'fastpixel-website-accelerator'),
                ]);
                return;
            }

            $has_account = isset($data['HasAccount']) ? (bool) $data['HasAccount'] : false;
            $email       = isset($data['Email']) ? sanitize_email($data['Email']) : '';
            $status      = isset($data['Status']) ? $data['Status'] : null;
            $unlimited   = isset($data['Unlimited']) ? (bool) $data['Unlimited'] : false;
            $api_domain  = isset($data['Domain']) ? sanitize_text_field($data['Domain']) : $domain;

            wp_send_json_success([
                'has_account' => $has_account,
                'email'       => $email,
                'status'      => $status,
                'unlimited'   => $unlimited,
                'domain'      => $api_domain,
                'raw'         => $data,
            ]);
        }
    }
    new FASTPIXEL_Backend_Actions();
}
