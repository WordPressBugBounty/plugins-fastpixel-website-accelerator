<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Module_WP_SEO')) {
    class FASTPIXEL_Module_WP_SEO extends FASTPIXEL_Module 
    {

        public function __construct() {
            parent::__construct();
        }

        public function init() {
            add_filter('Yoast\WP\SEO\allowlist_permalink_vars', function ($vars) {
                $vars = array_merge($vars, ['fastpixeldebug', 'fastpixeldisable']);
                return $vars;
            }, 10, 1);
        }
    }
    new FASTPIXEL_Module_WP_SEO();
}
