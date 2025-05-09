<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Tab_Images')) {
    class FASTPIXEL_Tab_Images extends FASTPIXEL_UI_Tab
    {

        protected $slug = 'images';
        protected $order = 6;
        protected $purge_all = false;

        public function __construct()
        {
            parent::__construct();
            $this->name = esc_html__('Images', 'fastpixel-website-accelerator');
            add_filter('sanitize_option_fastpixel_images_optimization', [$this, 'sanitize_fastpixel_images_optimization_cb'], 10, 3);
            add_action('fastpixel/tabs/loaded', [$this, 'save_options'], 11);
            add_filter('fastpixel/settings_tab/purge_all', [$this, 'get_purge_all_status'], 11, 1);
        }

        public function settings()
        {
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_images_optimization', ['type' => 'integer']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_images_crop', ['type' => 'boolean']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_force_image_dimensions', ['type' => 'boolean']);
            add_settings_section(
                'fastpixel_settings_section-images',
                '',
                false,
                FASTPIXEL_TEXTDOMAIN . '-images'
            );
            $field_title = esc_html__('Image Compression Level', 'fastpixel-website-accelerator');
            add_settings_field(
                'fastpixel_images_optimization',
                $field_title,
                [$this, 'field_images_optimization_cb'],
                FASTPIXEL_TEXTDOMAIN . '-images',
                'fastpixel_settings_section-images',
                [
                    'class' => 'fastpixel-settings-form-row',
                    'label' => $field_title
                ]
            );
            $field_title = esc_html__('Image Crop', 'fastpixel-website-accelerator');
            add_settings_field(
                'fastpixel_images_crop',
                $field_title,
                [$this, 'field_images_crop_cb'],
                FASTPIXEL_TEXTDOMAIN.'-images',
                'fastpixel_settings_section-images',
                [
                    'class' => 'fastpixel-settings-form-row',
                    'label' => $field_title
                ]
            );
            $field_title = esc_html__('Image Sizes', 'fastpixel-website-accelerator');
            add_settings_field(
                'fastpixel_force_image_dimensions',
                $field_title,
                [$this, 'field_force_image_dimensions_cb'],
                FASTPIXEL_TEXTDOMAIN . '-images',
                'fastpixel_settings_section-images',
                [
                    'class' => 'fastpixel-settings-form-row',
                    'label' => $field_title
                ]
            );
        }
        public function sanitize_fastpixel_images_optimization_cb($value, $option, $original_value)
        {
            $old_value = $this->functions->get_option($option);
            if ($value != $old_value) {
                $this->purge_all = true;
            }
            return $value;
        }

        public function field_images_optimization_cb($args)
        {
            // Get the value of the setting we've registered with register_setting()
            $option = $this->functions->get_option('fastpixel_images_optimization', 1);
            $this->be_functions->print_horizontal_selector([
                'field_name'         => 'fastpixel_images_optimization',
                'field_values'       => [
                    3 => esc_html__('Lossless', 'fastpixel-website-accelerator'),
                    2 => esc_html__('Glossy', 'fastpixel-website-accelerator'),
                    1 => esc_html__('Lossy', 'fastpixel-website-accelerator')
                ],
                'selected'           => $option,
                'label'              => $args['label'],
                'value_descriptions' => [
                    1 => esc_html__('This is the recommended option for most users, producing results that appear identical to the original to the human eye.', 'fastpixel-website-accelerator') . '<br/>' . esc_html__('FastPixel automatically serves images in WebP format. If a visitor\'s browser doesn\'t support WebP, the original format (e.g. JPEG, PNG) is used instead.', 'fastpixel-website-accelerator'),
                    2 => esc_html__('Best option for photographers and other professionals who use very high-quality images on their sites and want the best compression while keeping the quality untouched.', 'fastpixel-website-accelerator') . '<br/>' . esc_html__('FastPixel automatically serves images in WebP format. If a visitor\'s browser doesn\'t support WebP, the original format (e.g. JPEG, PNG) is used instead.', 'fastpixel-website-accelerator'),
                    3 => esc_html__('Make sure not a single pixel looks different in the optimized image compared with the original. In some rare cases, you will need to use this type of compression. Technical drawings or images from vector graphics are possible situations.', 'fastpixel-website-accelerator') . '<br/>' . esc_html__('FastPixel automatically serves images in WebP format. If a visitor\'s browser doesn\'t support WebP, the original format (e.g. JPEG, PNG) is used instead.', 'fastpixel-website-accelerator')
                ]
            ], true);
        }

        public function field_images_crop_cb($args) {
            // Get the value of the setting we've registered with register_setting()
            $crop = $this->functions->get_option('fastpixel_images_crop');
            $this->be_functions->print_checkbox([
                'field_name'  => 'fastpixel_images_crop',
                'checked'     => $crop,
                'label'       => $args['label'],
                'description' => esc_html__('Automatically crop images to fit their display area perfectly, based on the visitor\'s screen size.', 'fastpixel-website-accelerator')
            ], true);
        }

        public function field_force_image_dimensions_cb($args)
        {
            // Get the value of the setting we've registered with register_setting()
            $force = $this->functions->get_option('fastpixel_force_image_dimensions');
            $this->be_functions->print_checkbox([
                'field_name'  => 'fastpixel_force_image_dimensions',
                'checked'     => $force,
                'label'       => $args['label'],
                'description' => esc_html__('Add missing Width and Height to image elements.', 'fastpixel-website-accelerator')
            ], true);
        }

        public function save_options() {
            if (sanitize_text_field($_SERVER['REQUEST_METHOD']) !== 'POST' || (defined('DOING_AJAX') && DOING_AJAX) || 
                check_admin_referer('fastpixel-settings', 'fastpixel-nonce') == false ||
                empty($_POST['fastpixel-action']) || sanitize_key($_POST['fastpixel-action']) != 'save_settings') {
                return;
            }
            if (isset($_POST['fastpixel_images_optimization']) && is_numeric($_POST['fastpixel_images_optimization'])) {
                $this->functions->update_option('fastpixel_images_optimization', (int)sanitize_text_field($_POST['fastpixel_images_optimization']));
            }
            $images_crop = isset($_POST['fastpixel_images_crop']) && 1 == sanitize_text_field($_POST['fastpixel_images_crop']) ? 1 : 0;
            $this->functions->update_option('fastpixel_images_crop', $images_crop);
            $force_image_dimensions = isset($_POST['fastpixel_force_image_dimensions']) && 1 == sanitize_text_field($_POST['fastpixel_force_image_dimensions']) ? 1 : 0;
            $this->functions->update_option('fastpixel_force_image_dimensions', $force_image_dimensions);    
        }

        public function get_purge_all_status($status)
        {
            if ($status == true) {
                return $status;
            }
            return $this->purge_all;
        }
    }
    new FASTPIXEL_Tab_Images();
}
