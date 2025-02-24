<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Tab_Integrations')) {
    class FASTPIXEL_Tab_Integrations extends FASTPIXEL_UI_Tab
    {
        protected $name = 'Integrations';
        protected $slug = 'integrations';
        protected $order = 9;
        protected $enabled = false;

        public function __construct() {
            parent::__construct();
            $this->name = esc_html__('Integrations', 'fastpixel-website-accelerator');
            $this->enabled = apply_filters('fastpixel/integrations_tab/enabled', false);
            $this->save_options();
        }

        public function settings() {
            if (!$this->check_capabilities()) {
                return;
            }

            // Registering "integrations" settings.
            do_action('fastpixel/integrations_tab/init_settings');
        }

        protected function save_options() {
            if (sanitize_text_field($_SERVER['REQUEST_METHOD']) !== 'POST' || (defined('DOING_AJAX') && DOING_AJAX) || 
                check_admin_referer('fastpixel-settings', 'fastpixel-nonce') == false ||
                empty($_POST['fastpixel-action']) || sanitize_key($_POST['fastpixel-action']) != 'save_settings') {
                return false;
            }
            do_action('fastpixel/integrations_tab/save_options');
        }
    }
    new FASTPIXEL_Tab_Integrations();
}
