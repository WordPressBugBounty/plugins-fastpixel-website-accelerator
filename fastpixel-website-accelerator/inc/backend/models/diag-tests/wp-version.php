<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Diag_Test_WP_Version')) {
    class FASTPIXEL_Diag_Test_WP_Version extends FASTPIXEL_Diag_Test 
    {
        protected $order_id = 15;
        protected $name = 'Wordpress version';

        public function __construct()
        {
            parent::__construct();
        }

        public function test() {
            if ( (float)get_bloginfo('version') >= (float)5) {
                $this->passed = true;
                return;
            }
        }

        public function l10n_name()
        {
            $this->name = esc_html__('WordPress Version', 'fastpixel-website-accelerator');
        }
    }
    new FASTPIXEL_Diag_Test_WP_Version();
}
