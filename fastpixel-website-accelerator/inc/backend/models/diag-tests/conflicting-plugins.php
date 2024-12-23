<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Diag_Test_Conflicting_Plugins')) {
    class FASTPIXEL_Diag_Test_Conflicting_Plugins extends FASTPIXEL_Diag_Test 
    {
        protected $order_id = 18;
        protected $name = 'Conflicting Plugins';
        protected $array_result = true;
        protected $conflicting_mu_plugins = [
            'Endurance Page Cache' => 'endurance-page-cache.php'
        ];
        protected $conflicting_plugins = [
            'Breeze'                               => 'breeze/breeze.php',
            'WP Rocket'                            => 'wp-rocket/wp-rocket.php',
            'W3 Total Cache'                       => 'w3-total-cache/w3-total-cache.php',
            'WP Fastest Cache'                     => 'wp-fastest-cache/wpFastestCache.php',
            'PhastPress'                           => 'phastpress/phastpress.php',
            'Perfmatters'                          => '',
            'WP Super Cache'                       => 'wp-super-cache/wp-cache.php',
            'LiteSpeed Cache'                      => 'litespeed-cache/litespeed-cache.php',
            'Swift Performance Lite'               => 'swift-performance-lite/performance.php',
            'Swift Performance'                    => 'swift-performance/performance.php',
            'PageSpeed Ninja'                      => 'psn-pagespeed-ninja/pagespeedninja.php',
            'Autoptimize'                          => 'autoptimize/autoptimize.php',
            'Pegasaas Accelerator WP'              => 'pegasaas-accelerator-wp/pegasaas-accelerator-wp.php',
            'Hummingbird'                          => 'hummingbird-performance/wp-hummingbird.php',
            'Smush'                                => 'wp-smushit/wp-smush.php',
            'Comet Cache'                          => 'comet-cache/comet-cache.php',
            'WP-Optimize - Clean, Compress, Cache' => 'wp-optimize/wp-optimize.php',
            'BJ Lazy Load'                         => '',
            'WP Meteor'                            => 'wp-meteor/wp-meteor.php',
            'ShortPixel Adaptive Images'           => 'shortpixel-adaptive-images/short-pixel-ai.php',
            'Speed Optimizer'                      => 'sg-cachepress/sg-cachepress.php',
            'Image optimization service by Optimole' => 'optimole-wp/optimole-wp.php',
            'Jetpack Boost'                          => 'jetpack-boost/jetpack-boost.php',
            'Asset CleanUp: Page Speed Booster'      => 'wp-asset-clean-up/wpacu.php',
        ];
        protected $conflicting_plugins_tested = [];

        public function __construct()
        {
            parent::__construct();
        }

        public function test() {
            if (!function_exists('get_plugins')) {
                include_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $mu_plugins = [];
            $conflicting_mu_plugin_names = array_keys($this->conflicting_mu_plugins);
            $conflicting_mu_plugin_files = array_values($this->conflicting_mu_plugins);
            $all_mu_plugins = get_mu_plugins();
            foreach ($all_mu_plugins as $filename => $plugin_info) {
                if (in_array($plugin_info['Title'], $conflicting_mu_plugin_names) || in_array($filename, $conflicting_mu_plugin_files)) {
                    //getting ours plugin name by filename
                    $plugin_key = array_search($filename, $this->conflicting_mu_plugins);
                    $mu_plugins[$plugin_key] = [
                        'status' => true,
                        'file'   => $filename
                    ];
                }
            }
            //must use plugins
            if (class_exists('Endurance_Page_Cache')) { // Endurance
                $level = absint(get_option('endurance_cache_level'));
                if ($level > 0) {
                    $mu_plugins["Endurance Page Cache"]['status'] = false;
                    $mu_plugins["Endurance Page Cache"]['status_text'] = '(Must-use plugin, Cache Level ' . $level . ')';
                }
            }
            foreach ($mu_plugins as $plugin_name => $mu_passed) {
                $mu_plugins[$plugin_name]['display_status'] = '<div><span class="deactivate-text"><strong class="' . esc_attr($mu_passed['status'] == true ? 'passed' : 'failed') . '">' . ($mu_passed['status'] == true ? esc_html__('DEACTIVATED', 'fastpixel-website-accelerator') : esc_html__('ACTIVE', 'fastpixel-website-accelerator')) . '</strong></span>&nbsp;' . (isset($mu_plugins[$plugin_name]['status_text']) ? esc_html($mu_plugins[$plugin_name]['status_text']) : '') . '</div>';
            }



            $all_plugins = get_plugins();
            $conflicting_plugin_names = array_keys($this->conflicting_plugins);
            $conflicting_plugin_files = array_values($this->conflicting_plugins);
            $plugins = [];
            foreach ($all_plugins as $filename => $plugin_info) {
                if (in_array($plugin_info['Title'], $conflicting_plugin_names) || in_array($filename, $conflicting_plugin_files)) {
                    //getting ours plugin name by filename
                    $plugin_key = array_search($filename, $this->conflicting_plugins);
                    $plugins[$plugin_key] = [
                        'status' => true,
                        'file'   => $filename
                    ];
                }
            }

            if (defined('BREEZE_PLUGIN_DIR')) { // Breeze cache plugin
                $plugins['Breeze']['status'] = false;
            }
            if (defined('WP_ROCKET_VERSION')) { // WP-Rocket
                $plugins['WP Rocket']['status'] = false;
            }

            if (defined('W3TC')) { // W3 Total Cache
                $plugins["W3 Total Cache"]['status'] = false;
            }
            if (defined('WPFC_MAIN_PATH')) { // WP Fastest Cache
                $plugins["WP Fastest Cache"]['status'] = false;
            }
            if (defined('PHASTPRESS_VERSION')) { // PhastPress
                $plugins["PhastPress"]['status'] = false;
            }
            if (defined('WPCACHEHOME') && function_exists("wp_cache_phase2")) { // WP Super Cache
                $plugins["WP Super Cache"]['status'] = false;
            }
            if (defined('LSCACHE_ADV_CACHE') || defined('LSCWP_DIR')) { // LiteSpeed Cache
                $plugins["LiteSpeed Cache"]['status'] = false;
            }
            if (class_exists('Swift_Performance') || class_exists('Swift_Performance_Lite')) { // Swift Performance
                $plugins["Swift Performance"]['status'] = false;
            }
            if (class_exists('PagespeedNinja')) { // PageSpeed Ninja
                $plugins["PageSpeed Ninja"]['status'] = false;
            }
            if (defined('AUTOPTIMIZE_PLUGIN_VERSION')) { // Autoptimize
                $plugins["Autoptimize"]['status'] = false;
            }
            if (defined('PEGASAAS_ACCELERATOR_VERSION')) { // Pegasaas Accelerator WP
                $plugins["Pegasaas Accelerator WP"]['status'] = false;
            }
            if (defined('WPHB_VERSION')) { // Hummingbird
                $plugins["Hummingbird"]['status'] = false;
            }
            if (defined('WP_SMUSH_VERSION')) { // Smush by WPMU DEV
                if (class_exists('Smush\\Core\\Settings') && defined('WP_SMUSH_PREFIX')) {
                    $smush_lazy = Smush\Core\Settings::get_instance()->get('lazy_load');
                    if ($smush_lazy) {
                        $plugins["Smush Lazy Load"]['status'] = false;
                    } else {
                        $plugins["Smush Lazy Load"]['status'] = true;
                    }
                } else {
                    $plugins["Smush"]['status'] = false;
                }
            }
            if (defined('COMET_CACHE_PLUGIN_FILE')) { // Comet Cache by WP Sharks
                $plugins["Comet Cache"]['status'] = false;
            }
            if (defined('WPO_VERSION') && class_exists('WPO_Cache_Config')) { // WP Optimize
                $wpo_cache_config = \WPO_Cache_Config::instance();
                if ($wpo_cache_config->get_option('enable_page_caching', false)) {
                    $plugins["WP Optimize page caching"]['status'] = false;
                }
            }
            if (class_exists('BJLL')) { // BJ Lazy Load
                $plugins["BJ Lazy Load"]['status'] = false;
            }
            if (defined('WPMETEOR_TEXTDOMAIN')) { // WP METEOR
                $plugins["WP Meteor"]['status'] = false;
            }
            if (class_exists('ShortPixelAI') || defined('SHORTPIXEL_AI_PLUGIN_FILE')) { // Shortpixel AI
                $plugins["ShortPixel Adaptive Images"]['status'] = false;
            }

            if (defined('SiteGround_Optimizer\VERSION')) { // SiteGround
                $sg_cache_enabled = get_option('siteground_optimizer_enable_cache', false);
                if ($sg_cache_enabled) { 
                    $plugins["Speed Optimizer"]['status'] = false;
                }
            }

            if (is_plugin_active($this->conflicting_plugins["Image optimization service by Optimole"]) || defined('OPTML_BASEFILE')) { // Image Optimization by Optimole
                $plugins["Image optimization service by Optimole"]['status'] = false;
            }

            if (is_plugin_active($this->conflicting_plugins["Jetpack Boost"]) || defined('JETPACK_BOOST_VERSION')) {
                $plugins["Jetpack Boost"]['status'] = false;
            }

            if (is_plugin_active($this->conflicting_plugins["Asset CleanUp: Page Speed Booster"]) || defined('WPACU_PLUGIN_VERSION')) { 
                $plugins["Asset CleanUp: Page Speed Booster"]['status'] = false;
            }

            if (is_plugin_active($this->conflicting_plugins["WP-Optimize - Clean, Compress, Cache"]) || defined('WPO_VERSION')) { // WP-Optimize
                $plugins["WP-Optimize - Clean, Compress, Cache"]['status'] = false;
            }
            
            $plugin_id = 0;
            foreach ($plugins as $plugin_name => $passed) {
                $plugin_id++;
                $action_deactivate = '';
                if (!$passed['status']) {
                    if (isset($passed['file']) && !empty($passed['file']) && current_user_can('deactivate_plugin', $passed['file'])) {
                        $url = 'plugins.php?action=deactivate&plugin=' . urlencode($passed['file']) . '&plugin_status=all&paged=1';
                        if (is_multisite()) {
                            $abs_url = network_admin_url($url);
                        } else {
                            $abs_url = admin_url($url);
                        }
                        $action_deactivate = sprintf(
                            ' <a href="%1$s" class="button button-primary plugin-deactivation-btn" data-plugin-file="%2$s" data-plugin-id="%3$s">%4$s</a>',
                            esc_url(wp_nonce_url($abs_url, 'deactivate-plugin_' . $passed['file'])),
                            esc_html($passed['file']),
                            esc_html($plugin_id),
                            esc_html__('DEACTIVATE', 'fastpixel-website-accelerator')
                        );
                    }
                }
                $plugins[$plugin_name]['display_status'] = '<div id="plugin-action-' . esc_attr($plugin_id) . '"><span class="deactivate-text"><strong class="' . esc_attr($passed['status'] == true ? 'passed' : 'failed') . '">' . ($passed['status'] == true ? esc_html__('DEACTIVATED', 'fastpixel-website-accelerator') : esc_html__('ACTIVE', 'fastpixel-website-accelerator')) . '</strong>' . $action_deactivate . '</span></div>';
            }

            //merging plugins arrays
            $plugins = array_merge($mu_plugins, $plugins);

            $this->passed = true;
            foreach ($plugins as $plugin_name => $passed) {
                if ($passed['status'] == false) {
                    $this->passed = false;
                    $url = admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN . '-settings#diagnostics');
                    /* translators: %s should be an url */
                    $this->add_notification_message(sprintf(esc_html__('It looks like there are conflicting plugins active that may overlap and cause issues. Please deactivate them on the %s page to achieve the best results.', 'fastpixel-website-accelerator'), sprintf('<a class="button" href="%s"> ' . esc_html__('Diagnostics', 'fastpixel-website-accelerator') . '</a>', esc_url($url))), 'warning');
                    break;
                }
            }
            $this->conflicting_plugins_tested = $plugins;
        }

        public function get_display() {
            if (!empty($this->conflicting_plugins_tested)) {
                return $this->conflicting_plugins_tested;
            } else {
                /* translators: diagnostics page message for 'conflicting plugins' row when there are no conflicting plugins */
                return '<strong class="passed">'.esc_html__('NONE', 'fastpixel-website-accelerator').'</strong>';
            }
        }
        public function l10n_name()
        {
            $this->name = esc_html__('Conflicting Plugins', 'fastpixel-website-accelerator');
        }
    }
    new FASTPIXEL_Diag_Test_Conflicting_Plugins();
}
