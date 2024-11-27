<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Diag_Test_Php_Version')) {
    class FASTPIXEL_Diag_Test_Php_Version extends FASTPIXEL_Diag_Test 
    {
        protected $order_id = 12;
        protected $name = 'PHP Version';

        public function __construct()
        {
            parent::__construct();
        }

        public function test() {
            if (defined('PHP_VERSION') && version_compare(PHP_VERSION, 5.6, '>')) {
                $this->passed = true;
            }
        }

        public function l10n_name()
        {
            $this->name = esc_html__('PHP Version', 'fastpixel-website-accelerator');
        }
    }
    new FASTPIXEL_Diag_Test_Php_Version();
}
