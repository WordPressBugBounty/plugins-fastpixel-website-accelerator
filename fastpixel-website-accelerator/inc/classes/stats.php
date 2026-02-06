<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Stats')) {
    class FASTPIXEL_Stats
    {
        protected $debug = false;
        protected static $instance;
        protected $stats_dir;
        protected $stats_file;
        protected $daily_stats_file;
        protected $hourly_stats_file;
        protected $lock_file;
        protected $stats_data = array();
        protected $daily_stats_data = array();
        protected $hourly_stats_data = array();
        protected $lock_timeout = 5; // seconds
        protected $loaded = false;

        public function __construct() {
            self::$instance = $this;
            
            // Set up stats directory and files
            $functions = FASTPIXEL_Functions::get_instance();
            $this->stats_dir = $functions->get_cache_dir() . DIRECTORY_SEPARATOR . 'stats';
            $this->stats_file = $this->stats_dir . DIRECTORY_SEPARATOR . 'cache_stats.json';
            $this->daily_stats_file = $this->stats_dir . DIRECTORY_SEPARATOR . 'daily_stats.json';
            $this->hourly_stats_file = $this->stats_dir . DIRECTORY_SEPARATOR . 'hourly_stats.json';
            $this->lock_file = $this->stats_dir . DIRECTORY_SEPARATOR . 'stats.lock';
            
            // Create stats directory if it doesn't exist
            if (!file_exists($this->stats_dir)) {
                @mkdir($this->stats_dir, 0755, true);
                // Create an empty index.php file to prevent directory listing
                if (file_exists($this->stats_dir)) {
                    @file_put_contents($this->stats_dir . '/index.php', '<?php //silence is golden');
                }
            }

            // Register AJAX handlers for admin
            add_action('wp_ajax_fastpixel_reset_stats', [$this, 'ajax_reset_stats']);
        }

        public static function get_instance() {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Stats();
            }
            return self::$instance;
        }

        /**
         * Record a cache hit
         */
        public function record_hit() {//error_log('Recording cache hit');
            $this->record_event('hits');
        }

        /**
         * Record a cache miss
         */
        public function record_miss() {
            $this->record_event('misses');
        }

        /**
         * Get the cache hit ratio statistics
         * 
         * @return array The statistics data
         */
        public function get_stats() {
            $this->load_stats();
            
            // Calculate hit ratio
            $hits = isset($this->stats_data['hits']) ? $this->stats_data['hits'] : 0;
            $misses = isset($this->stats_data['misses']) ? $this->stats_data['misses'] : 0;
            $total = $hits + $misses;
            
            $hit_ratio = ($total > 0) ? ($hits / $total) * 100 : 0;
            
            return array(
                'hits' => $hits,
                'misses' => $misses,
                'total' => $total,
                'hit_ratio' => round($hit_ratio, 2),
                'daily' => $this->get_daily_stats(),
                'hourly' => $this->get_hourly_stats(),
            );
        }

        /**
         * Get daily statistics
         */
        protected function get_daily_stats() {
            return $this->daily_stats_data;
        }

        /**
         * Get hourly statistics
         */
        protected function get_hourly_stats() {
            return $this->hourly_stats_data;
        }

        /**
         * Reset all statistics
         */
        public function reset_stats() {
            if ($this->acquire_lock()) {
                $this->stats_data = array(
                    'hits' => 0,
                    'misses' => 0,
                    'last_reset' => time(),
                );
                $this->daily_stats_data = array();
                $this->hourly_stats_data = array();
                
                $this->save_stats();
                $this->save_daily_stats();
                $this->save_hourly_stats();
                
                $this->release_lock();
                return true;
            }
            return false;
        }

        /**
         * Record a cache event (hit or miss)
         * 
         * @param string $type The type of event (hits or misses)
         */
        protected function record_event($type) {
            if (!in_array($type, array('hits', 'misses'))) {
                return false;
            }
            
            // Try to acquire lock to update the stats
            if ($this->acquire_lock()) {
                // Load current stats
                $this->load_stats();
                
                // Update global stats
                if (!isset($this->stats_data[$type])) {
                    $this->stats_data[$type] = 0;
                }
                $this->stats_data[$type]++;
                
                // Update daily stats
                $today = gmdate('Y-m-d');
                if (!isset($this->daily_stats_data[$today])) {
                    $this->daily_stats_data[$today] = array(
                        'hits' => 0,
                        'misses' => 0,
                    );
                }
                $this->daily_stats_data[$today][$type]++;
                
                // Limit daily stats to last 30 days
                $this->daily_stats_data = $this->limit_array_days($this->daily_stats_data, 2);
                
                // Update hourly stats
                $hour = gmdate('Y-m-d H:00:00');
                if (!isset($this->hourly_stats_data[$hour])) {
                    $this->hourly_stats_data[$hour] = array(
                        'hits' => 0,
                        'misses' => 0,
                    );
                }
                $this->hourly_stats_data[$hour][$type]++;
                
                // Limit hourly stats to last 48 hours
                $this->hourly_stats_data = $this->limit_array_days($this->hourly_stats_data, 2, 'Y-m-d H:00:00');
                
                // Save updated stats
                $this->save_stats();
                $this->save_daily_stats();
                $this->save_hourly_stats();
                
                // Release the lock
                $this->release_lock();
                
                return true;
            }
            
            return false;
        }

        /**
         * Load statistics from file
         */
        protected function load_stats($force = false) {
            if (!$force && $this->loaded == true) {
                return;
            }

            //loading stats 
            if (file_exists($this->stats_file)) {
                $content = @file_get_contents($this->stats_file);
                if ($content) {
                    $data = json_decode($content, true);
                    if (is_array($data)) {
                        $this->stats_data = $data;
                    }
                }
            }
            
            // Initialize if empty
            if (empty($this->stats_data)) {
                $this->stats_data = array(
                    'hits' => 0,
                    'misses' => 0,
                    'last_reset' => time(),
                );
            }

            //load daily and hourly stats
            $this->load_daily_stats();
            $this->load_hourly_stats();

            $this->loaded = true;
        }

        /**
         * Load daily statistics from file
         */
        protected function load_daily_stats() {
            if (file_exists($this->daily_stats_file)) {
                $content = @file_get_contents($this->daily_stats_file);
                if ($content) {
                    $data = json_decode($content, true);
                    if (is_array($data)) {
                        $this->daily_stats_data = $data;
                    }
                }
            }
            
            // Initialize if empty
            if (empty($this->daily_stats_data)) {
                $this->daily_stats_data = array();
            }
        }

        /**
         * Load hourly statistics from file
         */
        protected function load_hourly_stats() {
            if (file_exists($this->hourly_stats_file)) {
                $content = @file_get_contents($this->hourly_stats_file);
                if ($content) {
                    $data = json_decode($content, true);
                    if (is_array($data)) {
                        $this->hourly_stats_data = $data;
                    }
                }
            }
            
            // Initialize if empty
            if (empty($this->hourly_stats_data)) {
                $this->hourly_stats_data = array();
            }
        }

        /**
         * Save statistics to file
         */
        protected function save_stats() {
            $content = json_encode($this->stats_data);
            @file_put_contents($this->stats_file, $content);
        }

        /**
         * Save daily statistics to file
         */
        protected function save_daily_stats() {
            $content = json_encode($this->daily_stats_data);
            @file_put_contents($this->daily_stats_file, $content);
        }

        /**
         * Save hourly statistics to file
         */
        protected function save_hourly_stats() {
            $content = json_encode($this->hourly_stats_data);
            @file_put_contents($this->hourly_stats_file, $content);
        }

        /**
         * Acquire a lock for file access
         * 
         * @return bool True if lock acquired, false otherwise
         */
        protected function acquire_lock() {
            $start_time = microtime(true);
            
            // Try to create the lock file
            while (file_exists($this->lock_file)) {
                // Check if the lock has expired
                $lock_time = @filemtime($this->lock_file);
                if ($lock_time && (time() - $lock_time > $this->lock_timeout)) {
                    @unlink($this->lock_file);
                } else {
                    // Wait a bit before trying again
                    usleep(50000); // 50ms
                }
                
                // Prevent infinite loop
                if ((microtime(true) - $start_time) > $this->lock_timeout) {
                    return false;
                }
            }
            
            // Create lock file
            @touch($this->lock_file);
            return true;
        }

        /**
         * Release the lock
         */
        protected function release_lock() {
            if (file_exists($this->lock_file)) {
                @unlink($this->lock_file);
            }
        }

        /**
         * Limit an array to the last X days
         * 
         * @param array $data The array to limit
         * @param int $days Number of days to keep
         * @param string $format Date format
         * @return array Limited array
         */
        protected function limit_array_days($data, $days, $format = 'Y-m-d') {
            if (count($data) <= $days) {
                return $data;
            }
            
            // Sort by date key
            ksort($data);
            
            // Keep only the most recent entries
            return array_slice($data, -$days, null, true);
        }

        /**
         * AJAX handler for resetting stats
         */
        public function ajax_reset_stats()
        {
            check_ajax_referer('fastpixel_reset_stats', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error('Permission denied');
            }

            $result = $this->reset_stats();

            if ($result) {
                wp_send_json_success();
            } else {
                wp_send_json_error('Failed to reset stats');
            }
        }
    }
}
