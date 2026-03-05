<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Tab_Compatibility')) {
    class FASTPIXEL_Tab_Compatibility extends FASTPIXEL_UI_Tab
    {
        protected $name = 'Compatibility';
        protected $slug = 'compatibility';
        protected $order = 8;
        protected $enabled = false;

        public function __construct() {
            parent::__construct();
            $this->name = esc_html__('Compatibility', 'fastpixel-website-accelerator');
            $this->enabled = apply_filters('fastpixel/compatibility_tab/enabled', false);
            $this->save_options();
        }

        public function settings() {
            if (!$this->check_capabilities()) {
                return;
            }

            // Registering "Compatibility" settings.
            do_action('fastpixel/compatibility_tab/init_settings');
        }

        protected function save_options() {
            if (!$this->validate_settings_save_request()) {
                return false;
            }
            do_action('fastpixel/compatibility_tab/save_options');
        }
    }
    new FASTPIXEL_Tab_Compatibility();
}


