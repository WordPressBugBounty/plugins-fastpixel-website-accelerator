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
            add_settings_field(
                'fastpixel_images_optimization',
                esc_html__('Image Compression Level', 'fastpixel-website-accelerator'),
                [$this, 'field_images_optimization_cb'],
                FASTPIXEL_TEXTDOMAIN . '-images',
                'fastpixel_settings_section-images'
            );
            add_settings_field(
                'fastpixel_images_crop',
                esc_html__('Image Crop', 'fastpixel-website-accelerator'),
                [$this, 'field_images_crop_cb'],
                FASTPIXEL_TEXTDOMAIN.'-images',
                'fastpixel_settings_section-images'
            );
            add_settings_field(
                'fastpixel_force_image_dimensions',
                esc_html__('Image Sizes', 'fastpixel-website-accelerator'),
                [$this, 'field_force_image_dimensions_cb'],
                FASTPIXEL_TEXTDOMAIN . '-images',
                'fastpixel_settings_section-images'
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
            ?>
            <div class="fastpixel-select-with-description fastpixel-select">
                <div class="fastpixel-row">
                    <select id="fastpixel_images_optimization" name="fastpixel_images_optimization">
                        <option value="1" <?php echo $option == '1' ? 'selected="selected"' : ''; ?>><?php esc_html_e('Lossy', 'fastpixel-website-accelerator'); ?></option>
                        <option value="2" <?php echo $option == '2' ? 'selected="selected"' : ''; ?>><?php esc_html_e('Glossy', 'fastpixel-website-accelerator'); ?></option>
                        <option value="3" <?php echo $option == '3' ? 'selected="selected"' : ''; ?>><?php esc_html_e('Lossless', 'fastpixel-website-accelerator'); ?></option>
                    </select>
                    <div class="field-description">
                        <span class="optimization-description fastpixel-desc-hidden" data-value="1"><?php
                        /* translators: %s used to display option name, option name is translated separately */
                        printf(esc_html__('%s offers the best compression rate.', 'fastpixel-website-accelerator'), sprintf('<b>%s</b>', esc_html__('Lossy SmartCompression (recommended):', 'fastpixel-website-accelerator'))); ?></span>
                        <span class="optimization-description fastpixel-desc-hidden" data-value="2"><?php
                        /* translators: %s used to display option name, option name is translated separately */
                        printf(esc_html__('%s creates images that are almost pixel-perfect identical with the originals.', 'fastpixel-website-accelerator'), sprintf('<b>%s</b>', esc_html__('Glossy SmartCompression:', 'fastpixel-website-accelerator'))); ?></span>
                        <span class="optimization-description fastpixel-desc-hidden" data-value="3"><?php
                        /* translators: %s used to display option name, option name is translated separately */
                        printf(esc_html__('%s the resulting image is pixel-identical with the original image.', 'fastpixel-website-accelerator'), sprintf('<b>%s</b>', esc_html__('Lossless SmartCompression:', 'fastpixel-website-accelerator'))); ?></span>
                    </div>
                </div>
                <div class="fastpixel-row">
                    <div class="field-extra-description">
                        <span class="optimization-description fastpixel-desc-hidden" data-value="1"><?php esc_html_e('This is the recommended option for most users, producing results that look the same as the original to the human eye.', 'fastpixel-website-accelerator'); ?></span>
                        <span class="optimization-description fastpixel-desc-hidden" data-value="2"><?php esc_html_e('Best option for photographers and other professionals that use very high quality images on their sites and want the best compression while keeping the quality untouched.', 'fastpixel-website-accelerator'); ?></span>
                        <span class="optimization-description fastpixel-desc-hidden" data-value="3"><?php esc_html_e('Make sure not a single pixel looks different in the optimized image compared with the original. In some rare cases you will need to use this type of compression. Some technical drawings or images from vector graphics are possible situations.', 'fastpixel-website-accelerator'); ?></span>
                    </div>
                </div>
            </div>
            <?php
        }

        public function field_images_crop_cb($args) {
            // Get the value of the setting we've registered with register_setting()
            $crop = $this->functions->get_option('fastpixel_images_crop');
            ?>
            <input type="checkbox" id="fastpixel_images_crop" name="fastpixel_images_crop" value="1" <?php echo checked($crop); ?> />
            <span class="fastpixel-field-desc"><?php esc_html_e('Crop images to reduce size and fit better.', 'fastpixel-website-accelerator'); ?></span>
            <?php
        }

        public function field_force_image_dimensions_cb($args)
        {
            // Get the value of the setting we've registered with register_setting()
            $force = $this->functions->get_option('fastpixel_force_image_dimensions');
            ?>
            <input type="checkbox" id="fastpixel_force_image_dimensions" name="fastpixel_force_image_dimensions" value="1" <?php echo checked($force); ?> />
            <span class="fastpixel-field-desc"><?php esc_html_e('Add missing Width and Height to image elements.', 'fastpixel-website-accelerator'); ?></span>
            <?php
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
