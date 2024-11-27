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
                        'eventHandlersRewrite' => false, // this is to match wp-meteor:0.1.39 to fpo-javascript-rewrite:0.1.40
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
                    $js_excludes = $this->functions->get_option('fastpixel_javascript_excludes'); //getting excludes from DB
                    $js_excludes_array = explode(chr(13), $js_excludes); //parsing excludes into array
                    $js_excludes_array_prepared = [];
                    foreach($js_excludes_array as $entry) {
                        $js_excludes_array_prepared[] = trim(stripslashes($entry));
                    }
                    $js_excludes_regexp = $this->functions->get_option('fastpixel_javascript_excludes_regexp'); //getting regexp excludes from DB
                    $js_excludes_regexp_array = explode(chr(13), $js_excludes_regexp); //parsing regexp excludes into array
                    if (!empty($js_excludes_array) && !empty($js_excludes_array[0])) {
                        $scriptrewrite['settings']['exclude']['strings'] = $js_excludes_array_prepared; //adding excludes to request options
                    }
                    if (!empty($js_excludes_regexp)) {
                        foreach ($js_excludes_regexp_array as $regexp_exclude) {
                            $scriptrewrite['settings']['exclude']['regexps'][] = (object) ['type' => 'RegExp', 'source' => trim(stripslashes($regexp_exclude)), 'flags' => 'i']; //adding excludes to request options
                        }
                    }
                    $exclude_gdpr = $this->functions->get_option('fastpixel_javascript_exclude_gdpr', 1); //by default GDPR scripts should be excluded from optimization or delay
                    $scriptrewrite['settings']['excludeGDPR'] = $exclude_gdpr ? true : false;
                }
                return $scriptrewrite;
            }
            return [];
        }
    }
}
