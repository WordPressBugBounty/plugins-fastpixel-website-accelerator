<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Tab_Fonts')) {
    class FASTPIXEL_Tab_Fonts extends FASTPIXEL_UI_Tab
    {

        protected $slug = 'fonts';
        protected $order = 7;

        public function __construct()
        {
            parent::__construct();
            $this->name = esc_html__('Fonts', 'fastpixel-website-accelerator');
            add_action('fastpixel/tabs/loaded', [$this, 'save_options'], 12);
        }

        public function settings()
        {
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_fonts_soft', ['type' => 'boolean']);
            add_settings_section(
                'fastpixel_settings_section-fonts',
                '',
                false,
                FASTPIXEL_TEXTDOMAIN . '-fonts'
            );
            $field_title = esc_html__('Compatibility Mode', 'fastpixel-website-accelerator');
            add_settings_field(
                'fastpixel_fonts_soft',
                $field_title,
                [$this, 'field_fonts_soft_cb'],
                FASTPIXEL_TEXTDOMAIN . '-fonts',
                'fastpixel_settings_section-fonts',
                [
                    'class' => 'fastpixel-settings-form-row',
                    'label' => $field_title
                ]
            );
        }

        public function field_fonts_soft_cb($args) {
            // Get the value of the setting we've registered with register_setting()
            $soft = $this->functions->get_option('fastpixel_fonts_soft');
            $this->be_functions->print_checkbox([
                'field_name'  => 'fastpixel_fonts_soft',
                'checked'     => $soft,
                'label'       => $args['label'],
                'description' => esc_html__('Improve compatibility by also loading the original font in cases where the optimized fonts display glitches.', 'fastpixel-website-accelerator')
            ], true);
        }

        public function save_options() {
            if (sanitize_text_field($_SERVER['REQUEST_METHOD']) !== 'POST' || (defined('DOING_AJAX') && DOING_AJAX) || 
                check_admin_referer('fastpixel-settings', 'fastpixel-nonce') == false ||
                empty($_POST['fastpixel-action']) || sanitize_key($_POST['fastpixel-action']) != 'save_settings') {
                return;
            }
            $font_soft = isset($_POST['fastpixel_fonts_soft']) && 1 == sanitize_text_field($_POST['fastpixel_fonts_soft']) ? 1 : 0;
            $this->functions->update_option('fastpixel_fonts_soft', $font_soft);
        }
    }
    new FASTPIXEL_Tab_Fonts();
}
