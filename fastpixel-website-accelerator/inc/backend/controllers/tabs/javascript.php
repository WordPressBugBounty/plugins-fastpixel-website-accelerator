<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Tab_Javascript')) {
    class FASTPIXEL_Tab_Javascript extends FASTPIXEL_UI_Tab
    {

        protected $slug = 'javascript';
        protected $order = 5;
        protected $purge_all = false;

        public function __construct() {
            parent::__construct();
            $this->name = esc_html__('JavaScript', 'fastpixel-website-accelerator');
            add_filter('sanitize_option_fastpixel_javascript_optimization', [$this, 'sanitize_fastpixel_javascript_optimization_cb'], 10, 3);
            add_action('fastpixel/tabs/loaded', [$this, 'save_options'], 10);
            add_filter('fastpixel/settings_tab/purge_all', [$this, 'get_purge_all_status'], 10, 1);
        }

        public function settings() {
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_javascript_optimization', ['type' => 'integer']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_javascript_excludes', ['type' => 'array']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_javascript_excludes_regexp', ['type' => 'array']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_javascript_exclude_gdpr', ['type' => 'boolean']);
            add_settings_section(
                'fastpixel_settings_section-javascript',
                '',
                false,
                FASTPIXEL_TEXTDOMAIN . '-javascript'
            );
            $field_title = esc_html__('Optimization', 'fastpixel-website-accelerator');
            add_settings_field(
                'fastpixel_javascript_optimization',
                esc_html__('Optimization', 'fastpixel-website-accelerator'),
                [$this, 'field_fastpixel_javascript_optimization_cb'],
                FASTPIXEL_TEXTDOMAIN . '-javascript',
                'fastpixel_settings_section-javascript',
                [
                    'class' => 'fastpixel-settings-form-row',
                    'label' => $field_title
                ]
            );
            $field_title = esc_html__('Exclusions', 'fastpixel-website-accelerator');
            add_settings_field(
                'fastpixel_javascript_excludes',
                $field_title,
                [$this, 'field_fastpixel_javascript_excludes_cb'],
                FASTPIXEL_TEXTDOMAIN . '-javascript',
                'fastpixel_settings_section-javascript',
                [
                    'class' => 'fastpixel-settings-form-row',
                    'label' => $field_title
                ]
            );
            $field_title = esc_html__('RegExp Exclusions', 'fastpixel-website-accelerator');
            add_settings_field(
                'fastpixel_javascript_excludes_regexp',
                $field_title,
                [$this, 'field_fastpixel_javascript_excludes_regexp_cb'],
                FASTPIXEL_TEXTDOMAIN . '-javascript',
                'fastpixel_settings_section-javascript',
                [
                    'class' => 'fastpixel-settings-form-row',
                    'label' => $field_title
                ]
            );
            $field_title = esc_html__('GDPR', 'fastpixel-website-accelerator');
            add_settings_field(
                'fastpixel_javascript_exclude_gdpr',
                $field_title,
                [$this, 'field_fastpixel_javascript_exclude_gdpr_cb'],
                FASTPIXEL_TEXTDOMAIN . '-javascript',
                'fastpixel_settings_section-javascript',
                [
                    'class' => 'fastpixel-settings-form-row',
                    'label' => $field_title
                ]
            );
        }
        public function sanitize_fastpixel_javascript_optimization_cb($value, $option, $original_value) {
            $old_value = $this->functions->get_option($option);
            if ($value != $old_value) {
                $this->purge_all = true;
            }
            return $value;
        }
        public function field_fastpixel_javascript_optimization_cb($args) {
            // Get the value of the setting we've registered with register_setting()
            $option = $this->functions->get_option('fastpixel_javascript_optimization');
            $this->be_functions->print_horizontal_selector([
                'field_name'   => 'fastpixel_javascript_optimization',
                'field_values' => [
                    1 => esc_html__('Optimize JavaScript', 'fastpixel-website-accelerator'),
                    2 => esc_html__('Delay non-critical JavaScript', 'fastpixel-website-accelerator'),
                    3 => esc_html__('Do not optimize JavaScript', 'fastpixel-website-accelerator')
                ],
                'selected'     => $option,
                'label'        => $args['label'],
                // 'description'  => esc_html__('Optimization', 'fastpixel-website-accelerator'),
                'value_descriptions' => [
                    1 => esc_html__('All scripts are optimized and run as on the original page.', 'fastpixel-website-accelerator'),
                    2 => esc_html__('All scripts are optimized and delayed, except for necessary scripts like GDPR.', 'fastpixel-website-accelerator'),
                    3 => esc_html__('No JavaScript optimization is performed; the scripts run exactly as on the original page.', 'fastpixel-website-accelerator')
                ]
            ], true);
        }
        public function field_fastpixel_javascript_excludes_cb($args) {
            // Get the value of the setting we've registered with register_setting()
            $excludes = stripslashes($this->functions->get_option('fastpixel_javascript_excludes'));
            /* translators: %s used to display "a" tag(read more), nothing to translate */
            $description = sprintf(esc_html__('Scripts that should be excluded from caching. Each script URL should be added on a new line. %1$s', 'fastpixel-website-accelerator'), '<a href="https://fastpixel.io/docs/fastpixel-exclusions/" target="_blank">' . esc_html(__('Read More', 'fastpixel-website-accelerator')) . '</a>');
            $description .= '<br/><br/>' . esc_html__('Example:', 'fastpixel-website-accelerator') . '&nbsp;' . esc_url(home_url('/wp-includes/js/jquery/jquery.js'));
            $this->be_functions->print_textarea([
                'field_name'  => 'fastpixel_javascript_excludes',
                'field_value' => $excludes,
                'label'       => $args['label'],
                'description' => $description
            ], true);
        }
        public function field_fastpixel_javascript_excludes_regexp_cb($args) {
            // Get the value of the setting we've registered with register_setting()
            $excludes = stripslashes($this->functions->get_option('fastpixel_javascript_excludes_regexp'));
            $examples_text = '<span class="fastpixel-textarea-desc">' . esc_html__('Examples:', 'fastpixel-website-accelerator') . '&nbsp;fastpixel(\.min)?\.js<br/><span class="fastpixel-javascript-regexp-excludes-example-2d-row">fastpixel\.js\?ver=2\.8\.4</span></span>';
            /* translators: %s used to display "br" tag, nothing to translate */
            $description = sprintf(esc_html__('Specify URLs, keywords, or regular expressions that can identify inline or src of JavaScript to exclude from deferred execution (one per line). %1$s.%2$s
            This is a good place to put your menus, carousels in the hero area, GA (Google Analytics), and GTM. %3$s', 'fastpixel-website-accelerator'), '<a href="https://fastpixel.io/docs/fastpixel-exclusions/" target="_blank">' . esc_html(__('Read More', 'fastpixel-website-accelerator')) . '</a>', '<br/><br/>', $examples_text);
            $this->be_functions->print_textarea([
                'field_name'  => 'fastpixel_javascript_excludes_regexp',
                'field_value' => $excludes,
                'label'       => $args['label'],
                'description' => $description
            ], true);
        }

        public function field_fastpixel_javascript_exclude_gdpr_cb($args) {
            // Get the value of the setting we've registered with register_setting()
            $exclude = $this->functions->get_option('fastpixel_javascript_exclude_gdpr');
            $this->be_functions->print_checkbox([
                'field_name'  => 'fastpixel_javascript_exclude_gdpr',
                'checked'     => $exclude,
                'label'       => $args['label'],
                'description' => esc_html__('Exclude GDPR/Cookie pop-ups.', 'fastpixel-website-accelerator')
            ], true);
        }

        public function save_options() {
            if (sanitize_text_field($_SERVER['REQUEST_METHOD']) !== 'POST' || (defined('DOING_AJAX') && DOING_AJAX) || 
                check_admin_referer('fastpixel-settings', 'fastpixel-nonce') == false ||
                empty($_POST['fastpixel-action']) || sanitize_key($_POST['fastpixel-action']) != 'save_settings') {
                return;
            }
            if (isset($_POST['fastpixel_javascript_optimization']) && is_numeric($_POST['fastpixel_javascript_optimization'])) {
                $this->functions->update_option('fastpixel_javascript_optimization', (int)sanitize_text_field($_POST['fastpixel_javascript_optimization']));
            }
            if (isset($_POST['fastpixel_javascript_excludes'])) {
                $this->functions->update_option('fastpixel_javascript_excludes', sanitize_textarea_field($_POST['fastpixel_javascript_excludes']));
            }
            if (isset($_POST['fastpixel_javascript_excludes_regexp'])) {
                $this->functions->update_option('fastpixel_javascript_excludes_regexp', sanitize_textarea_field($_POST['fastpixel_javascript_excludes_regexp']));
            }
            $gdpr = isset($_POST['fastpixel_javascript_exclude_gdpr']) && 1 == sanitize_text_field($_POST['fastpixel_javascript_exclude_gdpr']) ? 1 : 0;
            $this->functions->update_option('fastpixel_javascript_exclude_gdpr', $gdpr);    
        }

        public function get_purge_all_status($status)
        {
            if ($status == true) {
                return $status;
            }
            return $this->purge_all;
        }
    }
    new FASTPIXEL_Tab_Javascript();
}
