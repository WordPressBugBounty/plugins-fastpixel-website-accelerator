<?php
namespace FASTPIXEL;

use Endurance_Page_Cache;
defined('ABSPATH') || exit;


if (!class_exists('FASTPIXEL\FASTPIXEL_Module_Endurance')) {
    class FASTPIXEL_Module_Endurance extends FASTPIXEL_Module 
    {

        public function __construct() {
            parent::__construct();
        }

        public function init() {
            add_action('fastpixel/settings/config/save', [$this, 'save_fastpixel_config'], 10, 1);
            //early init and api init only if module is enabled        
            if ($this->enabled) {
                $this->api_init();
                $this->early_init();
            }
        }

        public function api_init() {
            add_action('fastpixel/cachefiles/saved', [$this, 'reset_page_cache'], 10, 1);
        }

        public function early_init() {
            add_filter('epc_exempt_uri_contains', [$this, 'add_exempt_param'], 11, 1);
            add_filter('fastpixel_cache_url_before_request', [$this, 'add_nocache_to_url'], 10, 1);
        }

        public function reset_page_cache($cached_url = false) {
            if (!$cached_url || !class_exists('Endurance_Page_Cache')) {
                return;
            }
            $epc = new Endurance_Page_Cache();
            // Purge post if post_id is matched by URL
            $post_id = url_to_postid($cached_url);
            if (!$post_id) {
                $permalink = get_permalink($post_id);
                $epc->purge_request($permalink);
            } else 
            // Purge post if it is homepage and post_id wasn't matched
            if (rtrim($cached_url, '/') == rtrim(home_url(), '/')) {
                $epc->purge_request(home_url());
            }
        }

        public function purge_all()
        {
            if (!class_exists('Endurance_Page_Cache')) {
                return;
            }
            $epc = new Endurance_Page_Cache();
            $epc->purge_all();
        }

        public function save_fastpixel_config($config_instance) {
            $class_name = get_class($this);
            $enabled = $config_instance->get_option('fastpixel_enabled_modules');
            if (class_exists('Endurance_Page_Cache') && !empty($config_instance)) {
                if (!in_array($class_name, $enabled)) {
                    $enabled[] = $class_name;
                }
            } else {
                if (in_array($class_name, $enabled)) {
                    $key = array_search($class_name, $enabled);
                    unset($enabled[$key]);
                }
            }
            $config_instance->set_option('fastpixel_enabled_modules', $enabled);
        }

        public function add_nocache_to_url($input_url) {
            if (empty($input_url)) {
                return;
            }
            $url = new FASTPIXEL_Url($input_url);
            $url->add_query_param('epc_nocache');
            return $url->get_url();
        }

        public function add_exempt_param($input)
        {
            $input[] = 'epc_nocache';
            return $input;
        }
    }
    new FASTPIXEL_Module_Endurance();
}
