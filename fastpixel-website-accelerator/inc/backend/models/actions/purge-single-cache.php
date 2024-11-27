<?php
namespace FASTPIXEL;

use FASTPIXEL\FASTPIXEL_Backend_Cache;
use FASTPIXEL\FASTPIXEL_Notices;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Action_Fastpixel_Purge_Single_Cache')) {
    class FASTPIXEL_Action_Fastpixel_Purge_Single_Cache extends FASTPIXEL_Action_Model {

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
            $cache_nonce = isset($_REQUEST['fastpixel_cache_nonce']) ? sanitize_text_field($_REQUEST['fastpixel_cache_nonce']) : false;
            if (!current_user_can('manage_options') || empty($cache_nonce) || !wp_verify_nonce($cache_nonce, 'fastpixel_purge_cache')) {
                wp_die(esc_html__('You need a higher permission level.', 'fastpixel-website-accelerator'));
            }
            $purge_id = isset($_REQUEST['purge_id']) ? sanitize_text_field($_REQUEST['purge_id']) : false;
            $purge_type = isset($_REQUEST['purge_type']) ? sanitize_text_field($_REQUEST['purge_type']) : false;
            if ($purge_id == 'homepage') {
                $this->backend_cache->purge_cache_by_url(get_home_url());
            } else if (!empty($purge_type)) {
                if ($purge_type == 'taxonomy') {
                    $term = get_term($purge_id);
                    $link = get_term_link($term);
                    $this->backend_cache->purge_cache_by_url($link);
                } else if ($purge_type == 'author') {
                    $link = get_author_posts_url($purge_id);
                    $this->backend_cache->purge_cache_by_url($link);
                } else if ($purge_type == 'archive') {
                    $link = get_post_type_archive_link($purge_id);
                    $this->backend_cache->purge_cache_by_url($link);
                } else {
                    $this->backend_cache->purge_cache_by_id($purge_id);
                }
            }
            $this->add_redirect(wp_get_referer()); 
        }
    }
}
