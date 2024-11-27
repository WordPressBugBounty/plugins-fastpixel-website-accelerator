<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Response_Handler')) {
    class FASTPIXEL_Response_Handler 
    {
        public static $instance;
        protected static $debug_response_handler = false;

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Response_Handler();
            }
            return self::$instance;
        }

        public function handle_default_api_response($http_code = false, $response_body = false, $url = false) {
            if (self::$debug_response_handler) {
                FASTPIXEL_DEBUG::log('Class Response Handler: "$http_code"', $http_code);
                FASTPIXEL_DEBUG::log('Class Response Handler: "$response_body"', $response_body);
                FASTPIXEL_DEBUG::log('Class Response Handler: "$url"', $url);
            }
            if (empty($http_code)) {
                return false;
            }
            $functions = FASTPIXEL_Functions::get_instance();
            $body = json_decode($response_body, true);
            //checking if response is OK
            if ($http_code == 200) {
                //checking response body for 'queued' status
                if (isset($body['status']) && $body['status'] == 'queued') {
                    //removing limit reached message if it was set
                    if (defined('FASTPIXEL_FREE_LIMIT_REACHED') && FASTPIXEL_FREE_LIMIT_REACHED) {
                        $functions->update_option('fastpixel_free_limit_reached', false);
                    }
                    return true;
                } else {
                    if (self::$debug_response_handler) {
                        FASTPIXEL_DEBUG::log('Default Handle Api Response: "Queued" is not present in response');
                    }
                }
            } else {
                //handling limit error
                if ($http_code == 429) {
                    $functions->update_option('fastpixel_free_limit_reached', true);
                    if (self::$debug_response_handler) {
                        FASTPIXEL_DEBUG::log('Default Handle Api Response: FREE LIMIT REACHED response recieved');
                    }
                    return false;
                }
                //handling error response, temporary disabling
                if (isset($body['error']) && !empty($body['error'])) {
                    if (!empty($url)) {
                        if ($functions->error_file($url, 'add', $body)) {
                            return false;
                        }
                    }
                    if (self::$debug_response_handler) {
                        FASTPIXEL_DEBUG::log('Default Handle Api Response: Error', $body['error']);
                    }
                }
            }
            return false;
        }
    }
}
