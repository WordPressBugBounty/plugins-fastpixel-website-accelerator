<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Settings_Javascript')) {
    class FASTPIXEL_Settings_Javascript 
    {
        public static $instance;
        protected $enabled = false;
        protected $functions;

        public function __construct()
        {
            if (!class_exists('FASTPIXEL\FASTPIXEL_Functions')) {
                return; //stop early if class not exists
            }
            $this->enabled = true;
            $this->functions = FASTPIXEL_Functions::get_instance();
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Settings_Javascript();
            }
            return self::$instance;
        }

        public function get_module_settings(): array {
            if ($this->enabled == true) {
                // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- data is not connected to wordpress query.
                $scriptrewrite = [
                    // false to disable rewrite completely
                    // do not include wp-meteor in the page disabled
                    'enabled'  => false,
                    'settings' => [
                        // optional
                        'exclude'              => [ //phpcs:ignore
                            // optional
                            'strings' => [],
                            // optional, ["string1", "string2"]
                            'regexps' => [],
                            // optional, [{type => "RegExp", source => "domain\\.com"}, flags =>  "i"}, {type => "RegExp", source => "domain2\\.com"}, flags =>  "i"}]
                        ],
                        'nodelay'              => [
                            'strings' => [],
                            'regexps' => [],
                        ],
                        'eventHandlersRewrite' => false, // this is to match wp-meteor:0.1.39 to fpo-javascript-rewrite:0.1.40
                        'rdelay'               => 0,
                        'excludeGDPR'          => true,
                    ],
                    'injectBootstrapScripts' => true //inject js on server side
                ];
                //1 => 'optimize', 2 => 'delaycritical', 3 => 'donotoptimize'
                $js_optimize = $this->functions->get_option('fastpixel_javascript_optimization', 1); //default value is 1 "optiomize"
                if (in_array($js_optimize, [1, 2])) {
                    $scriptrewrite['enabled'] = true;
                    if ($js_optimize == 1) {
                        $scriptrewrite['settings']['rdelay'] = 0; // for "optimize"
                    } else {
                        $scriptrewrite['settings']['rdelay'] = 86400 * 1000; //for "delay critical"
                    }
                    $rules = $this->get_asset_exclusion_settings('fastpixel_javascript_exclusion_rules', true, true, 'javascript');
                    $scriptrewrite['settings']['exclude'] = $rules['exclude'];
                    $scriptrewrite['settings']['nodelay'] = $rules['nodelay'];
                    $exclude_gdpr = $this->functions->get_option('fastpixel_javascript_exclude_gdpr', 1); //by default GDPR scripts should be excluded from optimization or delay
                    $scriptrewrite['settings']['excludeGDPR'] = $exclude_gdpr ? true : false;
                }
                return $scriptrewrite;
            }
            return [];
        }

        public function get_style_module_settings(): array {
            if ($this->enabled == true) {
                $rules = $this->get_asset_exclusion_settings('fastpixel_css_exclusion_rules', false, false, 'css');
                return [
                    'enabled'  => true,
                    'settings' => [
                        'exclude' => $rules['exclude'],
                    ],
                ];
            }
            return [];
        }

        protected function get_asset_exclusion_settings($option_name, $allow_nodelay = true, $pull_legacy = false, $context = 'javascript'): array {
            $settings = [
                'exclude' => [
                    'strings' => [],
                    'regexps' => [],
                ],
                'nodelay' => [
                    'strings' => [],
                    'regexps' => [],
                ],
            ];
            $raw_rules = $this->functions->get_option($option_name, '');
            $rules = is_string($raw_rules) ? json_decode($raw_rules, true) : $raw_rules;
            if (!is_array($rules)) {
                return $settings;
            }

            foreach ($rules as $rule) {
                if (!is_array($rule)) {
                    continue;
                }
                $value = isset($rule['value']) ? trim($rule['value']) : '';
                if ($value === '') {
                    continue;
                }
                $type = isset($rule['type']) && $rule['type'] === 'regex' ? 'regexps' : 'strings';
                $behavior = isset($rule['behavior']) && $allow_nodelay && $rule['behavior'] === 'nodelay' ? 'nodelay' : 'exclude';
                if ($type === 'regexps') {
                    $settings[$behavior][$type][] = $this->format_regexp($value);
                } else {
                    $settings[$behavior][$type][] = $value;
                }
            }

            if (
                $pull_legacy
                && empty($settings['exclude']['strings'])
                && empty($settings['exclude']['regexps'])
                && empty($settings['nodelay']['strings'])
                && empty($settings['nodelay']['regexps'])
            ) {
                $settings['exclude'] = $this->get_legacy_javascript_exclusions();
            }

            //auto-exclusions: applied transparently for detected plugins, never stored in or shown in the exclusions UI
            $settings['exclude'] = apply_filters('fastpixel/asset_exclusions', $settings['exclude'], $context);

            return $settings;
        }

        protected function get_legacy_javascript_exclusions(): array {
            $exclude = [
                'strings' => [],
                'regexps' => [],
            ];
            $js_excludes = $this->functions->get_option('fastpixel_javascript_excludes', ''); //getting excludes from DB
            $js_excludes_array = preg_split('/\r\n|\r|\n/', $js_excludes); //parsing excludes into array
            foreach($js_excludes_array as $entry) {
                $entry = trim(stripslashes($entry));
                if ($entry !== '') {
                    $exclude['strings'][] = $entry;
                }
            }
            $js_excludes_regexp = $this->functions->get_option('fastpixel_javascript_excludes_regexp', ''); //getting regexp excludes from DB
            $js_excludes_regexp_array = preg_split('/\r\n|\r|\n/', $js_excludes_regexp); //parsing regexp excludes into array
            if (!empty($js_excludes_regexp)) {
                foreach ($js_excludes_regexp_array as $regexp_exclude) {
                    $regexp_exclude = trim(stripslashes($regexp_exclude));
                    if ($regexp_exclude !== '') {
                        $exclude['regexps'][] = $this->format_regexp($regexp_exclude); //adding excludes to request options
                    }
                }
            }

            return $exclude;
        }

        protected function format_regexp($source) {
            return (object) ['type' => 'RegExp', 'source' => $source, 'flags' => 'i'];
        }
    }
}
