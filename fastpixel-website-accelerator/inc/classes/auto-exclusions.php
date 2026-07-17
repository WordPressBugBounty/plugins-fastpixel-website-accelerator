<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Auto_Exclusions')) {
    /**
     * Injects asset exclusions for known third-party plugins when they are active.
     * Applied transparently to the request, not stored in or shown in the exclusions UI.
     * Integrates via the 'fastpixel/asset_exclusions' filter (see settings-javascript.php).
     */
    class FASTPIXEL_Auto_Exclusions
    {
        public static $instance;

        // Resolved excludes cache, keyed by context.
        protected $resolved = null;

        public function __construct()
        {
            self::$instance = $this;
            if (function_exists('add_filter')) {
                add_filter('fastpixel/asset_exclusions', [$this, 'filter_exclude'], 10, 2);
            }
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Auto_Exclusions();
            }
            return self::$instance;
        }

        /**
         * Registry of plugin => exclusions. Entry keys:
         *  - 'label'   : human-readable plugin name (shown as an Active Integration).
         *  - 'detect'  : signals (constants|classes|functions|plugins arrays); active if ANY match.
         *  - 'context' : asset type, 'javascript' (default) | 'css'.
         *  - 'strings' : substring exclusions.
         *  - 'regexps' : regexp sources (no delimiters; "i" flag added).
         * Extend via the 'fastpixel/auto_exclusions/registry' filter.
         */
        protected function get_registry(): array
        {
            $registry = [
                'simpletoc' => [
                    'label'   => 'SimpleTOC',
                    'detect'  => [
                        'functions' => ['MToensing\\SimpleTOC\\register_simpletoc_block'],
                        'plugins'   => ['simpletoc/plugin.php'],
                    ],
                    'context' => 'javascript',
                    'strings' => ['simpletoc-accordion-js'],
                    'regexps' => [],
                ],
                'wpforms' => [
                    'label'   => 'WPForms',
                    'detect'  => [
                        'constants' => ['WPFORMS_VERSION'],
                        'plugins'   => ['wpforms-lite/wpforms.php', 'wpforms/wpforms.php'],
                    ],
                    'context' => 'javascript',
                    'strings' => [],
                    'regexps' => ['jquery', 'wpforms'],
                ],
                'mailpoet' => [
                    'label'   => 'MailPoet',
                    'detect'  => [
                        'constants' => ['MAILPOET_VERSION'],
                        'classes'   => ['MailPoet\\DI\\ContainerWrapper'],
                        'plugins'   => ['mailpoet/mailpoet.php'],
                    ],
                    'context' => 'javascript',
                    'strings' => ['mailpoet'],
                    'regexps' => [],
                ],
            ];
            return (array) apply_filters('fastpixel/auto_exclusions/registry', $registry);
        }

        /**
         * Active integrations: registry plugins detected on this site, for display in the
         * Diagnostics tab. Returns a de-duplicated list of human-readable plugin names.
         *
         * @return string[]
         */
        public function get_active_integrations(): array
        {
            $labels = [];
            foreach ($this->get_registry() as $key => $plugin) {
                if (empty($plugin['detect']) || !is_array($plugin['detect']) || !$this->is_active($plugin['detect'])) {
                    continue;
                }
                $labels[] = !empty($plugin['label']) ? (string) $plugin['label'] : (string) $key;
            }
            return array_values(array_unique($labels));
        }

        /**
         * Merged auto-exclusions for the active plugins in one context.
         *
         * @param string $context 'javascript' | 'css'.
         * @return array{strings: string[], regexps: string[]}
         */
        public function get_excludes(string $context = 'javascript'): array
        {
            if (isset($this->resolved[$context])) {
                return $this->resolved[$context];
            }
            $strings = [];
            $regexps = [];
            foreach ($this->get_registry() as $plugin) {
                $plugin_context = isset($plugin['context']) ? $plugin['context'] : 'javascript';
                if ($plugin_context !== $context) {
                    continue;
                }
                if (empty($plugin['detect']) || !is_array($plugin['detect']) || !$this->is_active($plugin['detect'])) {
                    continue;
                }
                if (!empty($plugin['strings']) && is_array($plugin['strings'])) {
                    $strings = array_merge($strings, $plugin['strings']);
                }
                if (!empty($plugin['regexps']) && is_array($plugin['regexps'])) {
                    $regexps = array_merge($regexps, $plugin['regexps']);
                }
            }
            if (!is_array($this->resolved)) {
                $this->resolved = [];
            }
            $this->resolved[$context] = [
                'strings' => $this->normalize($strings),
                'regexps' => $this->normalize($regexps),
            ];
            return $this->resolved[$context];
        }

        /**
         * Filter callback ('fastpixel/asset_exclusions'): merges auto-exclusions into the
         * exclude set, de-duplicated against existing entries.
         *
         * @param array  $exclude ['strings' => string[], 'regexps' => object[]].
         * @param string $context 'javascript' | 'css'.
         * @return array
         */
        public function filter_exclude($exclude, $context = 'javascript') {
            if (!is_array($exclude)) {
                $exclude = [];
            }
            $auto = $this->get_excludes($context);
            if (empty($auto['strings']) && empty($auto['regexps'])) {
                return $exclude;
            }
            if (!isset($exclude['strings']) || !is_array($exclude['strings'])) {
                $exclude['strings'] = [];
            }
            if (!isset($exclude['regexps']) || !is_array($exclude['regexps'])) {
                $exclude['regexps'] = [];
            }
            foreach ($auto['strings'] as $string) {
                if (!in_array($string, $exclude['strings'], true)) {
                    $exclude['strings'][] = $string;
                }
            }
            // existing regexp sources, to avoid duplicates
            $existing_sources = [];
            foreach ($exclude['regexps'] as $regexp) {
                if (is_object($regexp) && isset($regexp->source)) {
                    $existing_sources[] = $regexp->source;
                }
            }
            foreach ($auto['regexps'] as $source) {
                if (!in_array($source, $existing_sources, true)) {
                    $exclude['regexps'][] = (object) ['type' => 'RegExp', 'source' => $source, 'flags' => 'i'];
                    $existing_sources[] = $source;
                }
            }
            return $exclude;
        }

        // Trim, drop empties and de-duplicate while preserving order.
        protected function normalize(array $values): array
        {
            $values = array_map('trim', $values);
            $values = array_filter($values, function ($value) {
                return $value !== '';
            });
            return array_values(array_unique($values));
        }

        // True if ANY of the supplied detection signals match.
        protected function is_active(array $detect): bool
        {
            if (!empty($detect['constants'])) {
                foreach ((array) $detect['constants'] as $constant) {
                    if (defined($constant)) {
                        return true;
                    }
                }
            }
            if (!empty($detect['classes'])) {
                foreach ((array) $detect['classes'] as $class) {
                    if (class_exists($class)) {
                        return true;
                    }
                }
            }
            if (!empty($detect['functions'])) {
                foreach ((array) $detect['functions'] as $function) {
                    if (function_exists($function)) {
                        return true;
                    }
                }
            }
            if (!empty($detect['plugins'])) {
                foreach ((array) $detect['plugins'] as $plugin) {
                    if ($this->is_plugin_active_by_slug($plugin)) {
                        return true;
                    }
                }
            }
            return false;
        }

        // Frontend-safe is_plugin_active check (no wp-admin include). Honors network activation.
        protected function is_plugin_active_by_slug(string $slug): bool
        {
            if (!function_exists('get_option')) {
                return false;
            }
            $active = (array) get_option('active_plugins', []);
            if (in_array($slug, $active, true)) {
                return true;
            }
            if (function_exists('is_multisite') && is_multisite() && function_exists('get_site_option')) {
                $network = (array) get_site_option('active_sitewide_plugins', []);
                if (isset($network[$slug])) {
                    return true;
                }
            }
            return false;
        }
    }
    // register the filter on load
    new FASTPIXEL_Auto_Exclusions();
}
