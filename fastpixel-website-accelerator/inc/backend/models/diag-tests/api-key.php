<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Diag_Test_API_KEY')) {
    class FASTPIXEL_Diag_Test_API_KEY extends FASTPIXEL_Diag_Test
    {
        protected $order_id = 21;
        protected $name = 'API KEY';
        protected $activation_check = false;
        protected $display_notifications = true;
        protected $visible_on_diagnostics_page = false;

        public function __construct()
        {
            parent::__construct();
        }

        public function test()
        {
            if (function_exists('get_option')) {
                $functions = FASTPIXEL_Functions::get_instance();
                $api_key = $functions->get_option('fastpixel_api_key', false);
                if (!$api_key) {
                    /* translators: %s is used to display contact us link, no need to translate */
                    $this->add_notification_message(sprintf(esc_html__('API Key is missing, please try reactivating plugin. Otherwise please %s to assist you.', 'fastpixel-website-accelerator'), '<a href="https://fastpixel.io/#contact">' . esc_html__('contact us', 'fastpixel-website-accelerator') . '</a>'), 'error', false);
                }
            }        
        }

        public function l10n_name()
        {
            $this->name = esc_html__('API KEY', 'fastpixel-website-accelerator');
        }
    }
    new FASTPIXEL_Diag_Test_API_KEY();
}
