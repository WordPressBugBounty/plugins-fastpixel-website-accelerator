<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Tab_Diag')) {
    class FASTPIXEL_Tab_Diag extends FASTPIXEL_UI_Tab
    {

        protected $slug = 'diagnostics';
        protected $order = 10;
        protected $diag;

        public function __construct()
        {
            parent::__construct();
            $diag = FASTPIXEL_Diag::get_instance();
            $error_img = '';
            if ($diag->have_failed_tests()) {
                $error_img = '<img class="fastpixel-icon-diag-tab" src="' . esc_url(FASTPIXEL_PLUGIN_URL . 'icons/exclamation.png') . '" />';
            } 
            $this->name = $error_img . esc_html__('Diagnostics', 'fastpixel-website-accelerator');
            $this->diag = FASTPIXEL_Diag::get_instance();
        }

        public function settings() {}
    }
    new FASTPIXEL_Tab_Diag();
}
