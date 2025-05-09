<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Tab_Help_Center')) {
    class FASTPIXEL_Tab_Help_Center extends FASTPIXEL_UI_Tab
    {
        protected $name = 'Help Center';
        protected $slug = 'help_center';
        protected $order = 11;

        public function __construct() {
            parent::__construct();
            $this->name = esc_html__('Help Center', 'fastpixel-website-accelerator');
        }
        public function settings() {
            if (!$this->check_capabilities()) {
                return;
            }
        }
        protected function get_links() {
            $links = [
                [
                    'icon' => FASTPIXEL_PLUGIN_URL . 'icons/help.svg',
                    'title' => __('Knowledge Base', 'fastpixel-website-accelerator'),
                    'description' => __('Most customer questions are answered in our Knowledge Base.', 'fastpixel-website-accelerator'),
                    'link' => 'https://fastpixel.io/docs/',
                ],
                [
                    'icon' => FASTPIXEL_PLUGIN_URL . 'icons/envelope.svg',
                    'title' => __('Get Support', 'fastpixel-website-accelerator'),
                    'description' => __('Contact us with any issues, bug reports, or questions.', 'fastpixel-website-accelerator'),
                    'link' => 'https://fastpixel.io/#contact',
                    'button_title' => __('Contact Us', 'fastpixel-website-accelerator')
                ],
                [
                    'icon' => FASTPIXEL_PLUGIN_URL . 'icons/processing.svg',
                    'title' => __('Upgrade Plan', 'fastpixel-website-accelerator'),
                    'description' => __('Upgrade your plan to get more pageviews and priority caching for your website.', 'fastpixel-website-accelerator'),
                    'link' => 'https://fastpixel.io/pricing/'
                ],
                [
                    'icon'        => FASTPIXEL_PLUGIN_URL . 'icons/lightbulb.svg',
                    'title'       => __('Feature Request', 'fastpixel-website-accelerator'),
                    'description' => __('Is there a feature missing? Do you have suggestions for improving FastPixel?', 'fastpixel-website-accelerator'),
                    'link'        => 'mailto:support@fastpixel.io?subject=FastPixel Feature Request'
                ]
            ];
            return $links;
        }
        public function get_settings()
        {
            return [];
        }
    }
    new FASTPIXEL_Tab_Help_Center();
}
