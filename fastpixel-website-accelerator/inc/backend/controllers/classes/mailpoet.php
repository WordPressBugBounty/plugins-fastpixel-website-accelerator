<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Mailpoet_Backend')) {
    class FASTPIXEL_Mailpoet_Backend
    {
        public static $instance;

        public function __construct()
        {
            self::$instance = $this;
            //mailpoet admin pages dequeue all styles that are not in their whitelist,
            //adding our assets to it so admin bar menu styles are not removed
            add_filter('mailpoet_conflict_resolver_whitelist_style', [$this, 'whitelist_styles']);
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Mailpoet_Backend();
            }
            return self::$instance;
        }

        public function whitelist_styles($styles)
        {
            if (is_array($styles)) {
                $styles[] = 'fastpixel';
            }
            return $styles;
        }
    }
    new FASTPIXEL_Mailpoet_Backend();
}
