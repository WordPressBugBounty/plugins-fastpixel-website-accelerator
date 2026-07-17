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
                'fastpixel_javascript_exclusion_rules',
                $field_title,
                [$this, 'field_fastpixel_javascript_exclusion_rules_cb'],
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
                    3 => esc_html__('Do not optimize JavaScript', 'fastpixel-website-accelerator'),
                    1 => esc_html__('Optimize JavaScript', 'fastpixel-website-accelerator'),
                    2 => esc_html__('Delay non-critical JavaScript', 'fastpixel-website-accelerator')
                ],
                'selected'     => $option,
                'label'        => $args['label'],
                // 'description'  => esc_html__('Optimization', 'fastpixel-website-accelerator'),
                'value_descriptions' => [
                    3 => esc_html__('No JavaScript optimization is performed; the scripts run exactly as on the original page.', 'fastpixel-website-accelerator'),
                    1 => esc_html__('All scripts are optimized and run as on the original page.', 'fastpixel-website-accelerator'),
                    2 => esc_html__('All scripts are optimized and delayed, except for necessary scripts like GDPR.', 'fastpixel-website-accelerator'),
                ]
            ], true);
        }
        public function field_fastpixel_javascript_exclusion_rules_cb($args) {
            $rules = $this->get_javascript_exclusion_rules();
            $description = sprintf(
                esc_html__('Scripts matching these rules can be excluded from optimization or kept out of delay. Use URL for simple text matches and RegExp only for advanced patterns. %1$s', 'fastpixel-website-accelerator'),
                '<a href="https://fastpixel.io/docs/fastpixel-exclusions/" target="_blank">' . esc_html(__('Read More', 'fastpixel-website-accelerator')) . '</a>'
            );
            $this->be_functions->print_asset_exclusions([
                'field_name'  => 'fastpixel_javascript_exclusion_rules',
                'field_value' => wp_json_encode($rules),
                'label'       => $args['label'],
                'placeholder' => esc_html__('e.g. /wp-content/plugins/my-plugin/script.js', 'fastpixel-website-accelerator'),
                'description' => $description,
                'data'        => ['data-depends-on="fastpixel-javascript-optimization"'],
            ], true);
        }

        public function field_fastpixel_javascript_exclude_gdpr_cb($args) {
            // Get the value of the setting we've registered with register_setting()
            $exclude = $this->functions->get_option('fastpixel_javascript_exclude_gdpr');
            $this->be_functions->print_checkbox([
                'field_name'  => 'fastpixel_javascript_exclude_gdpr',
                'checked'     => $exclude,
                'label'       => $args['label'],
                'description' => esc_html__('Exclude GDPR/Cookie pop-ups from being cached.', 'fastpixel-website-accelerator')
            ], true);
        }

        public function save_options() {
            if (!$this->validate_settings_save_request()) {
                return;
            }
            if (isset($_POST['fastpixel_javascript_optimization']) && is_numeric($_POST['fastpixel_javascript_optimization'])) {
                $this->functions->update_option('fastpixel_javascript_optimization', (int)sanitize_text_field($_POST['fastpixel_javascript_optimization']));
            }
            if (isset($_POST['fastpixel_javascript_exclusion_rules'])) {
                $rules = $this->be_functions->sanitize_asset_exclusion_rules(wp_unslash($_POST['fastpixel_javascript_exclusion_rules']), true);
                $old_rules = $this->be_functions->sanitize_asset_exclusion_rules($this->functions->get_option('fastpixel_javascript_exclusion_rules', ''), true);
                if ($rules != $old_rules) {
                    $this->purge_all = true;
                }
                $this->functions->update_option('fastpixel_javascript_exclusion_rules', wp_json_encode($rules));
                $this->delete_legacy_javascript_exclusion_options();
            }
            $gdpr = isset($_POST['fastpixel_javascript_exclude_gdpr']) && 1 == sanitize_text_field($_POST['fastpixel_javascript_exclude_gdpr']) ? 1 : 0;
            if ($gdpr != (int) $this->functions->get_option('fastpixel_javascript_exclude_gdpr', 1)) {
                $this->purge_all = true;
            }
            $this->functions->update_option('fastpixel_javascript_exclude_gdpr', $gdpr);
        }

        public function get_purge_all_status($status)
        {
            if ($status == true) {
                return $status;
            }
            return $this->purge_all;
        }

        protected function get_javascript_exclusion_rules() {
            $rules = $this->be_functions->sanitize_asset_exclusion_rules($this->functions->get_option('fastpixel_javascript_exclusion_rules', ''), true);
            if (!empty($rules)) {
                return $rules;
            }

            $legacy_rules = $this->get_legacy_javascript_exclusion_rules();
            if (!empty($legacy_rules)) {
                $this->functions->update_option('fastpixel_javascript_exclusion_rules', wp_json_encode($legacy_rules));
                $this->delete_legacy_javascript_exclusion_options();
            }

            return $legacy_rules;
        }

        protected function get_legacy_javascript_exclusion_rules() {
            $rules = [];
            $js_excludes = $this->functions->get_option('fastpixel_javascript_excludes', '');
            $js_excludes_array = preg_split('/\r\n|\r|\n/', $js_excludes);
            foreach ($js_excludes_array as $entry) {
                $entry = trim(stripslashes($entry));
                if ($entry !== '') {
                    $rules[] = [
                        'type'     => 'url',
                        'value'    => $entry,
                        'behavior' => 'exclude',
                    ];
                }
            }

            $js_excludes_regexp = $this->functions->get_option('fastpixel_javascript_excludes_regexp', '');
            $js_excludes_regexp_array = preg_split('/\r\n|\r|\n/', $js_excludes_regexp);
            foreach ($js_excludes_regexp_array as $regexp_exclude) {
                $regexp_exclude = trim(stripslashes($regexp_exclude));
                if ($regexp_exclude !== '') {
                    $rules[] = [
                        'type'     => 'regex',
                        'value'    => $regexp_exclude,
                        'behavior' => 'exclude',
                    ];
                }
            }

            return $this->be_functions->sanitize_asset_exclusion_rules($rules, true);
        }

        protected function delete_legacy_javascript_exclusion_options() {
            $this->functions->delete_option('fastpixel_javascript_excludes');
            $this->functions->delete_option('fastpixel_javascript_excludes_regexp');
        }
    }
    new FASTPIXEL_Tab_Javascript();
}
