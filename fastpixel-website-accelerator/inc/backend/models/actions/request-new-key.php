<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Action_Request_New_Key')) {
    class FASTPIXEL_Action_Request_New_Key extends FASTPIXEL_Action_Model {

        public function __construct($action_name) 
        {
            parent::__construct($action_name);
        }

        public function do_action()
        {
            // check nonce
            if (!isset($_POST['fastpixel-nonce']) || !wp_verify_nonce(sanitize_key($_POST['fastpixel-nonce']), 'fastpixel-onboarding')) {
                $this->add_error(esc_html__('Security check failed. Please try again.', 'fastpixel-website-accelerator'), 'error');
                return;
            }

            // get email
            $email = isset($_POST['pluginemail']) ? sanitize_email($_POST['pluginemail']) : '';
            
            // validate email
            if (empty($email) || !is_email($email)) {
                $this->add_error(esc_html__('Please provide a valid e-mail address.', 'fastpixel-website-accelerator'), 'error');
                return;
            }

            // Terms of Service
            if (!isset($_POST['tos']) || $_POST['tos'] != '1') {
                $this->add_error(esc_html__('You must agree to the Terms of Service and Privacy Policy.', 'fastpixel-website-accelerator'), 'error');
                return;
            }

            // prepare multipart form data for signup
            $dashboard_host = defined('FASTPIXEL_DASHBOARD_HOST') ? FASTPIXEL_DASHBOARD_HOST : 'https://dash.fastpixel.io';
            $signup_url = $dashboard_host . '/free-sign-up-plugin';

            // build multipart form data
            $boundary = wp_generate_password(20, false);
            $body = '';
            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Disposition: form-data; name="submit"' . "\r\n\r\n";
            $body .= 'submit' . "\r\n";

            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Disposition: form-data; name="agreement"' . "\r\n\r\n";
            $body .= '1' . "\r\n";

            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Disposition: form-data; name="email"' . "\r\n\r\n";
            $body .= $email . "\r\n";

            $body .= '--' . $boundary . '--';

            // make request to FastPixel signup endpoint
            $response = wp_remote_post($signup_url, array(
                'headers' => array(
                    'Accept' => 'application/json',
                    'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
                ),
                'body' => $body,
                'timeout' => 30,
                'sslverify' => true
            ));

            if (is_wp_error($response)) {
                $this->add_error(esc_html__('Connection error. Please try again later.', 'fastpixel-website-accelerator'), 'error');
                return;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);

            // Check for format: Status="success" and Details contains API key
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

                $notices = FASTPIXEL_Notices::get_instance();
                $notices->add_flash_notice(esc_html__('Account created successfully! API Key has been saved.', 'fastpixel-website-accelerator'), 'success');

                // redirect to settings page - clean URL without any action parameters
                $redirect_url = admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN . '-settings');
                $redirect_url = remove_query_arg(['noheader', 'fastpixel-action'], $redirect_url);
                $this->add_redirect($redirect_url);
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
                
                $this->add_error($error_message, 'error');
            }
        }
    }
}
