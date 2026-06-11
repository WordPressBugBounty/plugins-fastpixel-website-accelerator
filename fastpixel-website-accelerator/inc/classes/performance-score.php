<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Performance_Score')) {
    class FASTPIXEL_Performance_Score
    {
        const CRON_HOOK = 'fastpixel_performance_test';
        const RESULT_PROVIDER = 'shortpixel-performance-monitor';
        // Minimum score improvement (Before vs After) required to display the speedometer.
        const DISPLAY_THRESHOLD = 20;

        protected static $instance;
        protected $functions;
        protected $strategy = 'fastpixel-test';
        protected $compare_host = 'https://perfapi.shortpixel.com';

        public function __construct()
        {
            self::$instance = $this;
            $this->functions = FASTPIXEL_Functions::get_instance();
            add_action('fastpixel/cachefiles/saved', [$this, 'schedule_test'], 10, 1);
            add_action(self::CRON_HOOK, [$this, 'run_test'], 10, 2);
            add_action('wp_ajax_fastpixel_run_performance_test', [$this, 'ajax_run_test']);
            add_action('wp_ajax_fastpixel_get_homepage_performance', [$this, 'ajax_get_homepage_performance']);
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Performance_Score();
            }
            return self::$instance;
        }

        public function schedule_test($url)
        {
            if (empty($url) || !$this->is_homepage_url($url)) {
                return;
            }

            $cache_status = $this->functions->check_post_cache_status($url);
            $html_created_time = !empty($cache_status['html_created_time']) ? (int) $cache_status['html_created_time'] : 0;
            if ($html_created_time < 1) {
                return;
            }

            $this->mark_pending_result($url, $html_created_time, true);
        }

        public function run_test($url, $expected_html_created_time = 0)
        {
            if (empty($url) || !$this->is_homepage_url($url)) {
                return;
            }

            $cache_status = $this->functions->check_post_cache_status($url);
            $html_created_time = !empty($cache_status['html_created_time']) ? (int) $cache_status['html_created_time'] : 0;
            if ($html_created_time < 1) {
                return;
            }
            if (!empty($expected_html_created_time) && (int) $expected_html_created_time !== $html_created_time) {
                return;
            }

            $previous_data = $this->read_result($url);
            $previous_original_score = $this->get_result_score($previous_data, 'original_score');
            $previous_optimized_score = $this->get_result_score($previous_data, 'optimized_score');

            $this->write_result($url, [
                'state' => 'running',
                'html_created_time' => $html_created_time,
                'original_score' => $previous_original_score,
                'optimized_score' => $previous_optimized_score,
                'error' => '',
                'tested_at' => 0,
                'provider' => self::RESULT_PROVIDER,
                'strategy' => $this->strategy,
            ]);

            $original_url_raw = $this->get_unoptimized_homepage_url();
            $homepage_url_raw = $this->get_homepage_url();
            $original_test_url = 'https://pagespeed.web.dev/report?url=' . rawurlencode($original_url_raw);
            $optimized_test_url = 'https://pagespeed.web.dev/report?url=' . rawurlencode($homepage_url_raw);

            $result = $this->run_shortpixel_compare($url);
            if (!$result['success']) {
                $this->write_result($url, [
                    'state' => 'error',
                    'html_created_time' => $html_created_time,
                    'original_score' => $this->resolve_result_score($result['original_score'], $previous_original_score),
                    'optimized_score' => $this->resolve_result_score($result['optimized_score'], $previous_optimized_score),
                    'error' => $result['error'],
                    'tested_at' => time(),
                    'provider' => self::RESULT_PROVIDER,
                    'strategy' => $this->strategy,
                ]);
                return;
            }

            $this->write_result($url, [
                'state' => 'ready',
                'html_created_time' => $html_created_time,
                'original_score' => $result['original_score'],
                'optimized_score' => $result['optimized_score'],
                'original_test_url' => $original_test_url,
                'optimized_test_url' => $optimized_test_url,
                'error' => '',
                'tested_at' => time(),
                'provider' => self::RESULT_PROVIDER,
                'strategy' => $this->strategy,
            ]);
        }

        public function get_display($url, $html_created_time = 0)
        {
            $data = $this->read_result($url);
            $state = is_array($data) && !empty($data['state']) ? sanitize_key($data['state']) : 'empty';
            if ($state !== 'ready') {
                return '';
            }

            $original_score = $this->get_result_score($data, 'original_score');
            $optimized_score = $this->get_result_score($data, 'optimized_score');

            if (($optimized_score - $original_score) < self::DISPLAY_THRESHOLD) {
                return '';
            }

            $original_test_url = !empty($data['original_test_url']) ? esc_url_raw($data['original_test_url']) : '';
            $optimized_test_url = !empty($data['optimized_test_url']) ? esc_url_raw($data['optimized_test_url']) : '';

            return $this->get_speedometer_html($original_score, $optimized_score, $original_test_url, $optimized_test_url);
        }

        public function should_show_dashboard_widget()
        {
            $homepage_url = $this->get_homepage_url();
            if (empty($homepage_url)) {
                return false;
            }
            $data = $this->read_result($homepage_url);
            if (!is_array($data)) {
                return false;
            }
            $state = !empty($data['state']) ? sanitize_key($data['state']) : 'empty';
            if ($state !== 'ready') {
                return false;
            }
            $original_score = $this->get_result_score($data, 'original_score');
            $optimized_score = $this->get_result_score($data, 'optimized_score');
            return ($optimized_score - $original_score) >= self::DISPLAY_THRESHOLD;
        }

        protected function get_speedometer_html($original_score, $optimized_score, $original_test_url = '', $optimized_test_url = '')
        {
            $original_score = max(0, min(100, (int) $original_score));
            $optimized_score = max(0, min(100, (int) $optimized_score));
            $start_score = 0;

            if (empty($original_test_url)) {
                $original_test_url = 'https://pagespeed.web.dev/report?url=' . rawurlencode($this->get_unoptimized_homepage_url());
            }
            if (empty($optimized_test_url)) {
                $optimized_test_url = 'https://pagespeed.web.dev/report?url=' . rawurlencode($this->get_homepage_url());
            }

            if ($original_score >= 90) {
                $original_color = 'rgb(0, 204, 102)';
                $original_track_color = '#e3f8ed';
            } elseif ($original_score >= 50) {
                $original_color = 'rgb(255, 153, 0)';
                $original_track_color = '#fff4e5';
            } else {
                $original_color = 'rgb(255, 51, 51)';
                $original_track_color = '#ffe9e9';
            }
            $optimized_color = 'rgb(0, 204, 102)';
            $optimized_track_color = '#e3f8ed';
            $original_gradient = 'conic-gradient(' . $original_color . ' 0% ' . $start_score . '%, ' . $original_track_color . ' ' . $start_score . '% 100%)';
            $optimized_gradient = 'conic-gradient(' . $optimized_color . ' 0% ' . $start_score . '%, ' . $optimized_track_color . ' ' . $start_score . '% 100%)';

            return '<h3 class="fastpixel-panel-title">' . esc_html__('FastPixel Optimization', 'fastpixel-website-accelerator') . '</h3>' .
                '<div class="fastpixel-performance-widget">' .
                    '<div class="circle-container clearfix" id="circle-container"><div class="circle-wrapper">' .
                        '<div class="circle" data-start-score="' . (int) $start_score . '" data-target-score="' . (int) $original_score . '" data-active-color="' . esc_attr($original_color) . '" data-track-color="' . esc_attr($original_track_color) . '" style="background: ' . esc_attr($original_gradient) . '">' .
                            '<div class="inner-circle" style="background:' . esc_attr($original_track_color) . '; ">' .
                                '<div class="score-text" style="color:' . esc_attr($original_color) . '">' .
                                    (int) $start_score .
                                '</div>' .
                            '</div>' .
                        '</div>' .
                        '<div class="label"><a href="' . esc_url($original_test_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Before', 'fastpixel-website-accelerator') . '</a></div>' .
                    '</div><div class="circle-wrapper">' .
                        '<div class="circle" data-start-score="' . (int) $start_score . '" data-target-score="' . (int) $optimized_score . '" data-active-color="' . esc_attr($optimized_color) . '" data-track-color="' . esc_attr($optimized_track_color) . '" style="background: ' . esc_attr($optimized_gradient) . '">' .
                            '<div class="inner-circle" style="background:' . esc_attr($optimized_track_color) . '; ">' .
                                '<div class="score-text" style="color:' . esc_attr($optimized_color) . '">' .
                                    (int) $start_score .
                                '</div>' .
                            '</div>' .
                        '</div>' .
                        '<div class="label"><a href="' . esc_url($optimized_test_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('After', 'fastpixel-website-accelerator') . '</a></div>' .
                    '</div></div>' .
                '</div>';
        }

        public function get_cell_html($url)
        {
            if (empty($url)) {
                return '<span class="fastpixel-performance-cell" data-state="empty"><span class="fastpixel-performance-empty">-</span></span>';
            }

            $cache_status = $this->functions->check_post_cache_status($url);
            $html_created_time = !empty($cache_status['html_created_time']) ? (int) $cache_status['html_created_time'] : 0;
            if ($html_created_time > 0) {
                $this->prepare_result_state($url, $html_created_time);
            }

            $data = $this->read_result($url);
            $state = !empty($data['state']) ? sanitize_key($data['state']) : 'empty';

            return '<span class="fastpixel-performance-cell" data-state="' . esc_attr($state) . '" data-url="' . esc_attr($url) . '" data-html-created-time="' . (int) $html_created_time . '">' .
                $this->get_display($url, $html_created_time) .
            '</span>';
        }

        public function get_homepage_cell_html()
        {
            return $this->get_cell_html($this->get_homepage_url());
        }

        public function get_dashboard_widget_html()
        {
            $homepage_url = $this->get_homepage_url();
            if (empty($homepage_url)) {
                return '';
            }

            return '<div class="fastpixel-homepage-performance-box">' .
                '<div class="fastpixel-homepage-performance-result">' . $this->get_homepage_cell_html() . '</div>' .
            '</div>';
        }

        protected function run_shortpixel_compare($optimized_url)
        {
            $original_url = $this->get_unoptimized_homepage_url();
            if (empty($original_url)) {
                return [
                    'success' => false,
                    'original_score' => null,
                    'optimized_score' => null,
                    'error' => esc_html__('Homepage URL is missing', 'fastpixel-website-accelerator'),
                ];
            }

            $compare = $this->request_compare($original_url, $optimized_url);
            if (!$compare['success']) {
                return [
                    'success' => false,
                    'original_score' => null,
                    'optimized_score' => null,
                    'error' => $compare['error'],
                ];
            }

            return $this->wait_for_compare_results($compare['url_id']);
        }

        protected function request_compare($url, $cached_url)
        {
            $response = wp_remote_post($this->compare_host . '/api/compare-urls', [
                'timeout' => 30,
                'sslverify' => false,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'body' => wp_json_encode([
                    'url' => $url,
                    'cached' => $cached_url,
                    'retest' => '',
                ]),
            ]);

            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'error' => $response->get_error_message(),
                ];
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            $decoded = json_decode(wp_remote_retrieve_body($response), true);
            $url_id = $decoded['data']['urlId'] ?? null;
            if ($code >= 400 || !is_numeric($url_id)) {
                return [
                    'success' => false,
                    'error' => sprintf(esc_html__('ShortPixel compare request failed with HTTP %d', 'fastpixel-website-accelerator'), $code),
                ];
            }

            return [
                'success' => true,
                'url_id' => (int) $url_id,
            ];
        }

        protected function wait_for_compare_results($url_id)
        {
            for ($attempt = 0; $attempt < 6; $attempt++) {
                $response = wp_remote_post($this->compare_host . '/api/get-compare-results', [
                    'timeout' => 30,
                    'sslverify' => false,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'body' => wp_json_encode([
                        'urlId' => (int) $url_id,
                    ]),
                ]);

                if (!is_wp_error($response)) {
                    $decoded = json_decode(wp_remote_retrieve_body($response), true);
                    if (!empty($decoded['data']['success']) && !empty($decoded['data']['result'])) {
                        $original_score = $this->extract_compare_score($decoded, 'original');
                        $optimized_score = $this->extract_compare_score($decoded, 'cached');
                        if ($original_score !== null && $optimized_score !== null) {
                            return [
                                'success' => true,
                                'original_score' => $original_score,
                                'optimized_score' => $optimized_score,
                                'error' => '',
                            ];
                        }
                    }
                }

                if ($attempt < 5) {
                    sleep(5);
                }
            }

            return [
                'success' => false,
                'original_score' => null,
                'optimized_score' => null,
                'error' => esc_html__('ShortPixel compare results did not finish in time', 'fastpixel-website-accelerator'),
            ];
        }

        protected function extract_compare_score(array $decoded, $key)
        {
            $value = $decoded['data']['result'][$key]['performance'][0] ?? null;
            if (!is_numeric($value)) {
                return null;
            }
            return (int) round(((float) $value) * 100);
        }

        public function ajax_run_test()
        {
            if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'cache_status_nonce')) {
                wp_send_json([
                    'status' => 'error',
                    'statusText' => esc_html__('Can\'t run action, wrong nonce provided', 'fastpixel-website-accelerator'),
                ]);
            }

            $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
            if (empty($url) || !$this->is_homepage_url($url)) {
                wp_send_json([
                    'status' => 'error',
                    'statusText' => esc_html__('Only homepage performance tests are supported.', 'fastpixel-website-accelerator'),
                ]);
            }

            $this->run_test($url, 0);

            wp_send_json([
                'status' => 'success',
                'performance_display' => $this->get_homepage_cell_html(),
            ]);
        }

        public function ajax_get_homepage_performance()
        {
            if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'cache_status_nonce')) {
                wp_send_json([
                    'status' => 'error',
                    'statusText' => esc_html__('Can\'t run action, wrong nonce provided', 'fastpixel-website-accelerator'),
                ]);
            }

            wp_send_json([
                'status' => 'success',
                'performance_display' => $this->get_homepage_cell_html(),
            ]);
        }

        protected function read_result($url)
        {
            $file = $this->get_file_path($url);
            if (empty($file) || !file_exists($file) || !is_readable($file)) {
                return false;
            }

            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading simple JSON sidecar.
            $contents = file_get_contents($file); // phpcs:ignore
            if ($contents === false || $contents === '') {
                return false;
            }

            $decoded = json_decode($contents, true);
            return is_array($decoded) ? $decoded : false;
        }

        protected function write_result($url, array $data)
        {
            $file = $this->get_file_path($url, true);
            if (empty($file)) {
                return false;
            }
            return $this->functions->atomic_write_file($file, wp_json_encode($data));
        }

        protected function get_file_path($url, $create = false)
        {
            if (empty($url)) {
                return false;
            }

            if ($create) {
                $path = $this->functions->check_path($url);
            } else {
                $url_obj = new FASTPIXEL_Url($url);
                $path = $this->functions->get_cache_dir() . DIRECTORY_SEPARATOR . rtrim($url_obj->get_url_path(), DIRECTORY_SEPARATOR);
            }

            if (empty($path)) {
                return false;
            }

            return $path . DIRECTORY_SEPARATOR . 'perf.json';
        }

        protected function prepare_result_state($url, $html_created_time)
        {
            if (empty($url) || (int) $html_created_time < 1 || !$this->is_homepage_url($url)) {
                return false;
            }

            $data = $this->read_result($url);
            $stored_time = !empty($data['html_created_time']) ? (int) $data['html_created_time'] : 0;
            $stored_provider = !empty($data['provider']) ? sanitize_key($data['provider']) : '';
            $stored_error = !empty($data['error']) && is_string($data['error']) ? $data['error'] : '';
            $has_legacy_pagespeed_error = stripos($stored_error, 'pagespeedonline.googleapis.com') !== false
                || stripos($stored_error, 'Quota exceeded for quota metric') !== false;

            if (
                empty($data)
                || $stored_time !== (int) $html_created_time
                || $stored_provider !== self::RESULT_PROVIDER
                || $has_legacy_pagespeed_error
            ) {
                return $this->mark_pending_result($url, $html_created_time, true);
            }

            return false;
        }

        protected function mark_pending_result($url, $html_created_time, $schedule = true)
        {
            if (empty($url) || (int) $html_created_time < 1) {
                return false;
            }

            $previous_data = $this->read_result($url);

            $this->write_result($url, [
                'state' => 'pending',
                'html_created_time' => (int) $html_created_time,
                'original_score' => $this->get_result_score($previous_data, 'original_score'),
                'optimized_score' => $this->get_result_score($previous_data, 'optimized_score'),
                'error' => '',
                'tested_at' => 0,
                'provider' => self::RESULT_PROVIDER,
                'strategy' => $this->strategy,
            ]);

            if ($schedule && function_exists('wp_next_scheduled') && function_exists('wp_schedule_single_event')) {
                $event_args = [$url, (int) $html_created_time];
                if (!wp_next_scheduled(self::CRON_HOOK, $event_args)) {
                    wp_schedule_single_event(time() + 60, self::CRON_HOOK, $event_args);
                }
            }

            return true;
        }

        protected function get_result_score($data, $key, $default = 0)
        {
            if (!is_array($data) || !isset($data[$key]) || !is_numeric($data[$key])) {
                return max(0, min(100, (int) $default));
            }

            return max(0, min(100, (int) $data[$key]));
        }

        protected function resolve_result_score($score, $fallback = 0)
        {
            if (is_numeric($score)) {
                return max(0, min(100, (int) $score));
            }

            return max(0, min(100, (int) $fallback));
        }

        protected function get_homepage_url()
        {
            if (!function_exists('get_home_url')) {
                return '';
            }

            $homepage_url = defined('ICL_SITEPRESS_VERSION')
                ? apply_filters('wpml_home_url', get_home_url())
                : get_home_url();

            return is_string($homepage_url) ? esc_url_raw($homepage_url) : '';
        }

        protected function is_homepage_url($url)
        {
            if (!is_string($url) || empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                return false;
            }

            $homepage_url = $this->get_homepage_url();
            if (empty($homepage_url)) {
                return false;
            }

            $requested = new FASTPIXEL_Url($url, true);
            $homepage = new FASTPIXEL_Url($homepage_url, true);

            return $requested->get_url_path() === $homepage->get_url_path();
        }

        protected function get_unoptimized_homepage_url()
        {
            $homepage_url = $this->get_homepage_url();
            if (empty($homepage_url)) {
                return '';
            }

            return esc_url_raw(add_query_arg('fastpixeldisable', '1', $homepage_url));
        }
    }

    new FASTPIXEL_Performance_Score();
}
