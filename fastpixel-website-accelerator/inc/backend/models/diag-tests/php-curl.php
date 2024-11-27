<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Diag_Test_Php_Curl')) {
    class FASTPIXEL_Diag_Test_Php_Curl extends FASTPIXEL_Diag_Test
    {
        protected $order_id = 11;
        protected $name = 'PHP cURL';

        public function __construct()
        {
            parent::__construct();
        }

        public function test() {
            if (function_exists('curl_init')) {
                if (function_exists('curl_version')) {
                    $version = curl_version();
                    if ($version['version'] >= '7.10.0') {
                        $this->passed = true;
                        return;
                    }
                }
            } else {
                $this->add_notification_message(esc_html__('FastPixel does not work because cURL is disabled. Please enable cURL.', 'fastpixel-website-accelerator'), 'error');
            }
        }

        public function l10n_name()
        {
            $this->name = esc_html__('PHP cURL', 'fastpixel-website-accelerator');
        }
    }
    new FASTPIXEL_Diag_Test_Php_Curl();
}
