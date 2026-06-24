<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Module_WPDiscuz')) {
    class FASTPIXEL_Module_WPDiscuz extends FASTPIXEL_Module
    {
        public function __construct() {
            parent::__construct();
        }

        public function init() {
            //keep the module en/disabled state in sync with WPDiscuz presence (shown in enabled modules)
            add_action('fastpixel/settings/config/save', [$this, 'save_fastpixel_config'], 10, 1);

            /*
             * WPDiscuz fires "wpdiscuz_clean_post_cache" for every event that changes the rendered
             * page: new comment, comment edit, inline comment, vote, rating and social login. It runs
             * over admin-ajax (often as a logged-out visitor), so we purge by URL via the public
             * "fastpixel/purge/url" action which - unlike the post-object purge - has no edit_post
             * capability check and therefore works for anonymous commenters.
             *
             * Registered unconditionally: only WPDiscuz ever fires this action, so the listener is a
             * no-op when WPDiscuz is absent, and this avoids any plugin load-order race with $this->enabled.
             */
            add_action('wpdiscuz_clean_post_cache', [$this, 'purge_post_cache'], 10, 2);
        }

        public function purge_post_cache($post_id, $reason = '') {
            if (empty($post_id) || !is_numeric($post_id)) {
                return;
            }
            $permalink = get_permalink((int) $post_id);
            if (empty($permalink)) {
                return;
            }
            do_action('fastpixel/purge/url', $permalink);
        }

        public function save_fastpixel_config($config_instance) {
            if (empty($config_instance)) {
                return;
            }
            $class_name = get_class($this);
            $enabled = $config_instance->get_option('fastpixel_enabled_modules');
            if (!is_array($enabled)) {
                $enabled = [];
            }
            if (defined('WPDISCUZ_DIR_PATH') || class_exists('WpdiscuzCore')) {
                if (!in_array($class_name, $enabled)) {
                    $enabled[] = $class_name;
                }
            } else {
                if (in_array($class_name, $enabled)) {
                    $key = array_search($class_name, $enabled);
                    unset($enabled[$key]);
                }
            }
            $config_instance->set_option('fastpixel_enabled_modules', array_values($enabled));
        }
    }
    new FASTPIXEL_Module_WPDiscuz();
}
