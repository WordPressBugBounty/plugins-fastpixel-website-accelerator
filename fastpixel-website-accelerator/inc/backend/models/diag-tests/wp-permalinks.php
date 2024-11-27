<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Diag_Test_Wp_Permalinks')) {
    class FASTPIXEL_Diag_Test_Wp_Permalinks extends FASTPIXEL_Diag_Test 
    {
        protected $order_id = 14;
        protected $name = 'WordPress Permalinks';

        public function __construct()
        {
            parent::__construct();
        }

        public function test() {
            if (is_multisite()) {
                $this->passed = true;
                $this->visible_on_diagnostics_page = false;
                return;
            }
            $structure = get_option('permalink_structure');
            if (empty($structure)) {
                $this->add_notification_message(esc_html__('FastPixel does not work properly because of the \'plain\' permalinks. Please choose a permalinks structure other than \'plain\'.', 'fastpixel-website-accelerator'), 'error');
                return;
            }
            $this->passed = true;
        }

        public function l10n_name()
        {
            $this->name = esc_html__('WordPress Permalinks', 'fastpixel-website-accelerator');
        }
    }
    new FASTPIXEL_Diag_Test_Wp_Permalinks();
}
