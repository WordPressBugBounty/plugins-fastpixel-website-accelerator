<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Module_Kinsta')) {
    class FASTPIXEL_Module_Kinsta extends FASTPIXEL_Module 
    {

        public function __construct() {
            parent::__construct();
        }

        public function early_init() {
            add_filter('fastpixel_cache_url_before_request', [$this, 'add_nocache_to_url'], 10, 1);
            add_filter('fastpixel_exclude_url', [$this, 'blacklisted_urls'], 10, 1);
        }

        public function init() {
            add_action('fastpixel/settings/config/save', [$this, 'save_fastpixel_config'], 10, 1);

            //early init and api init only if module is enabled        
            if ($this->enabled) {
                add_filter('fastpixel_cache_url_before_request', [$this, 'add_nocache_to_url'], 10, 1);
                add_filter('fastpixel/purge_all/do_request', function ($request) { return false; }, 10, 1);
                add_action('fastpixel/purge_all', [$this, 'purge_all'], 10);
                add_action('admin_bar_menu', [$this, 'update_admin_bar_menu'], 101);

                add_action('fastpixel/init/early', [$this, 'early_init'], 10);
                add_action('rest_api_init', [$this, 'api_init'], 10);
            }
        }

        public function api_init() {
            add_filter('fastpixel_cache_url_before_request', [$this, 'add_nocache_to_url'], 10, 1);
            add_action('fastpixel/cachefiles/saved', [$this, 'reset_page_cache'], 10, 1);
        }

        public function reset_page_cache($cached_url = false) {
            if (!$cached_url) {
                return;
            }
            global $kinsta_muplugin;
            if (empty($kinsta_muplugin) || !is_object($kinsta_muplugin) || get_class($kinsta_muplugin) != 'Kinsta\KMP') {
                return;
            }
            $post_id = url_to_postid($cached_url);
            if ($post_id > 0) {
                $kinsta_muplugin->kinsta_cache_purge->initiate_purge($post_id);
            } else {
                $input_url = new FASTPIXEL_Url($cached_url);
                $home_url  = new FASTPIXEL_Url(home_url());
                $reset_url = preg_replace('/https?:\/\//i', '', $input_url->get_url());
                if ($input_url->get_url() == $home_url->get_url()) {
                    $kinsta_muplugin->kinsta_cache_purge->send_cache_purge_request($kinsta_muplugin->kinsta_cache->config['immediate_path'], ['single|home_page' => $reset_url]);
                } else {
                    $kinsta_muplugin->kinsta_cache_purge->send_cache_purge_request($kinsta_muplugin->kinsta_cache->config['immediate_path'], ['group|singular_post' => $reset_url]);
                }
            }
        }

        public function save_fastpixel_config($config_instance) {
            $class_name = get_class($this);
            $enabled = $config_instance->get_option('fastpixel_enabled_modules');
            if (class_exists('Kinsta\KMP') && !empty($config_instance)) {
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
            $url->add_query_param('nocache');
            return $url->get_url();
        }

        public function purge_all()
        {
            global $kinsta_muplugin;
            if (empty($kinsta_muplugin) || !is_object($kinsta_muplugin) || get_class($kinsta_muplugin) != 'Kinsta\KMP') {
                return;
            }
            $kinsta_muplugin->kinsta_cache_purge->purge_complete_site_cache();
        }

        public function blacklisted_urls($urls) {
            $urls[] = '/kinsta-clear-cache-all';
            return $urls;
        }

        public function update_admin_bar_menu() {
            global $wp_admin_bar;
            $wp_admin_bar->remove_node('kinsta-cache');
            $wp_admin_bar->remove_node('kinsta-cache-full-page');
            //getting menu node and remove it
            $cache_node_id = 'fastpixel-top-' . FASTPIXEL_TEXTDOMAIN . '-purge-cache';
            $cache_node = $wp_admin_bar->get_node($cache_node_id);
            $wp_admin_bar->remove_node($cache_node_id);
            //replacing cache button text and adding new node
            if (is_object($cache_node)) {
                $cache_vars = get_object_vars($cache_node);
                $cache_vars['id'] = 'fastpixel-top-' . FASTPIXEL_TEXTDOMAIN . '-purge-cache';
                $cache_vars['title'] = esc_html__('Purge Kinsta and FastPixel Cache', 'fastpixel-website-accelerator');
                $wp_admin_bar->add_node($cache_vars);
            }
            //adding kinsta clear site cache into fastpixel menu
            $clear_kinsta_cache_vars = [
                'id'     => 'fastpixel-kinsta-cache-full-page',
                'parent' => 'fastpixel-top-' . FASTPIXEL_TEXTDOMAIN . '-menu',
                'href'   => esc_url(wp_nonce_url(admin_url('admin.php?page=kinsta-tools&clear-cache=kinsta-clear-site-cache' ), 'kinsta-clear-cache-admin-bar', 'kinsta_nonce')),
                'title'  => esc_html__('Purge Kinsta Site Cache', 'fastpixel-website-accelerator'),
            ];
            $wp_admin_bar->add_node($clear_kinsta_cache_vars);
        }
    }
    new FASTPIXEL_Module_Kinsta();
}
