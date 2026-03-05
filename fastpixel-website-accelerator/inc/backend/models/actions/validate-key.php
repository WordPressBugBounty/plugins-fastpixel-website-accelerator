<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Action_Validate_Key')) {
    class FASTPIXEL_Action_Validate_Key extends FASTPIXEL_Action_Model {

        public function __construct($action_name) 
        {
            parent::__construct($action_name);
        }

        /**
         * Validates API key against external endpoint
         * 
         * @param string $api_key The API key to validate
         * @return array Returns array with keys: 'valid' (bool), 'error' (string|null), 'user' (string|null)
         */
        public static function validate_api_key($api_key)
        {
            if (empty($api_key)) {
                return [
                    'valid' => false,
                    'error' => esc_html__('Please provide an API Key.', 'fastpixel-website-accelerator'),
                    'user' => null
                ];
            }

            $dashboard_host = defined('FASTPIXEL_DASHBOARD_HOST') ? FASTPIXEL_DASHBOARD_HOST : 'https://dash.fastpixel.io';
            $validate_url = rtrim($dashboard_host, '/') . '/api/validate-key?apikey=' . rawurlencode($api_key);

            $response = wp_remote_get(
                $validate_url,
                [
                    'timeout' => 15,
                    'sslverify' => true,
                ]
            );

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                return [
                    'valid' => false,
                    'error' => esc_html__('Could not validate API Key. Connection error: ', 'fastpixel-website-accelerator') . esc_html($error_message),
                    'user' => null
                ];
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($response_code < 200 || $response_code >= 300) {
                return [
                    'valid' => false,
                    'error' => esc_html__('Invalid response from validation service. Please try again later.', 'fastpixel-website-accelerator'),
                    'user' => null
                ];
            }

            $response_data = json_decode($response_body, true);
            
            if (!is_array($response_data)) {
                return [
                    'valid' => false,
                    'error' => esc_html__('Invalid response format from validation service. Please try again later.', 'fastpixel-website-accelerator'),
                    'user' => null
                ];
            }

            // Check validation status
            $status = isset($response_data['status']) ? (int) $response_data['status'] : 0;
            $reason = isset($response_data['reason']) ? sanitize_text_field($response_data['reason']) : '';
            $user = isset($response_data['user']) ? sanitize_text_field($response_data['user']) : '';

            if ($status !== 1) {
                $error_message = !empty($reason) ? esc_html($reason) : esc_html__('Invalid API Key. Please check your key and try again.', 'fastpixel-website-accelerator');
                return [
                    'valid' => false,
                    'error' => $error_message,
                    'user' => null
                ];
            }
            return [
                'valid' => true,
                'error' => null,
                'user' => $user
            ];
        }

        public function do_action()
        {
            // check nonce
            if (!isset($_POST['fastpixel-nonce']) || !wp_verify_nonce(sanitize_key($_POST['fastpixel-nonce']), 'fastpixel-onboarding')) {
                $this->add_error(esc_html__('Security check failed. Please try again.', 'fastpixel-website-accelerator'), 'error');
                return;
            }

            // get API key
            $api_key = isset($_POST['login_apiKey']) ? sanitize_text_field($_POST['login_apiKey']) : '';
            
            // Validate API key
            $validation_result = self::validate_api_key($api_key);

            if (!$validation_result['valid']) {
                $this->add_error($validation_result['error'], 'error');
                return;
            }

            // API key is valid - save it
            $api_key_model = FASTPIXEL_Api_Key::get_instance();
            $api_key_model->set_key($api_key);
            $api_key_model->save_key();
            
            // Clear skip timestamp when API key is saved
            $functions = FASTPIXEL_Functions::get_instance();
            $functions->update_option('fastpixel_skip_onboarding_timestamp', 0);

            $notices = FASTPIXEL_Notices::get_instance();
            $notices->add_flash_notice(esc_html__('API Key validated and saved successfully!', 'fastpixel-website-accelerator'), 'success');

            // redirect to settings page - clean URL without any action parameters
            $redirect_url = admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN . '-settings');
            $redirect_url = remove_query_arg(['noheader', 'fastpixel-action'], $redirect_url);
            $this->add_redirect($redirect_url);
        }
    }
}
