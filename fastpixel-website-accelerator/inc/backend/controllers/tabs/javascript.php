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
            add_settings_field(
                'fastpixel_javascript_optimization',
                esc_html__('Optimization', 'fastpixel-website-accelerator'),
                [$this, 'field_fastpixel_javascript_optimization_cb'],
                FASTPIXEL_TEXTDOMAIN . '-javascript',
                'fastpixel_settings_section-javascript'
            );
            add_settings_field(
                'fastpixel_javascript_excludes',
                esc_html__('Exclusions', 'fastpixel-website-accelerator'),
                [$this, 'field_fastpixel_javascript_excludes_cb'],
                FASTPIXEL_TEXTDOMAIN . '-javascript',
                'fastpixel_settings_section-javascript'
            );
            add_settings_field(
                'fastpixel_javascript_excludes_regexp',
                esc_html__('RegExp Exclusions', 'fastpixel-website-accelerator'),
                [$this, 'field_fastpixel_javascript_excludes_regexp_cb'],
                FASTPIXEL_TEXTDOMAIN . '-javascript',
                'fastpixel_settings_section-javascript'
            );
            add_settings_field(
                'fastpixel_javascript_exclude_gdpr',
                esc_html__('GDPR', 'fastpixel-website-accelerator'),
                [$this, 'field_fastpixel_javascript_exclude_gdpr_cb'],
                FASTPIXEL_TEXTDOMAIN . '-javascript',
                'fastpixel_settings_section-javascript'
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
            ?>
            <div class="fastpixel-select-with-description fastpixel-select">
                <div class="fastpixel-row">
                    <select id="fastpixel_javascript_optimization" name="fastpixel_javascript_optimization">
                        <option value="1" <?php echo $option == 1 ? 'selected="selected"' : ''; ?>><?php esc_html_e('Optimize JavaScript', 'fastpixel-website-accelerator'); ?></option>
                        <option value="2" <?php echo $option == 2 ? 'selected="selected"' : ''; ?>><?php esc_html_e('Delay non-critical JavaScript', 'fastpixel-website-accelerator'); ?></option>
                        <option value="3" <?php echo $option == 3 ? 'selected="selected"' : ''; ?>><?php esc_html_e('Do not optimize JavaScript', 'fastpixel-website-accelerator'); ?></option>
                    </select>
                    <div class="field-description">
                        <span class="optimization-description fastpixel-desc-hidden" data-value="1"><?php esc_html_e('All scripts are optimized and run as on the original page.', 'fastpixel-website-accelerator'); ?></span>
                        <span class="optimization-description fastpixel-desc-hidden" data-value="2"><?php esc_html_e('All scripts are optimized and delayed, except necessary scripts like GDPR.', 'fastpixel-website-accelerator'); ?></span>
                        <span class="optimization-description fastpixel-desc-hidden" data-value="3"><?php esc_html_e('No JavaScript optimization is performed, the scripts run exactly as on the original page.', 'fastpixel-website-accelerator'); ?></span>
                    </div>
                </div>
            </div>
            <?php
        }
        public function field_fastpixel_javascript_excludes_cb($args) {
            // Get the value of the setting we've registered with register_setting()
            $excludes = stripslashes($this->functions->get_option('fastpixel_javascript_excludes'));
            ?>
            <textarea id="fastpixel_javascript_excludes" name="fastpixel_javascript_excludes" rows="7" cols="100" data-depends-on="fastpixel-javascript-optimization" class="fastpixel-textarea"><?php echo esc_textarea($excludes); ?></textarea>
            <span class="fastpixel-field-desc fastpixel-textarea-desc"><?php printf( esc_html('Scripts that should be excluded from caching. Each script URL should be added on a new line. %1$s', 'fastpixel-website-accelerator'), '<a href="https://fastpixel.io/docs/fastpixel-exclusions/">' . esc_html(__('Read More', 'fastpixel-website-accelerator')) . '</a>'); ?></span>
            <span class="fastpixel-field-desc fastpixel-textarea-desc"><?php esc_html_e('Example:', 'fastpixel-website-accelerator');?>&nbsp;<?php echo esc_url(home_url('/wp-includes/js/jquery/jquery.js')); ?></span>
            <?php
        }
        public function field_fastpixel_javascript_excludes_regexp_cb($args) {
            // Get the value of the setting we've registered with register_setting()
            $excludes = stripslashes($this->functions->get_option('fastpixel_javascript_excludes_regexp'));
            ?>
            <textarea id="fastpixel_javascript_excludes_regexp" name="fastpixel_javascript_excludes_regexp" rows="7" cols="100" data-depends-on="fastpixel-javascript-optimization" class="fastpixel-textarea"><?php echo esc_textarea($excludes); ?></textarea>
            <span class="fastpixel-field-desc fastpixel-textarea-desc"><?php
            /* translators: %s used to display "br" tag, nothing to translate */
            printf(esc_html__('Specify URLs, keywords or regular expressions that can identify inline or src of JavaScript to exclude from deferred execution (one per line). %1$s.%2$s
            This is a good place to put your menus, carousels in the hero area, GA and GTM.', 'fastpixel-website-accelerator'), '<a href="https://fastpixel.io/docs/fastpixel-exclusions/">' . esc_html(__('Read More', 'fastpixel-website-accelerator')) . '</a>', '<br/><br/>'); ?></span>
            <span class="fastpixel-field-desc fastpixel-textarea-desc"><?php esc_html_e('Examples:', 'fastpixel-website-accelerator'); ?>&nbsp;<?php esc_html_e('fastpixel(\.min)?\.js'); ?><br/><span class="fastpixel-javascript-regexp-excludes-example-2d-row"><?php esc_html_e('fastpixel\.js\?ver=2\.8\.4'); ?></span></span>
            <?php
        }
        public function field_fastpixel_javascript_exclude_gdpr_cb($args) {
            // Get the value of the setting we've registered with register_setting()
            $exclude = $this->functions->get_option('fastpixel_javascript_exclude_gdpr');
            ?>
            <input type="checkbox" id="fastpixel_javascript_exclude_gdpr" name="fastpixel_javascript_exclude_gdpr" value="1" <?php echo checked($exclude); ?> data-depends-on="fastpixel-javascript-optimization" />
            <span class="fastpixel-field-desc"><?php esc_html_e('Exclude GDPR/Cookie popups', 'fastpixel-website-accelerator'); ?></span>
            <?php
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
