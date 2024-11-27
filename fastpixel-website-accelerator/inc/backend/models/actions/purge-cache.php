<?php
namespace FASTPIXEL;

use FASTPIXEL\FASTPIXEL_Backend_Cache;
use FASTPIXEL\FASTPIXEL_Notices;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Action_Fastpixel_Purge_Cache')) {
    class FASTPIXEL_Action_Fastpixel_Purge_Cache extends FASTPIXEL_Action_Model {

        private $backend_cache;
        private $notices;

        public function __construct($action_name) 
        {
            parent::__construct($action_name);
            $this->backend_cache = FASTPIXEL_Backend_Cache::get_instance();
            $this->notices = FASTPIXEL_Notices::get_instance();
        }
        public function do_action()
        {
            $cache_nonce = false;
            if (isset($_REQUEST['fastpixel_cache_nonce'])) {
                $cache_nonce = sanitize_text_field($_REQUEST['fastpixel_cache_nonce']);
            }
            if (!current_user_can('manage_options') || empty($cache_nonce) || !wp_verify_nonce($cache_nonce, 'fastpixel_purge_cache')) {
                wp_die(esc_html__('You need a higher permission level.', 'fastpixel-website-accelerator'));
            }
            if ($this->backend_cache->purge_all()) {
                $this->notices->add_flash_notice(esc_html__('Cache cleared!', 'fastpixel-website-accelerator'), 'success', true);
            }
            $this->add_redirect(wp_get_referer()); 
        }
    }
}
