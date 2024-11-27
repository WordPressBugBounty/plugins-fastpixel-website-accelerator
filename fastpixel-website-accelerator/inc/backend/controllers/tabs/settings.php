<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Tab_Settings')) {
    class FASTPIXEL_Tab_Settings extends FASTPIXEL_UI_Tab
    {
        protected $name = 'Settings';
        protected $slug = 'settings';
        protected $order = 4;
        public $cache_dir;
        protected $config_file;
        protected $api_key;

        public function __construct() {
            parent::__construct();
            $this->name = esc_html__('Settings', 'fastpixel-website-accelerator');
            $this->save_options();
        }

        public function settings() {
            if (!$this->check_capabilities()) {
                return;
            }

            // Register a new setting for "settings" page.
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_serve_stale', ['type' => 'boolean']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_display_cached_for_logged', ['type' => 'boolean']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_speculation_rules', ['type' => 'boolean']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_speculation_mode', [
                'type'              => 'string',
                'sanitize_callback' => [$this, 'sanitize_fastpixel_speculation_mode'],
                'default'           => 'prerender'
            ]);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_speculation_eagerness', [
                'type'              => 'string', 
                'sanitize_callback' => [$this, 'sanitize_fastpixel_speculation_eagerness'],
                'default'           => 'moderate'
            ]);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_exclusions', ['type' => 'array']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_exclude_all_params', ['type' => 'array']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_params_exclusions', ['type' => 'array']);
            // Register a new section in the "settings" page.
            add_settings_section(
                'fastpixel_settings_section',
                '',
                false,
                FASTPIXEL_TEXTDOMAIN
            );

            add_settings_field(
                'fastpixel_serve_stale',
                esc_html__('Serve stale', 'fastpixel-website-accelerator'),
                [$this, 'field_serve_stale_cb'],
                FASTPIXEL_TEXTDOMAIN,
                'fastpixel_settings_section'
            );
            add_settings_field(
                'fastpixel_display_cached_for_logged',
                esc_html__('Logged-in users', 'fastpixel-website-accelerator'),
                [$this, 'fastpixel_display_cached_for_logged_cb'],
                FASTPIXEL_TEXTDOMAIN,
                'fastpixel_settings_section'
            );
            if (!defined('SPECULATION_RULES_VERSION')) { //displaying speculation rules fields only if speculation rules plugin is not installed/enabled
                add_settings_field(
                    'fastpixel_speculation_rules',
                    esc_html__('Speculation Rules', 'fastpixel-website-accelerator'),
                    [$this, 'field_speculation_rules_cb'],
                    FASTPIXEL_TEXTDOMAIN,
                    'fastpixel_settings_section'
                );
                add_settings_field(
                    'fastpixel_speculation_mode',
                    esc_html__('Speculation Mode', 'fastpixel-website-accelerator'),
                    [$this, 'field_speculation_mode_cb'],
                    FASTPIXEL_TEXTDOMAIN,
                    'fastpixel_settings_section'
                );
                add_settings_field(
                    'fastpixel_speculation_eagerness',
                    esc_html__('Speculation Eagerness', 'fastpixel-website-accelerator'),
                    [$this, 'field_speculation_eagerness_cb'],
                    FASTPIXEL_TEXTDOMAIN,
                    'fastpixel_settings_section'
                );
            }
            add_settings_field(
                'fastpixel_exclusions',
                esc_html__('Page Exclusions', 'fastpixel-website-accelerator'),
                [$this, 'field_exclusions_cb'],
                FASTPIXEL_TEXTDOMAIN,
                'fastpixel_settings_section'
            );
            add_settings_field(
                'fastpixel_params_exclusions',
                esc_html__('Parameter Exclusions', 'fastpixel-website-accelerator'),
                [$this, 'field_params_exclusions_cb'],
                FASTPIXEL_TEXTDOMAIN,
                'fastpixel_settings_section'
            );
            add_settings_field(
                'fastpixel_exclude_all_params',
                esc_html__('Disable All Parameters', 'fastpixel-website-accelerator'),
                [$this, 'field_exclude_all_params_cb'],
                FASTPIXEL_TEXTDOMAIN,
                'fastpixel_settings_section'
            );
        }

        public function field_serve_stale_cb($args) {
            // Get the value of the setting we've registered with register_setting()
            $serve_stale = $this->functions->get_option('fastpixel_serve_stale');
            ?>
            <input id="fastpixel_serve_stale" type="checkbox" name="fastpixel_serve_stale" value="1" <?php echo checked($serve_stale); ?>> <span class="fastpixel-field-desc"><?php esc_html_e('Serve content from the cache while regenerating the optimized page version.', 'fastpixel-website-accelerator'); ?></span>
            <?php
        }

        public function fastpixel_display_cached_for_logged_cb($args) {
            // Get the value of the setting we've registered with register_setting()
            $enabled = $this->functions->get_option('fastpixel_display_cached_for_logged');
            ?>
            <input id="fastpixel_display_cached_for_logged" type="checkbox" name="fastpixel_display_cached_for_logged" value="1" <?php echo checked($enabled); ?>> <span class="fastpixel-field-desc"><?php esc_html_e('Display cached pages for logged-in users.', 'fastpixel-website-accelerator'); ?></span>
            <?php
        }

        public function field_speculation_rules_cb($args)
        {
            // Get the value of the setting we've registered with register_setting()
            $speculation_rules = $this->functions->get_option('fastpixel_speculation_rules');
            ?>
            <input id="fastpixel_speculation_rules" type="checkbox" name="fastpixel_speculation_rules" value="1" <?php echo checked($speculation_rules); ?>> <span class="fastpixel-field-desc"><?php printf(esc_html('Enable speculation rules. %s', 'fastpixel-website-accelerator'), '<a href="https://developer.mozilla.org/en-US/docs/Web/API/Speculation_Rules_API" target="_blank">' . esc_html(__('Read more', 'fastpixel-website-accelerator')) . '</a>'); ?></span>
            <?php
        }

        public function field_speculation_mode_cb($args)
        {
            // Get the value of the setting we've registered with register_setting()
            $speculation_mode = $this->functions->get_option('fastpixel_speculation_mode', 'prerender');
            ?>
            <div class="fastpixel-settings-radio"><input id="fastpixel_speculation_mode_prefetch" type="radio" name="fastpixel_speculation_mode" value="prefetch" <?php echo checked($speculation_mode, 'prefetch'); ?> data-depends-on="fastpixel-speculation-rules"> <span class="fastpixel-field-desc"><?php esc_html_e('Prefetch', 'fastpixel-website-accelerator'); ?></span></div>
            <div class="fastpixel-settings-radio"><input id="fastpixel_speculation_mode_prerender" type="radio" name="fastpixel_speculation_mode" value="prerender" <?php echo checked($speculation_mode, 'prerender'); ?> data-depends-on="fastpixel-speculation-rules"> <span class="fastpixel-field-desc"><?php esc_html_e('Prerender', 'fastpixel-website-accelerator'); ?></span></div>
            <?php
        }

        public function field_speculation_eagerness_cb($args)
        {
            // Get the value of the setting we've registered with register_setting()
            $speculation_eagerness = $this->functions->get_option('fastpixel_speculation_eagerness', 'moderate');
            ?>
            <div class="fastpixel-settings-radio"><input id="fastpixel_speculation_eagerness_conservative" type="radio" name="fastpixel_speculation_eagerness" value="conservative" <?php echo checked($speculation_eagerness, 'conservative'); ?> data-depends-on="fastpixel-speculation-rules"> <span class="fastpixel-field-desc"><?php esc_html_e('Conservative', 'fastpixel-website-accelerator'); ?></span></div>
            <div class="fastpixel-settings-radio"><input id="fastpixel_speculation_eagerness_moderate" type="radio" name="fastpixel_speculation_eagerness" value="moderate" <?php echo checked($speculation_eagerness, 'moderate'); ?> data-depends-on="fastpixel-speculation-rules"> <span class="fastpixel-field-desc"><?php esc_html_e('Moderate', 'fastpixel-website-accelerator'); ?></span></div>
            <div class="fastpixel-settings-radio"><input id="fastpixel_speculation_eagerness_eager" type="radio" name="fastpixel_speculation_eagerness" value="eager" <?php echo checked($speculation_eagerness, 'eager'); ?> data-depends-on="fastpixel-speculation-rules"> <span class="fastpixel-field-desc"><?php esc_html_e('Eager', 'fastpixel-website-accelerator'); ?></span></div>
            <?php
        }

        public function field_exclusions_cb($args) {
            // Get the value of the setting we've registered with register_setting()
            $exclusions = stripslashes($this->functions->get_option('fastpixel_exclusions'));
            ?>
            <textarea id="fastpixel_exclusions" name="fastpixel_exclusions" rows="7" cols="100" class="fastpixel-textarea"><?php echo esc_textarea($exclusions); ?></textarea>
            <span class="fastpixel-field-desc fastpixel-textarea-desc"><?php
            /* translators: %s used to display "br" tag, nothing to translate */
            printf(esc_html__('Each URL should be added on a new line and the * character can be used to exclude pages in bulk. %1$s. %2$sExamples: /checkout/%3$s', 'fastpixel-website-accelerator'), '<a href="https://fastpixel.io/docs/fastpixel-exclusions/">' . esc_html(__('Read More', 'fastpixel-website-accelerator')) . '</a>', '<br/>', '<br/><span class="fastpixel-page-excludes-example-2d-row">/page/*</span>'); ?></span>
            <?php
        }

        public function field_exclude_all_params_cb($args)
        {
            // Get the value of the setting we've registered with register_setting()
            $enabled = $this->functions->get_option('fastpixel_exclude_all_params');
            ?>
            <input id="fastpixel_exclude_all_params" type="checkbox" name="fastpixel_exclude_all_params" value="1" <?php echo checked($enabled); ?>> <span class="fastpixel-field-desc"><?php esc_html_e('This option allows you to save disk space. All request parameters are ignored.', 'fastpixel-website-accelerator'); ?></span>
            <?php
        }

        public function field_params_exclusions_cb($args) {
            // Get the value of the setting we've registered with register_setting()
            $params_exclusions = $this->functions->get_option('fastpixel_params_exclusions');
            ?>
            <textarea id="fastpixel_params_exclusions" name="fastpixel_params_exclusions" rows="7" cols="100" class="fastpixel-textarea" data-depends-on="fastpixel-exclude-all-params" data-depends-action="readonly"><?php echo esc_textarea($params_exclusions); ?></textarea>
            <span class="fastpixel-field-desc fastpixel-textarea-desc"><?php
            /* translators: %s used to display "br" tag, nothing to translate */
            printf(esc_html__('Request parameters that should exclude pages from caching. Each parameter should be added on a new line. %1$s.%2$sExample: param_name=param_value, another_param_name', 'fastpixel-website-accelerator'), '<a href="https://fastpixel.io/docs/fastpixel-exclusions/">' . esc_html(__('Read More', 'fastpixel-website-accelerator')) . '</a>', '<br/>'); ?></span>
            <?php
        }
        public function save_options() {
            if (sanitize_text_field($_SERVER['REQUEST_METHOD']) !== 'POST' || (defined('DOING_AJAX') && DOING_AJAX) || 
                check_admin_referer('fastpixel-settings', 'fastpixel-nonce') == false ||
                empty($_POST['fastpixel-action']) || sanitize_key($_POST['fastpixel-action']) != 'save_settings') {
                return;
            }
            $stale = isset($_POST['fastpixel_serve_stale']) && 1 == sanitize_text_field($_POST['fastpixel_serve_stale']) ? 1 : 0;
            $this->functions->update_option('fastpixel_serve_stale', $stale);
            $dcflin = isset($_POST['fastpixel_display_cached_for_logged']) && 1 == sanitize_text_field($_POST['fastpixel_display_cached_for_logged']) ? 1 : 0;
            $this->functions->update_option('fastpixel_display_cached_for_logged', $dcflin);
            //speculation rules
            $sp_rules = isset($_POST['fastpixel_speculation_rules']) && 1 == sanitize_text_field($_POST['fastpixel_speculation_rules']) ? 1 : 0;
            $this->functions->update_option('fastpixel_speculation_rules', $sp_rules);
            $sp_mode = isset($_POST['fastpixel_speculation_mode']) ? sanitize_text_field($_POST['fastpixel_speculation_mode']) : '';
            $this->functions->update_option('fastpixel_speculation_mode', $sp_mode);
            $sp_eagerness = isset($_POST['fastpixel_speculation_eagerness']) ? sanitize_text_field($_POST['fastpixel_speculation_eagerness']) : '';
            $this->functions->update_option('fastpixel_speculation_eagerness', $sp_eagerness);
            //saving excludes and removing existing files if they exist
            $this->save_excludes();
            $exclude_all_params = isset($_POST['fastpixel_exclude_all_params']) && 1 == sanitize_text_field($_POST['fastpixel_exclude_all_params']) ? 1 : 0;
            $this->functions->update_option('fastpixel_exclude_all_params', $exclude_all_params);
            $this->functions->update_option('fastpixel_params_exclusions', sanitize_textarea_field($_POST['fastpixel_params_exclusions']));
            $notices = FASTPIXEL_Notices::get_instance();
            //displaying notice
            $notices->add_flash_notice(esc_html__('Settings have been saved', 'fastpixel-website-accelerator'), 'success', false);
        }

        public function save_excludes() {
            //added extra check to avoid pcp validation notice
            if (check_admin_referer('fastpixel-settings', 'fastpixel-nonce') == false) {
                return;
            }
            //getting home url for proper url generation
            $home_url = rtrim(home_url(), '/');
            //getting old value
            $old_values = $this->functions->get_option('fastpixel_exclusions');
            //getting new(submitted) value
            $new_values = sanitize_textarea_field($_POST['fastpixel_exclusions']);
            //comparing values and saving only when differs
            if ($old_values != $new_values) {
                //updating value
                $this->functions->update_option('fastpixel_exclusions', sanitize_textarea_field($_POST['fastpixel_exclusions']));
                //checking if new value is string
                if (is_string($new_values)) {
                    //making array of values from string
                    $excludes = explode("\r\n", $new_values);
                    //deleting existing cache files for excludes 
                    foreach($excludes as $exclude) {
                        $exclude_url = new FASTPIXEL_Url($home_url . $exclude);
                        if (!empty($exclude)) {
                            $this->functions->delete_cached_files($exclude_url->get_url_path());
                        }
                    }
                }
            }
        }

        public function sanitize_fastpixel_speculation_mode($value) {
            if (!in_array(sanitize_text_field($value), ['prefetch', 'prerender'])) {
                return 'prerender';
            }
            return $value;
        }

        public function sanitize_fastpixel_speculation_eagerness($value)
        {
            if (!in_array(sanitize_text_field($value), ['conservative', 'moderate', 'eager'])) {
                return 'moderate';
            }
            return $value;
        }
    }
    new FASTPIXEL_Tab_Settings();
}
