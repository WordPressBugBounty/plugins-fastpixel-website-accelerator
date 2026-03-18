<?php
namespace FASTPIXEL;

use FASTPIXEL\FASTPIXEL_Notices;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Action_Fastpixel_Purge_Object_Cache')) {
    class FASTPIXEL_Action_Fastpixel_Purge_Object_Cache extends FASTPIXEL_Action_Model {

        private $notices;

        public function __construct($action_name)
        {
            parent::__construct($action_name);
            $this->notices = FASTPIXEL_Notices::get_instance();
        }

        public function do_action()
        {
            $cache_nonce = isset($_REQUEST['fastpixel_cache_nonce']) ? sanitize_text_field(wp_unslash($_REQUEST['fastpixel_cache_nonce'])) : false;
            if (!current_user_can('manage_options') || empty($cache_nonce) || !wp_verify_nonce($cache_nonce, 'fastpixel_purge_object_cache')) {
                wp_die(esc_html__('You need a higher permission level.', 'fastpixel-website-accelerator'));
            }

            $flushed = false;
            if (function_exists('wp_cache_flush')) {
                $result = wp_cache_flush();
                $flushed = ($result === null) ? true : (bool) $result;
            }

            if ($flushed) {
                $this->notices->add_flash_notice(esc_html__('Object cache cleared!', 'fastpixel-website-accelerator'), 'success', true);
            } else {
                $this->notices->add_flash_notice(esc_html__('Object cache could not be cleared.', 'fastpixel-website-accelerator'), 'error', true);
            }

            $this->add_redirect(admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN . '-settings#object-cache'));
        }
    }
}
