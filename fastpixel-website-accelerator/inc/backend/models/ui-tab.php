<?php
namespace FASTPIXEL;

if (!class_exists('FASTPIXEL\FASTPIXEL_UI_Tab')) {
    abstract class FASTPIXEL_UI_Tab
    {
        protected $enabled = true;
        protected $order = 0;
        protected $name;
        protected $slug;
        protected $functions;
        protected $be_functions;

        public function __construct() {
            $this->functions = FASTPIXEL_Functions::get_instance();
            $this->be_functions = FASTPIXEL_Backend_Functions::get_instance();
            //registering tab settings
            $this->settings();
            //adding tab to UI
            $ui = FASTPIXEL_UI::get_instance();
            $ui->add_tab($this);
        }

        public function enable() {
            $this->enabled = true;
        }
        
        public function disable() {
            $this->enabled = false;
        }

        public function is_enabled() {
            return $this->enabled;
        }

        public function get_order() {
            return $this->order;
        }
        public function get_name() {
            return $this->name;
        }

        public function get_slug() {
            return $this->slug;
        }

        public function get_link() {
            if (!is_multisite()) {
                return esc_url(admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN . '-settings#' . $this->slug));
            } else {
                return esc_url(network_admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN . '-settings#' . $this->slug));
            }
        }

        abstract public function settings();

        public function view() {
            $slug = str_replace('_', '-', $this->slug);
            if (file_exists(FASTPIXEL_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . $slug . '.php')) {
                include_once FASTPIXEL_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . $slug . '.php';
            }
        }

        protected function check_capabilities()
        {
            // check user capabilities
            if (!current_user_can('manage_options')) {
                return false;
            }
            return true;
        }

        /**
         * Validates settings save request
         * Checks POST method, AJAX, onboarding actions, action type, and nonce
         * 
         * @param bool $require_save_action Whether to require 'save_settings' action (default: true)
         * @return bool True if validation passes, false otherwise
         */
        protected function validate_settings_save_request($require_save_action = true)
        {
            if (sanitize_text_field($_SERVER['REQUEST_METHOD']) !== 'POST' || (defined('DOING_AJAX') && DOING_AJAX)) {
                return false;
            }
            // skip nonce check for onboarding actions - check this BEFORE any nonce verification
            $action = isset($_POST['fastpixel-action']) ? sanitize_key($_POST['fastpixel-action']) : '';
            if (in_array($action, ['request_new_key', 'validate_key'])) {
                return false;
            }
            // check if this is a settings save action (if required)
            if ($require_save_action) {
                if (empty($_POST['fastpixel-action']) || sanitize_key($_POST['fastpixel-action']) != 'save_settings') {
                    return false;
                }
            }
            // use wp_verify_nonce instead of check_admin_referer to avoid wp_die() on failure
            if (!isset($_POST['fastpixel-nonce']) || !wp_verify_nonce(sanitize_key($_POST['fastpixel-nonce']), 'fastpixel-settings')) {
                return false;
            }
            return true;
        }
    }
}
