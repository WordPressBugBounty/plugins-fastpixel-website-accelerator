<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Cache_Cleanup')) {
    class FASTPIXEL_Cache_Cleanup
    {
        public const CRON_HOOK = 'fastpixel_cache_cleanup';

        protected static $instance;
        protected $functions;
        protected $config;

        public function __construct()
        {
            self::$instance = $this;
            $this->functions = FASTPIXEL_Functions::get_instance();
            $this->config = FASTPIXEL_Config_Model::get_instance();

            add_action('init', [$this, 'maybe_schedule']);
            add_action(self::CRON_HOOK, [$this, 'run']);
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Cache_Cleanup();
            }
            return self::$instance;
        }

        public static function get_hook_name(): string
        {
            return self::CRON_HOOK;
        }

        public function maybe_schedule(): void
        {
            if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event') || !function_exists('wp_clear_scheduled_hook')) {
                return;
            }
            if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
                return;
            }
            if (!$this->config->get_option('fastpixel_expired_cleanup')) {
                wp_clear_scheduled_hook(self::CRON_HOOK);
                return;
            }
            if (!wp_next_scheduled(self::CRON_HOOK)) {
                wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
            }
        }

        public static function clear_schedule(): void
        {
            if (function_exists('wp_clear_scheduled_hook')) {
                wp_clear_scheduled_hook(self::CRON_HOOK);
            }
        }

        public function run(): void
        {
            if (!$this->config->get_option('fastpixel_expired_cleanup')) {
                return;
            }

            $cache_dir = $this->functions->get_cache_dir();
            if (!is_dir($cache_dir)) {
                return;
            }

            $cache_limit_gb = $this->sanitize_cache_limit_gb($this->config->get_option('fastpixel_expired_cleanup_limit_gb'));
            if ($cache_limit_gb <= 0) {
                $cache_limit_gb = $this->sanitize_cache_limit_gb($this->config->get_option('fastpixel_expired_cleanup_days'));
            }
            if ($cache_limit_gb <= 0) {
                $cache_limit_gb = (float) FASTPIXEL_DEFAULT_CACHE_LIMIT_GB;
            }

            $cache_limit_bytes = $cache_limit_gb * 1024 * 1024 * 1024;
            if ($cache_limit_bytes < 1) {
                return;
            }

            $cache_size = $this->get_directory_size($cache_dir);
            $usage_ratio = $cache_size / $cache_limit_bytes;
            FASTPIXEL_Debug::log('Class FASTPIXEL_Cache_Cleanup: cron run started', [
                'cache_limit_gb' => $cache_limit_gb,
                'cache_limit_display' => $this->format_bytes($cache_limit_bytes),
                'cache_size_display' => $this->format_bytes($cache_size),
                'usage_percent' => round($usage_ratio * 100, 2),
            ]);
            if ($usage_ratio < $this->get_cleanup_start_ratio()) {
                FASTPIXEL_Debug::log('Class FASTPIXEL_Cache_Cleanup: cleanup skipped, cache is below threshold', [
                    'usage_percent' => round($usage_ratio * 100, 2),
                ]);
                return;
            }

            $days = $this->get_cleanup_days($usage_ratio);
            $expiry_window = $days * DAY_IN_SECONDS;
            FASTPIXEL_Debug::log('Class FASTPIXEL_Cache_Cleanup: cleanup threshold selected', [
                'usage_percent' => round($usage_ratio * 100, 2),
                'cleanup_days' => $days,
            ]);
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($cache_dir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getFilename() !== 'index.html') {
                    continue;
                }

                $html_path = $file->getPathname();
                $file_size = (int) $file->getSize();
                $modified_time = (int) @filemtime($html_path);

                $meta_path = $file->getPath() . DIRECTORY_SEPARATOR . 'meta';
                $last_access_time = 0;
                if (@file_exists($meta_path) && @is_readable($meta_path)) {
                    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- cleanup reads cache metadata directly.
                    $meta = json_decode(file_get_contents($meta_path), true); //phpcs:ignore
                    if (!empty($meta['last_access_time'])) {
                        $last_access_time = (int) $meta['last_access_time'];
                    }
                }
                if (!$last_access_time) {
                    $last_access_time = $modified_time;
                }
                if (!$last_access_time) {
                    continue;
                }
                if (($last_access_time + $expiry_window) >= time()) {
                    continue;
                }

                $relative_path = ltrim(str_replace($cache_dir, '', $file->getPath()), DIRECTORY_SEPARATOR);
                if ($relative_path !== '') {
                    FASTPIXEL_Debug::log('Class FASTPIXEL_Cache_Cleanup: deleting cached page', [
                        'path' => $relative_path,
                        'size' => $this->format_bytes($file_size),
                        'last_access' => $this->format_timestamp($last_access_time),
                    ]);
                    $this->functions->delete_cached_files($relative_path);
                }
            }
        }

        protected function get_cleanup_days(float $usage_ratio): int
        {
            if ($usage_ratio >= $this->get_cleanup_ratio_constant('FASTPIXEL_CACHE_CLEANUP_CRITICAL_RATIO', 0.98)) {
                return $this->get_cleanup_days_constant('FASTPIXEL_CACHE_CLEANUP_CRITICAL_DAYS', 1);
            }
            if ($usage_ratio >= $this->get_cleanup_ratio_constant('FASTPIXEL_CACHE_CLEANUP_HIGH_RATIO', 0.95)) {
                return $this->get_cleanup_days_constant('FASTPIXEL_CACHE_CLEANUP_HIGH_DAYS', 3);
            }
            if ($usage_ratio >= $this->get_cleanup_ratio_constant('FASTPIXEL_CACHE_CLEANUP_MEDIUM_RATIO', 0.90)) {
                return $this->get_cleanup_days_constant('FASTPIXEL_CACHE_CLEANUP_MEDIUM_DAYS', 7);
            }
            return $this->get_cleanup_days_constant('FASTPIXEL_CACHE_CLEANUP_DEFAULT_DAYS', 14);
        }

        protected function get_cleanup_start_ratio(): float
        {
            return $this->get_cleanup_ratio_constant('FASTPIXEL_CACHE_CLEANUP_START_RATIO', 0.80);
        }

        protected function get_cleanup_ratio_constant(string $constant_name, float $default): float
        {
            if (!defined($constant_name)) {
                return $default;
            }

            $value = (float) constant($constant_name);
            if ($value <= 0) {
                return $default;
            }

            return $value;
        }

        protected function get_cleanup_days_constant(string $constant_name, int $default): int
        {
            if (!defined($constant_name)) {
                return $default;
            }

            $value = (int) constant($constant_name);
            if ($value < 0) {
                return $default;
            }

            return $value;
        }

        protected function sanitize_cache_limit_gb($value): float
        {
            if (is_string($value)) {
                $value = str_replace(',', '.', trim($value));
            }

            if (!is_numeric($value)) {
                return 0.0;
            }

            return max(0.0, (float) $value);
        }

        protected function get_directory_size(string $directory): int
        {
            $size = 0;
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += (int) $file->getSize();
                }
            }

            return $size;
        }

        protected function format_bytes(int $bytes): string
        {
            if ($bytes < 1024) {
                return $bytes . ' B';
            }
            if ($bytes < (1024 * 1024)) {
                return round($bytes / 1024, 2) . ' KB';
            }
            if ($bytes < (1024 * 1024 * 1024)) {
                return round($bytes / (1024 * 1024), 2) . ' MB';
            }
            return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
        }

        protected function format_timestamp(int $timestamp): string
        {
            if ($timestamp < 1) {
                return 'unknown';
            }
            return gmdate('Y-m-d H:i:s', $timestamp);
        }
    }
    new FASTPIXEL_Cache_Cleanup();
}
