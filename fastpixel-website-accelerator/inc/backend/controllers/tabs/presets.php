<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Tab_Presets')) {
    class FASTPIXEL_Tab_Presets extends FASTPIXEL_UI_Tab
    {
        protected $name = 'Presets';
        protected $slug = 'presets';
        protected $order = 3;

        public function __construct() {
            parent::__construct();
            $this->name = esc_html__('Presets', 'fastpixel-website-accelerator');
            add_action('admin_enqueue_scripts', function () {
                global $pagenow;
                $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : false;
                if ($pagenow == 'admin.php' && $page && in_array($page, [FASTPIXEL_TEXTDOMAIN . '-settings'])) {
                    wp_localize_script('fastpixel-backend', 'fastpixel_preset_settings', $this->get_settings());
                    wp_localize_script('fastpixel-backend', 'fastpixel_presets', $this->get_presets());
                }
            });
        }
        public function settings() {
            if (!$this->check_capabilities()) {
                return;
            }
        }
        public function get_presets() {
            $presets = [
                'safe' => [
                    'fastpixel_javascript_optimization' => 3,
                    'fastpixel_javascript_exclude_gdpr' => false,
                    'fastpixel_images_optimization'     => 3,
                    'fastpixel_images_crop'             => false,
                    'fastpixel_fonts_soft'              => true,
                    'fastpixel_speculation_rules'       => false,
                ],
                'basic' => [
                    'fastpixel_javascript_optimization' => 1,
                    'fastpixel_javascript_exclude_gdpr' => true,
                    'fastpixel_images_optimization'     => 2,
                    'fastpixel_images_crop'             => true,
                    'fastpixel_fonts_soft'              => true,
                    'fastpixel_speculation_rules'       => true,
                    'fastpixel_speculation_mode'        => 'prerender',
                    'fastpixel_speculation_eagerness'   => 'moderate',
                ],
                'fast' => [
                    'fastpixel_javascript_optimization' => 2,
                    'fastpixel_javascript_exclude_gdpr' => true,
                    'fastpixel_images_optimization'     => 1,
                    'fastpixel_images_crop'             => true,
                    'fastpixel_fonts_soft'              => false,
                    'fastpixel_speculation_rules'       => true,
                    'fastpixel_speculation_mode'        => 'prerender',
                    'fastpixel_speculation_eagerness'   => 'eager',
                ]
            ];
            //removing speculative loading fields if original plugin is enabled
            if (defined('SPECULATION_RULES_VERSION')) {
                foreach ($presets as &$preset) {
                    foreach(['fastpixel_speculation_rules', 'fastpixel_speculation_mode', 'fastpixel_speculation_eagerness'] as $field) {
                        if (isset($preset[$field])) {
                            unset($preset[$field]);
                        }
                    }
                }
            }
            return $presets;
        }
        public function get_settings()
        {
            $functions = FASTPIXEL_Functions::get_instance();
            return [
                'fastpixel_javascript_optimization',
                'fastpixel_javascript_exclude_gdpr',
                'fastpixel_images_optimization',
                'fastpixel_images_crop',
                'fastpixel_fonts_soft',
            ];
        }
    }
    new FASTPIXEL_Tab_Presets();
}
