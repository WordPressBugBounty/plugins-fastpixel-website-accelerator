<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Module_Speculation_Rules')) {
    class FASTPIXEL_Module_Speculation_Rules extends FASTPIXEL_Module
    {

        protected $enabled = false;
        protected $mode = 'prerender';
        protected $eagerness = 'moderate';
        protected $contexts = [];


        public function __construct()
        {
            parent::__construct();
        }

        public function init()
        {
            add_action('init', function () {
                if (!class_exists('FASTPIXEL\FASTPIXEL_Functions')) {
                    return;
                }
                $functions = FASTPIXEL_Functions::get_instance();
                $this->enabled = (bool) $functions->get_option('fastpixel_speculation_rules', false);
                $this->mode = $functions->get_option('fastpixel_speculation_mode', 'prerender');
                $this->eagerness = $functions->get_option('fastpixel_speculation_eagerness', 'moderate');
                $this->contexts = $this->get_default_contexts();
                if ($this->enabled == true && !defined('SPECULATION_RULES_VERSION')) { //checking if speculation rules are enabled and there is no speculation rules plugin
                    //adding rules into page footer
                    add_action('wp_footer', [$this, 'add_speculation_rules']);
                }
                if (defined('SPECULATION_RULES_VERSION')) {
                    $functions->update_option('fastpixel_speculation_rules', (bool) false);
                }
            });
        }

        public function add_speculation_rules()
        {
            wp_print_inline_script_tag(
                (string) wp_json_encode($this->get_settings()),
                array('type' => 'speculationrules')
            );
        }

        protected function get_settings(): array 
        {
            if ($this->enabled == true) {

                $rules = [
                    [
                        'source'    => 'document',
                        'where'     => [
                            'and' => [
                                [
                                    'href_matches' => $this->prefix_path_pattern('/*'), //Include URLs within the same site.
                                ],
                                [
                                    'not' => [
                                        'href_matches' => $this->get_excludes(), //Exclude WP login and admin URLs.
                                    ],
                                ],
                                [
                                    'not' => [
                                        'selector_matches' => 'a[rel~="nofollow"]', //Exclude rel=nofollow links, as plugins like WooCommerce use that on their add-to-cart links.
                                    ],
                                ],
                            ],
                        ],
                        'eagerness' => $this->eagerness, //TODO: check this item
                    ],
                ];
                //TODO check if we need different modes
                if ('prerender' === $this->mode) {
                    $rules[0]['where']['and'][] = array(
                        'not' => array(
                            'selector_matches' => '.no-prerender',
                        ),
                    );
                }
                return [$this->mode => $rules];
            }
            return [];
        }

        protected function get_default_contexts(): array
        {
            return array(
                'home'       => $this->escape(trailingslashit( (string) wp_parse_url(\home_url('/'), PHP_URL_PATH))),
                'site'       => $this->escape(trailingslashit( (string) wp_parse_url(\site_url('/'), PHP_URL_PATH))),
                'uploads'    => $this->escape(trailingslashit( (string) wp_parse_url(\wp_upload_dir(null, false)['baseurl'], PHP_URL_PATH))),
                'content'    => $this->escape(trailingslashit( (string) wp_parse_url(\content_url(), PHP_URL_PATH))),
                'plugins'    => $this->escape(trailingslashit( (string) wp_parse_url(\plugins_url(), PHP_URL_PATH))),
                'template'   => function_exists('get_stylesheet_directory_uri') ? $this->escape(trailingslashit( (string) wp_parse_url(\get_stylesheet_directory_uri(), PHP_URL_PATH))) : '',
                'stylesheet' => function_exists('get_template_directory_uri') ? $this->escape(trailingslashit( (string) wp_parse_url(\get_template_directory_uri(), PHP_URL_PATH))) : '',
            );
        }

        protected function get_excludes(): array
        {
            $default_excluded_paths = [];
            foreach (['/wp-login.php' => 'site', '/wp-admin/*' => 'site', '/*\\?*(^|&)_wpnonce=*' => 'home', '/*' => ['uploads', 'content', 'plugins', 'template', 'stylesheet'],] as $path => $context) {
                if (is_array($context)) {
                    foreach ($context as $context_name) {
                        $default_excluded_paths[] = $this->prefix_path_pattern($path, $context_name);
                    }
                } else {
                    $default_excluded_paths[] = $this->prefix_path_pattern($path, $context);
                }
            }
            //getting excluded paths from filter used in 'spectacular rules' plugin, 
            //this filter is used by different plugins like woocommerce
            $excluded_paths = (array) apply_filters('plsr_speculation_rules_href_exclude_paths', array(), $this->mode);
            $excluded_paths = array_values(
                array_unique(
                    array_merge(
                        $default_excluded_paths,
                        array_map(
                            function (string $excluded_path): string {
                                return $this->prefix_path_pattern($excluded_path);
                            },
                            $excluded_paths
                        )
                    )
                )
            );
            return $excluded_paths;
        }

        protected function prefix_path_pattern(string $path_pattern, string $context = 'home'): string
        {
            if (!isset($this->contexts[$context])) {
                return $path_pattern;
            }

            // In the event that the context path contains a :, ? or # (which can cause the URL pattern parser to switch to another state, though only the latter 
            // two should be percent encoded anyway), we need to additionally enclose it in grouping braces. The final forward slash (trailingslashit ensures there is one) 
            // affects the meaning of the * wildcard, so is left outside the braces.
            $context_path = $this->contexts[$context];
            $escaped_context_path = $context_path;
            if (strcspn($context_path, ':?#') !== strlen($context_path)) {
                $escaped_context_path = '{' . substr($context_path, 0, -1) . '}/';
            }

            // If the path already starts with the context path (including '/'), remove it first since it is about to be added back.
            if (function_exists('str_starts_with') && str_starts_with($path_pattern, $context_path)) { //str_starts_with available only in php8
                $path_pattern = substr($path_pattern, strlen($context_path));
            } else if (substr($path_pattern, 0, strlen($context_path)) === $context_path) {
                $path_pattern = substr($path_pattern, strlen($context_path));
            }

            return $escaped_context_path . ltrim($path_pattern, '/');
        }

        /**
         * Escapes a string for use in a URL pattern component.
         */
        protected function escape(string $str): string
        {
            return addcslashes($str, '+*?:{}()\\');
        }

    }
    new FASTPIXEL_Module_Speculation_Rules();
}
