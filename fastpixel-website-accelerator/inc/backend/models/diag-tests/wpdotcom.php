<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Diag_Test_WP_Dot_Com')) {
    class FASTPIXEL_Diag_Test_WP_Dot_Com extends FASTPIXEL_Diag_Test 
    {
        protected $order_id = 99;
        protected $name = 'Batcache';
        protected $activation_check = false;
        protected $display_notifications = true;
        protected $visible_on_diagnostics_page = false;


        public function __construct() {
            parent::__construct();
        }

        public function test() {
            if (file_exists(WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'advanced-cache.php')) {
                $ac_content = file_get_contents(WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'advanced-cache.php');
                if (class_exists('batcache') || preg_match('/class\s+batcache/s', $ac_content)) {
                    $this->add_notification_message(sprintf(esc_html__('Seems that FastPixel is not compatible with current environment.', 'fastpixel-website-accelerator')), 'error');
                    return;
                } 
            } 
            $this->passed = true;
        }

        public function activation_test() {
            if ((!file_exists(WPMU_PLUGIN_DIR . DIRECTORY_SEPARATOR . '0fastpixel.php') || 
                strlen(file_get_contents(WPMU_PLUGIN_DIR . DIRECTORY_SEPARATOR . '0fastpixel.php')) == 0) &&
                class_exists('batcache')) { //for wordpress.com
                return false;
            }
            return true;
        }

        public function l10n_name()
        {
            $this->name = esc_html__('Batcache', 'fastpixel-website-accelerator');
        }
    }
    new FASTPIXEL_Diag_Test_WP_Dot_Com();
}
