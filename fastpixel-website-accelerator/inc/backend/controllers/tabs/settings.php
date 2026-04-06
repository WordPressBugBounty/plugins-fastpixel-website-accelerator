<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Tab_Settings')) {
    class FASTPIXEL_Tab_Settings extends FASTPIXEL_UI_Tab
    {
        protected $name = 'Pages';
        protected $slug = 'pages';
        protected $order = 4;
        public $cache_dir;
        protected $config_file;
        protected $api_key;
        protected $purge_all = false;

        public function __construct() {
            parent::__construct();
            $this->name = esc_html__('Pages', 'fastpixel-website-accelerator');
            add_action('fastpixel/tabs/loaded', [$this, 'save_options'], 99);
        }

        public function settings() {
            if (!$this->check_capabilities()) {
                return;
            }

            // Register a new setting for "settings" page.
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_serve_stale', ['type' => 'boolean']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_display_cached_for_logged', ['type' => 'boolean']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_expired_cleanup', ['type' => 'boolean', 'default' => false]);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_expired_cleanup_limit_gb', ['type' => 'string', 'sanitize_callback' => [$this, 'sanitize_cache_limit_gb'], 'default' => (float) FASTPIXEL_DEFAULT_CACHE_LIMIT_GB]);
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
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_cookie_exclusions', ['type' => 'string']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_exclude_all_params', ['type' => 'array']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_params_exclusions', ['type' => 'array']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_registered_params_custom', ['type' => 'string']);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_excluded_post_types', ['type' => 'array', 'sanitize_callback' => [$this, 'sanitize_fastpixel_post_types_exclusion_cb']]);
            register_setting(FASTPIXEL_TEXTDOMAIN, 'fastpixel_always_purge_urls', ['type' => 'array']);
            // Register a new section in the "settings" page.
            add_settings_section(
                'fastpixel_settings_section',
                '',
                false,
                FASTPIXEL_TEXTDOMAIN
            );
            $field_title = esc_html__('Serve Stale', 'fastpixel-website-accelerator');
            add_settings_field(
                'fastpixel_serve_stale',
                $field_title,
                [$this, 'field_serve_stale_cb'],
                FASTPIXEL_TEXTDOMAIN,
                'fastpixel_settings_section',
                [
                    'class' => 'fastpixel-settings-form-row',
                    'label' => $field_title
                ]
            );
            $field_title = esc_html__('Logged-in Users', 'fastpixel-website-accelerator');
            add_settings_field(
                'fastpixel_display_cached_for_logged',
                $field_title,
                [$this, 'fastpixel_display_cached_for_logged_cb'],
                FASTPIXEL_TEXTDOMAIN,
                'fastpixel_settings_section',
                [
                    'class' => 'fastpixel-settings-form-row',
                    'label' => $field_title
                ]
            );
            $field_title = esc_html__('Cache Size Limit', 'fastpixel-website-accelerator');
            add_settings_field(
                'fastpixel_expired_cleanup',
                $field_title,
                [$this, 'field_expired_cleanup_cb'],
                FASTPIXEL_TEXTDOMAIN,
                'fastpixel_settings_section',
                [
                    'class' => 'fastpixel-settings-form-row',
                    'label' => $field_title
                ]
            );
            if (!defined('SPECULATION_RULES_VERSION')) { //displaying speculation rules fields only if speculation rules plugin is not installed/enabled
                $field_title = esc_html__('Speculation Rules', 'fastpixel-website-accelerator');
                add_settings_field(
                    'fastpixel_speculation_rules',
                    $field_title,
                    [$this, 'field_speculation_rules_cb'],
                    FASTPIXEL_TEXTDOMAIN,
                    'fastpixel_settings_section',
                    [
                        'class' => 'fastpixel-settings-form-row',
                        'label' => $field_title
                    ]
                );
                $field_title = esc_html__('Speculation Mode', 'fastpixel-website-accelerator');
                add_settings_field(
                    'fastpixel_speculation_mode',
                    $field_title,
                    [$this, 'field_speculation_mode_cb'],
                    FASTPIXEL_TEXTDOMAIN,
                    'fastpixel_settings_section',
                    [
                        'class' => 'fastpixel-settings-form-row',
                        'label' => $field_title
                    ]
                );
                $field_title = esc_html__('Speculation Eagerness', 'fastpixel-website-accelerator');
                add_settings_field(
                    'fastpixel_speculation_eagerness',
                    $field_title,
                    [$this, 'field_speculation_eagerness_cb'],
                    FASTPIXEL_TEXTDOMAIN,
                    'fastpixel_settings_section',
                    [
                        'class' => 'fastpixel-settings-form-row',
                        'label' => $field_title
                    ]
                );
            }
            $field_title = esc_html__('Page Exclusions', 'fastpixel-website-accelerator');
            add_settings_field(
                'fastpixel_exclusions',
                $field_title,
                [$this, 'field_exclusions_cb'],
                FASTPIXEL_TEXTDOMAIN,
                'fastpixel_settings_section',
                [
                    'class' => 'fastpixel-settings-form-row',
                    'label' => $field_title
                ]
            );
            $field_title = esc_html__('Cookie Exclusions', 'fastpixel-website-accelerator');
            add_settings_field(
                'fastpixel_cookie_exclusions',
                $field_title,
                [$this, 'field_cookie_exclusions_cb'],
                FASTPIXEL_TEXTDOMAIN,
                'fastpixel_settings_section',
                [
                    'class' => 'fastpixel-settings-form-row',
                    'label' => $field_title
                ]
            );
            $field_title = esc_html__('Parameter Exclusions', 'fastpixel-website-accelerator');
            add_settings_field(
                'fastpixel_params_exclusions',
                $field_title,
                [$this, 'field_params_exclusions_cb'],
                FASTPIXEL_TEXTDOMAIN,
                'fastpixel_settings_section',
                [
                    'class' => 'fastpixel-settings-form-row',
                    'label' => $field_title
                ]
            );
            $field_title = esc_html__('Ignore Unregistered Parameters', 'fastpixel-website-accelerator');
            add_settings_field(
                'fastpixel_exclude_all_params',
                $field_title,
                [$this, 'field_exclude_all_params_cb'],
                FASTPIXEL_TEXTDOMAIN,
                'fastpixel_settings_section',
                [
                    'class' => 'fastpixel-settings-form-row',
                    'label' => $field_title
                ]
            );
            $field_title = esc_html__('Post Type Exclusions', 'fastpixel-website-accelerator');
            add_settings_field(
                'fastpixel_excluded_post_types',
                $field_title,
                [$this, 'field_exclude_post_types_cb'],
                FASTPIXEL_TEXTDOMAIN,
                'fastpixel_settings_section',
                [
                    'class' => 'fastpixel-settings-form-row',
                    'label' => $field_title
                ]
            );

            $field_title = esc_html__('Always Purge URL(s)', 'fastpixel-website-accelerator');
            add_settings_field(
                'fastpixel_always_purge_urls',
                $field_title,
                [$this, 'field_always_purge_urls_cb'],
                FASTPIXEL_TEXTDOMAIN,
                'fastpixel_settings_section',
                [
                    'class' => 'fastpixel-settings-form-row',
                    'label' => $field_title
                ]
            );

            // Registering extra settings.
            do_action('fastpixel/settings_tab/init_settings');
        }

        public function field_serve_stale_cb($args) {
            // Get the value of the setting we've registered with register_setting()
            $serve_stale = $this->functions->get_option('fastpixel_serve_stale', false);
            $this->be_functions->print_checkbox([
                'field_name'   => 'fastpixel_serve_stale',
                'checked'      => $serve_stale,
                'label'        => $args['label'],
                'description'  => esc_html__('Serve older cache while the new cache is being generated.', 'fastpixel-website-accelerator')
            ], true);
        }

        public function fastpixel_display_cached_for_logged_cb($args) {
            // Get the value of the setting we've registered with register_setting()
            $enabled = $this->functions->get_option('fastpixel_display_cached_for_logged', false);
            $this->be_functions->print_checkbox([
                'field_name'  => 'fastpixel_display_cached_for_logged',
                'checked'     => $enabled,
                'label'       => $args['label'],
                'description' => esc_html__('Display cached pages for logged-in users.', 'fastpixel-website-accelerator')
            ], true);
        }

        public function field_expired_cleanup_cb($args)
        {
            $enabled = (bool) $this->functions->get_option('fastpixel_expired_cleanup', false);
            $cache_limit_gb = $this->functions->get_option('fastpixel_expired_cleanup_limit_gb', false);
            if ($cache_limit_gb === false) {
                $cache_limit_gb = $this->functions->get_option('fastpixel_expired_cleanup_days', (float) FASTPIXEL_DEFAULT_CACHE_LIMIT_GB);
            }
            $cache_limit_gb = $this->sanitize_cache_limit_gb($cache_limit_gb);
            $description = esc_html__('Choose the maximum cache size you want FastPixel to maintain. As the cache approaches this limit, cleanup becomes more aggressive, purging cache for the least recently accessed pages first.', 'fastpixel-website-accelerator');
            $cron_warning = '';
            if ($this->is_wp_cron_disabled()) {
                $cron_warning = '<div class="fastpixel-cache-size-warning">' . esc_html__('WP-Cron is currently disabled. To make sure the Cache Size Limit works correctly, please enable WP-Cron.', 'fastpixel-website-accelerator') . '</div>';
            }
            $checked = checked($enabled, true, false);
            $switch = sprintf('<switch>
            <label>
                <input type="checkbox" class="fastpixel-switch" id="fastpixel_expired_cleanup" name="fastpixel_expired_cleanup" value="1" %1$s>
                <div class="the_switch">&nbsp;</div>
                %2$s
            </label>
            </switch>',
                $checked,
                esc_html($args['label'])
            );
            $cache_limit_input = sprintf('<span class="fastpixel-input-row">
                <label class="fastpixel-input-label">%1$s</label>
                <input type="number" class="fastpixel-input small-text" step="any" name="fastpixel_expired_cleanup_limit_gb" value="%2$s" />
            </span>',
                esc_html__('Cache Limit (GB)', 'fastpixel-website-accelerator'),
                esc_attr($cache_limit_gb)
            );
            $output = '<setting id="fastpixel_expired_cleanup-container" class="switch"><content>' .
                $switch .
                '<span class="fastpixel-switch-description fastpixel-setting-description">' . $description . '</span>' .
                $cron_warning .
                '<div class="fastpixel-fadein-options"' . ($enabled ? '' : ' style="display:none"') . '>' . $cache_limit_input . '</div>' .
                '</content></setting>';
            echo $output; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        public function sanitize_cache_limit_gb($value): float
        {
            if (is_string($value)) {
                $value = str_replace(',', '.', trim($value));
            }

            if (!is_numeric($value)) {
                return (float) FASTPIXEL_DEFAULT_CACHE_LIMIT_GB;
            }

            $value = (float) $value;
            if ($value <= 0) {
                return (float) FASTPIXEL_DEFAULT_CACHE_LIMIT_GB;
            }

            return $value;
        }

        protected function is_wp_cron_disabled(): bool
        {
            return defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        }

        public function field_speculation_rules_cb($args)
        {
            // Get the value of the setting we've registered with register_setting()
            $speculation_rules = $this->functions->get_option('fastpixel_speculation_rules');
            /* translators: %1$s used to display "a" tag, nothing to translate */
            $description = sprintf(esc_html__('Enable speculation rules. %1$s.', 'fastpixel-website-accelerator'), sprintf('<a href="https://fastpixel.io/docs/what-are-the-speculation-rules/" target="_blank">%1$s</a>', esc_html__('Read more', 'fastpixel-website-accelerator')));
            $checked = checked($speculation_rules, true, false);
            $field_name = 'fastpixel_speculation_rules';
            $label = $args['label'];
            $disabled = (!empty($args['disabled']) && true === $$args['disabled']) ? 'disabled' : '';
            if (empty($field_name)) {
                return false;
            }
            $switch = sprintf('<switch>
            <label>
                <input type="checkbox" class="fastpixel-switch" id="%1$s" name="%1$s" value="1" %2$s %3$s>
                <div class="the_switch">&nbsp;</div>
                %4$s
            </label>
            </switch>
            <span class="fastpixel-switch-description fastpixel-setting-description">%5$s</span>', $field_name, $checked, $disabled, $label, $description);

            $speculation_mode = $this->functions->get_option('fastpixel_speculation_mode', 'prerender');
            $mode_html = '<h4 class="fastpixel-switch-option-subtitle fastpixel-setting-subsection-title">'. esc_html__('Speculation Mode', 'fastpixel-website-accelerator') .'</h4>';
            $mode_html .= '<div class="fastpixel-settings-radio"><input id="fastpixel_speculation_mode_prefetch" type="radio" name="fastpixel_speculation_mode" value="prefetch" ' . checked($speculation_mode, 'prefetch', false) . ' data-depends-on="fastpixel-speculation-rules"> <span class="fastpixel-field-desc">' . esc_html__('Prefetch', 'fastpixel-website-accelerator') . '</span></div>';
            $mode_html .= '<div class="fastpixel-settings-radio"><input id="fastpixel_speculation_mode_prerender" type="radio" name="fastpixel_speculation_mode" value="prerender" ' . checked($speculation_mode, 'prerender', false) . ' data-depends-on="fastpixel-speculation-rules"> <span class="fastpixel-field-desc">' . esc_html__('Prerender', 'fastpixel-website-accelerator') . '</span></div>';

            $speculation_eagerness = $this->functions->get_option('fastpixel_speculation_eagerness', 'moderate');
            $eagerness_html = '<h4 class="fastpixel-switch-option-subtitle fastpixel-setting-subsection-title">' . esc_html__('Speculation Eagerness', 'fastpixel-website-accelerator') . '</h4>';
            $eagerness_html .= '<div class="fastpixel-settings-radio"><input id="fastpixel_speculation_eagerness_conservative" type="radio" name="fastpixel_speculation_eagerness" value="conservative" ' . checked($speculation_eagerness, 'conservative', false) . 'data-depends-on="fastpixel-speculation-rules"> <span class="fastpixel-field-desc">' . esc_html__('Conservative', 'fastpixel-website-accelerator') . '</span></div>';
            $eagerness_html .= '<div class="fastpixel-settings-radio"><input id="fastpixel_speculation_eagerness_moderate" type="radio" name="fastpixel_speculation_eagerness" value="moderate" '. checked($speculation_eagerness, 'moderate', false) . ' data-depends-on="fastpixel-speculation-rules"> <span class="fastpixel-field-desc">' . esc_html__('Moderate', 'fastpixel-website-accelerator') . '</span></div>';
            $eagerness_html .= '<div class="fastpixel-settings-radio"><input id="fastpixel_speculation_eagerness_eager" type="radio" name="fastpixel_speculation_eagerness" value="eager" ' . checked($speculation_eagerness, 'eager', false) . ' data-depends-on="fastpixel-speculation-rules"> <span class="fastpixel-field-desc">' . esc_html__('Eager', 'fastpixel-website-accelerator') . '</span></div>';

            $output = '<setting id="' . $field_name . '-container" class="switch"><content>' . $switch . '<div class="fastpixel-fadein-options" ' . ($speculation_rules ? '' : 'style="display:none"') . '>'. $mode_html . $eagerness_html . '</div></content></setting>';
            echo $output; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        public function field_speculation_mode_cb($args)
        {
            return;
        }

        public function field_speculation_eagerness_cb($args)
        {
            return;
        }

        public function field_exclusions_cb($args) {
            // Get the value of the setting we've registered with register_setting()
            $exclusions = stripslashes($this->functions->get_option('fastpixel_exclusions'));
            /* translators: %1$s used to display "read more" link, %2$s for br tag, %3$s for new line example, nothing to translate */
            $description = sprintf(esc_html__('Each URL should be added on a new line and the * character can be used to exclude pages in bulk. %1$s. %2$sExamples: /checkout/%3$s', 'fastpixel-website-accelerator'), '<a href="https://fastpixel.io/docs/fastpixel-exclusions/" target="_blank">' . esc_html(__('Read More', 'fastpixel-website-accelerator')) . '</a>', '<br/>', '<br/><span class="fastpixel-page-excludes-example-2d-row">/page/*</span>');
            $this->be_functions->print_textarea([
                'field_name'  => 'fastpixel_exclusions',
                'field_value' => $exclusions,
                'label'       => $args['label'],
                'description' => $description
            ], true);
        }

        public function field_cookie_exclusions_cb($args)
        {
            $cookie_exclusions = (string) $this->functions->get_option('fastpixel_cookie_exclusions', '');
            $description = esc_html__('Cookies that should bypass the cache for the current request. Add one cookie per line.', 'fastpixel-website-accelerator');
            $this->be_functions->print_textarea([
                'field_name'  => 'fastpixel_cookie_exclusions',
                'field_value' => $cookie_exclusions,
                'label'       => $args['label'],
                'description' => $description,
                'placeholder' => "woocommerce_items_in_cart\nwp_woocommerce_session_1"
            ], true);
        }

        public function field_exclude_all_params_cb($args)
        {
            // Get the value of the settings we've registered with register_setting()
            $enabled_ignore = $this->functions->get_option('fastpixel_exclude_all_params');
            $custom_params  = $this->functions->get_option('fastpixel_registered_params_custom', '');
            global $wp;
            $registered_params = [];
            if (isset($wp) && is_object($wp) && is_array($wp->query_vars)) {
                $registered_params = array_keys($wp->query_vars);
            }
            // Merge custom params into display list (whitelist view).
            if (!empty($custom_params) && is_string($custom_params)) {
                $lines = preg_split('/\r\n|\r|\n/', $custom_params);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line !== '' && !in_array($line, $registered_params, true)) {
                        $registered_params[] = $line;
                    }
                }
            }
            if (!empty($registered_params)) {
                sort($registered_params);
            }

            $ignore_description = esc_html__(
                'When enabled, the system serves cached pages while ignoring any unregistered request parameters. Pages are cached using URLs where only allowed parameters affect the cache key, reducing disk usage, lowering unnecessary pageviews, and improving the cache hit ratio',
                'fastpixel-website-accelerator'
            );

            ?>
            <setting id="fastpixel_exclude_all_params-container" class="switch">
                <content>
                    <switch>
                        <label>
                            <input type="checkbox"
                                   class="fastpixel-switch"
                                   id="fastpixel_exclude_all_params"
                                   name="fastpixel_exclude_all_params"
                                   value="1" <?php checked((bool) $enabled_ignore, true); ?>>
                            <div class="the_switch">&nbsp;</div>
                            <?php echo esc_html($args['label']); ?>
                        </label>
                    </switch>
                    <span class="fastpixel-switch-description">
                        <?php echo wp_kses_post($ignore_description); ?>
                    </span>

                    <div class="fastpixel-registered-params-group"<?php echo $enabled_ignore ? '' : ' style="display:none"'; ?>>
                        <?php
                        $custom_description = sprintf(
                            esc_html__(
                                'These registered query parameters are taken into account when caching the page. They are registered by the theme or various plugins. If any other query parameter which is not registered (so it doesn\'t appear here) modifies the contents of the page, you can manually add it here.
                                 %1$sExample: param_name=param_value, another_param_name%2$s%3$s',
                                'fastpixel-website-accelerator'
                            ),
                            '<br/>',
                            '<br/>',
                            '<span class="fastpixel-registered-params-example-indent">third_param_name</span>'
                        );
                        $this->be_functions->print_textarea([
                            'field_name'  => 'fastpixel_registered_params_custom',
                            'field_value' => $custom_params,
                            'label'       => esc_html__('Registered Parameters', 'fastpixel-website-accelerator'),
                            'description' => $custom_description,
                            'data'        => []
                        ], true);
                        ?>
                    </div>
                </content>
            </setting>
            <?php
        }

        public function field_params_exclusions_cb($args) {
            // Get the value of the setting we've registered with register_setting()
            $params_exclusions = $this->functions->get_option('fastpixel_params_exclusions');
            /* translators: %s used to display "br" tag, nothing to translate */
            $description = sprintf(esc_html__('Request parameters that should exclude pages from caching. Each parameter should be added on a new line. %1$s.%2$sExample: param_name=param_value, another_param_name', 'fastpixel-website-accelerator'), '<a href="https://fastpixel.io/docs/fastpixel-exclusions/" target="_blank">' . esc_html(__('Read More', 'fastpixel-website-accelerator')) . '</a>', '<br/>');
            $this->be_functions->print_textarea([
                'field_name'  => 'fastpixel_params_exclusions',
                'field_value' => $params_exclusions,
                'label'       => $args['label'],
                'description' => $description,
                'data'        => []
            ], true);
        }

        public function field_exclude_post_types_cb($args)
        {
            // Get the value of the setting we've registered with register_setting()
            $post_types = get_post_types(['public' => true], 'objects');
            $post_types_removed = apply_filters('fastpixel/settings_tab/disabled_post_types', ['attachment']);
            foreach($post_types as $post_type_name => $post_type) {
                if (in_array($post_type_name, $post_types_removed)) { //removing attachment
                    unset($post_types[$post_type_name]);
                }
            }
            $excluded = $this->functions->get_option('fastpixel_excluded_post_types', []);
            ?>
            <setting id="fastpixel_excluded_post_types-container" class="fastpixel-excluded-post-types-container">
                <content>
                    <name><?php echo esc_attr($args['label']); ?></name>
                    <div class="fastpixel-exclude-post-types-row">
                        <div class="fastpixel-exclude-post-types-column">
                            <p><?php esc_html_e('Post Types:', 'fastpixel-website-accelerator'); ?></p>
                            <select id="fastpixel_exclude_post_types_list" class="fastpixel-select" name="fastpixel_exclude_post_types_list" multiple>
                                <?php foreach($post_types as $post_type) :
                                    if (!in_array($post_type->name, $excluded)) : ?>
                                        <option value="<?php echo esc_html($post_type->name); ?>" <?php selected(in_array($post_type->name, []), true); ?>><?php echo esc_html($post_type->label); ?></option>
                                <?php   endif;
                                endforeach; ?>
                            </select>
                        </div>
                        <div class="fastpixel-exclude-post-types-column fastpixel-exclude-post-types-actions">
                            <p>&nbsp;</p>
                            <button id="fastpixel-exclude-post-types-move-right">>>></button>
                            <button id="fastpixel-exclude-post-types-move-left"><<<</button>
                        </div>
                        <div class="fastpixel-exclude-post-types-column">
                            <p><?php esc_html_e('Excluded Post Types:', 'fastpixel-website-accelerator'); ?></p>
                            <select id="fastpixel_excluded_post_types" class="fastpixel-select" name="fastpixel_excluded_post_types[]" multiple>
                                <?php foreach ($excluded as $post_type_name):
                                    foreach ($post_types as $post_type) :
                                        if ($post_type->name == $post_type_name) : ?>
                                            <option value="<?php echo esc_html($post_type->name); ?>" <?php selected(in_array($post_type->name, []), true); ?>><?php echo esc_html($post_type->label); ?></option>
                                <?php   endif;
                                    endforeach;
                                endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="fastpixel-textarea-description"><?php //esc_html_e('Post types excluded from cache.', 'fastpixel-website-accelerator'); ?></div>
                </content>
            </setting>
            <?php
        }

        public function field_always_purge_urls_cb($args)
        {
            // Get the value of the setting we've registered with register_setting()
            $urls = $this->functions->get_option('fastpixel_always_purge_urls');
            $description = esc_html__('Page URLs that should always be purged from the cache whenever posts or plugins are added, edited, or deleted. Each URL should be added on a new line.', 'fastpixel-website-accelerator');
            $description .= '<br/>';
            $description .= esc_html__('Example: /blog/', 'fastpixel-website-accelerator');
            $this->be_functions->print_textarea([
                'field_name'  => 'fastpixel_always_purge_urls',
                'field_value' => $urls,
                'label'       => $args['label'],
                'description' => $description,
                'data'        => []
            ], true);
        }

        public function save_options() {
            if (!$this->validate_settings_save_request()) {
                return;
            }
            $stale = isset($_POST['fastpixel_serve_stale']) && 1 == sanitize_text_field($_POST['fastpixel_serve_stale']) ? 1 : 0;
            $this->functions->update_option('fastpixel_serve_stale', $stale);
            $dcflin = isset($_POST['fastpixel_display_cached_for_logged']) && 1 == sanitize_text_field($_POST['fastpixel_display_cached_for_logged']) ? 1 : 0;
            $this->functions->update_option('fastpixel_display_cached_for_logged', $dcflin);
            $expired_cleanup = isset($_POST['fastpixel_expired_cleanup']) && 1 == sanitize_text_field($_POST['fastpixel_expired_cleanup']) ? 1 : 0;
            $this->functions->update_option('fastpixel_expired_cleanup', $expired_cleanup);
            $expired_cleanup_limit_gb = isset($_POST['fastpixel_expired_cleanup_limit_gb']) ? $this->sanitize_cache_limit_gb($_POST['fastpixel_expired_cleanup_limit_gb']) : (float) FASTPIXEL_DEFAULT_CACHE_LIMIT_GB;
            $this->functions->update_option('fastpixel_expired_cleanup_limit_gb', $expired_cleanup_limit_gb);
            $this->functions->delete_option('fastpixel_expired_cleanup_days');
            //speculation rules
            $sp_rules = isset($_POST['fastpixel_speculation_rules']) && 1 == sanitize_text_field($_POST['fastpixel_speculation_rules']) ? 1 : 0;
            $this->functions->update_option('fastpixel_speculation_rules', $sp_rules);
            $sp_mode = isset($_POST['fastpixel_speculation_mode']) ? sanitize_text_field($_POST['fastpixel_speculation_mode']) : '';
            $this->functions->update_option('fastpixel_speculation_mode', $sp_mode);
            $sp_eagerness = isset($_POST['fastpixel_speculation_eagerness']) ? sanitize_text_field($_POST['fastpixel_speculation_eagerness']) : '';
            $this->functions->update_option('fastpixel_speculation_eagerness', $sp_eagerness);
            //saving excludes and removing existing files if they exist
            $this->save_excludes();
            //saving purge urls
            $this->save_purge_urls();
            $exclude_all_params = isset($_POST['fastpixel_exclude_all_params']) && 1 == sanitize_text_field($_POST['fastpixel_exclude_all_params']) ? 1 : 0;
            $this->functions->update_option('fastpixel_exclude_all_params', $exclude_all_params);
            $this->functions->update_option('fastpixel_cookie_exclusions', sanitize_textarea_field($_POST['fastpixel_cookie_exclusions'] ?? ''));
            $registered_params_custom = isset($_POST['fastpixel_registered_params_custom']) ? sanitize_textarea_field($_POST['fastpixel_registered_params_custom']) : '';
            $this->functions->update_option('fastpixel_registered_params_custom', $registered_params_custom);
            $this->functions->update_option('fastpixel_params_exclusions', sanitize_textarea_field($_POST['fastpixel_params_exclusions']));
            $excluded_post_types = [];
            if (!empty($_POST['fastpixel_excluded_post_types']) && is_array($_POST['fastpixel_excluded_post_types'])) {
                foreach($_POST['fastpixel_excluded_post_types'] as $post_type) {
                    $excluded_post_types[] = sanitize_textarea_field($post_type);
                }
            }
            $this->functions->update_option('fastpixel_excluded_post_types', $excluded_post_types);
            do_action('fastpixel/settings_tab/save_options');
            //displaying notice
            $notices = FASTPIXEL_Notices::get_instance();
            $purge_all = apply_filters('fastpixel/settings_tab/purge_all', $this->purge_all);
            $text_cleared = '';
            if ($purge_all) {
                $backend_cache = FASTPIXEL_Backend_Cache::get_instance();
                $backend_cache->purge_all();
                /* translators: for %1$s should be link to status page with text "here" which can be translated separately */
                $text_cleared = sprintf(esc_html__('Cache has been cleared! Pages are now being optimized and cached - this may take a few minutes. Check the status %1$s.', 'fastpixel-website-accelerator'), '<a href="' . admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN . '-settings#cache-status') . '">' . esc_html__('here', 'fastpixel-website-accelerator') . '</a>');
            }
            /* translators: for %1$s text is used which tells that cache was cleared and pages are being generated. This text is translated separately. */
            $notices->add_flash_notice(sprintf(esc_html__('Settings saved successfully. %1$s', 'fastpixel-website-accelerator'), $text_cleared), 'success', false);
            if ($expired_cleanup && $this->is_wp_cron_disabled()) {
                $notices->add_flash_notice(esc_html__('FastPixel Website Accelerator: Cache Size Limit is enabled, but WP-Cron is disabled. Please enable WP-Cron for automatic cleanup to run.', 'fastpixel-website-accelerator'), 'warning', false);
            }
        }

        protected function save_excludes() {
            //added extra check to avoid pcp validation notice
            if (!$this->validate_settings_save_request(false)) {
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

        protected function save_purge_urls()
        {
            //added extra check to avoid pcp validation notice
            if (!$this->validate_settings_save_request(false)) {
                return;
            }
            //getting old value
            $old_values = $this->functions->get_option('fastpixel_always_purge_urls');
            //getting new(submitted) value
            $new_values = sanitize_textarea_field($_POST['fastpixel_always_purge_urls']);
            //comparing values and saving only when differs
            if ($old_values != $new_values) {
                //updating value
                $this->functions->update_option('fastpixel_always_purge_urls', sanitize_textarea_field($_POST['fastpixel_always_purge_urls']));
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

        public function sanitize_fastpixel_post_types_exclusion_cb($value)
        {
            $old_value = $this->functions->get_option('fastpixel_excluded_post_types');
            if (is_array($value) && is_array($old_value)) {
                if (count($value) != count($old_value)) {
                    $this->purge_all = true;
                } else {
                    $diff = array_diff($old_value, $value);
                    if (!empty($diff)) {
                        $this->purge_all = true;
                    }
                }
            }
            return $value;
        }
    }
    new FASTPIXEL_Tab_Settings();
}
