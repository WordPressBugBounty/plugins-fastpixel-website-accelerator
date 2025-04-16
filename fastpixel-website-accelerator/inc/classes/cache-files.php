<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Cache_Files')) {
    class FASTPIXEL_Cache_Files
    {
        protected $debug = false;
        public static $instance;
        protected $functions;
        protected $config;
        protected $request_wait_time = 300;
        protected $cache_dir;
        protected $url;
        protected $url_path;
        protected $html_path;
        protected $local_html_path;
        protected $json_path;
        protected $header_path;
        protected $meta_path;

        protected $page_cache_status;
        protected $serve_stale;
        protected $cache_exists = false;
        protected $display_for_logged = false;

        public function __construct() {
            self::$instance = $this;
            //initializing functions and config
            $this->functions = FASTPIXEL_Functions::get_instance();
            $this->config    = FASTPIXEL_Config_Model::get_instance();
            $this->serve_stale = $this->config->get_option('fastpixel_serve_stale');
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_Cache_Files();
            }
            return self::$instance;
        }

        public function serve($url) {
            if (is_a($url, 'FASTPIXEL\FASTPIXEL_Url')) {
                $this->url             = $url;
                $this->cache_dir       = $this->functions->get_cache_dir();
                $this->url_path        = $this->url->get_url_path();
                $this->html_path       = $this->cache_dir . DIRECTORY_SEPARATOR . $this->url_path . 'index.html';
                $this->local_html_path = $this->cache_dir . DIRECTORY_SEPARATOR . $this->url_path . 'index_local.html';
                $this->json_path       = $this->cache_dir . DIRECTORY_SEPARATOR . $this->url_path . 'index.json';
                $this->header_path     = $this->cache_dir . DIRECTORY_SEPARATOR . $this->url_path . 'headers.json';
                $this->meta_path       = $this->cache_dir . DIRECTORY_SEPARATOR . $this->url_path . 'meta';
            } else {
                return false;
            }
            if ($this->debug) {
                FASTPIXEL_DEBUG::log('Class FASTPIXEL_Cache_Files: Checking for cached page', $this->url_path);
            }
            //no need to continue if path is empty
            if (empty($this->url_path)) {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('Class FASTPIXEL_Cache_Files: Checking for cached page, url_path is empty', $this->url_path);
                }
                return false;
            }
            //getting page cache status
            $this->page_cache_status = $this->functions->check_post_cache_status($this->url->get_url());
            //updating meta depends on parent's data for paginated pages or pages with params
            if (preg_match('/page\/\d+/', $this->url->get_url()) || strpos($this->url->get_url(), '?') > 0) {
                if (preg_match('/page\/\d+/', $this->url->get_url())) {
                    $parent_url = preg_replace('/page.+$/i', '', $this->url->get_url());
                } else {
                    $stripped_parent_url = new FASTPIXEL_Url($this->url->get_url(), true);
                    $parent_url = $stripped_parent_url->get_url();
                }
                $parent_meta = $this->functions->check_post_cache_status($parent_url);
                if ((isset($this->page_cache_status['have_cache']) && $this->page_cache_status['have_cache'])
                    && (!empty($parent_meta['local_invalidation_time']) && $parent_meta['local_invalidation_time'] > $this->page_cache_status['local_invalidation_time']) 
                    && $this->page_cache_status['html_created_time'] < $parent_meta['local_invalidation_time']
                ) {
                    $this->page_cache_status['need_cache'] = true;
                    $this->page_cache_status['local_invalidation_time'] = $parent_meta['local_invalidation_time'];
                }
            }
            if ($this->debug) {
                $debug_array = [
                    'serve_stale'              => $this->config->get_option('fastpixel_serve_stale'),
                    'have_cache'               => $this->page_cache_status['have_cache'],
                    'need_cache'               => $this->page_cache_status['need_cache'],
                    'html_created_time'        => gmdate('Y-m-d H:i:s', $this->page_cache_status['html_created_time']) . ' -> ' . $this->page_cache_status['html_created_time'],
                    'local_html_created_time'  => gmdate('Y-m-d H:i:s', $this->page_cache_status['local_html_created_time']) . ' -> ' . $this->page_cache_status['local_html_created_time'],
                    'global_invalidation_time' => gmdate('Y-m-d H:i:s', $this->page_cache_status['global_invalidation_time']) . ' -> ' . $this->page_cache_status['global_invalidation_time'],
                    'local_invalidation_time'  => gmdate('Y-m-d H:i:s', $this->page_cache_status['local_invalidation_time']) . ' -> ' . $this->page_cache_status['local_invalidation_time'],
                    'last_cache_request_time'  => gmdate('Y-m-d H:i:s', $this->page_cache_status['last_cache_request_time']) . ' -> ' . $this->page_cache_status['last_cache_request_time'],
                    'error'                    => $this->page_cache_status['error'],
                    'error_time'               => $this->page_cache_status['error_time'],
                ];
                FASTPIXEL_DEBUG::log('Class FASTPIXEL_Cache_Files: Page cache status', $debug_array);
            }

            //checking for index.html and return if present
            if (!$this->return_optimized()) { 
                //checking for index_local.html and return if present
                $this->return_local();
            }
            //running action after cache files checked and returned if exists
            do_action('fastpixel/cachefiles/exists', $this->cache_exists);
            //handling wordpress shutdown
            $this->handle_wordpress_shutdown();
        }

        protected function return_optimized()
        {            
            //serve_stale is used to return old cached version while new cache was requested
            //if serve_stale is disabled and we have "invalidated" file then return "wordpress" or "local" page
            //else serve cached version if exists
            if (!$this->page_cache_status['have_cache']) {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('Class FASTPIXEL_Cache_Files: No cache files exist, nothing to return, skipping');
                }
                return false;
            }
            //no need to return cached page if serve_stale is disabled and page cache is reset
            if ($this->serve_stale == false && $this->page_cache_status['need_cache']) {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('Class FASTPIXEL_Cache: Cached page is not generated yet, nothing to return, skipping');
                }
                return false;
            }
            if ($this->debug) {
                FASTPIXEL_DEBUG::log('Class FASTPIXEL_Cache_Files: Checking for optimized page', $this->html_path);
            }
            if (file_exists($this->html_path) && is_readable($this->html_path)) {
                return $this->handle_cache_file();
            }
            return false;
        }

        protected function return_local()
        {
            if ($this->debug) {
                FASTPIXEL_DEBUG::log('Class FASTPIXEL_Cache_Files: Checking for local saved page', $this->local_html_path);
            }
            if (!file_exists($this->local_html_path) || !is_readable($this->local_html_path)) {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('Class FASTPIXEL_Cache_Files: Local page not exists or is not readable, skip');
                }
                return false;
            }
            if ($this->page_cache_status['global_invalidation_time'] > $this->page_cache_status['local_html_created_time']
                || $this->page_cache_status['local_invalidation_time'] > $this->page_cache_status['local_html_created_time']) {
                if ($this->debug) {
                    FASTPIXEL_DEBUG::log('Class FASTPIXEL_Cache_Files: Local page expired, skip');
                }
                return false;
            }
            return $this->handle_cache_file(true);
        }

        protected function handle_cache_file($local = false) {
            if ($local) {
                $path = $this->local_html_path;
            } else {
                $path = $this->html_path;
            }
            if ($this->debug) {
                FASTPIXEL_DEBUG::log('Class FASTPIXEL_Cache_Files: handling ' . ($local ? 'local' : '') . ' cached file(s), $path', $path);
            }
            //validations with debug
            if (empty($path)) {
                if ($this->debug) {
                    FASTPIXEL_Debug::log('Class FASTPIXEL_Cache_Files: Can\'t serve html file because $path is empty', $path);
                }
                return false;
            }
            if (@!file_exists($path)) {
                if ($this->debug) {
                    FASTPIXEL_Debug::log('Class FASTPIXEL_Cache_Files: Can\'t serve html file because $path not exists', $path);
                }
                return false;
            }
            if (@!is_readable($path)) {
                if ($this->debug) {
                    FASTPIXEL_Debug::log('Class FASTPIXEL_Cache_Files: Can\'t serve html file because $path is not readable', $path);
                }
                return false;
            }
            //extra check if file is inside cache folder
            if (strpos(realpath($path), realpath($this->functions->get_cache_dir())) !== 0) {
                if ($this->debug) {
                    FASTPIXEL_Debug::log('Class FASTPIXEL_Cache_Files: Can\'t serve html file because requested $path is not in cache directory');
                }
                return false;
            }
            $modified_time = (int) @filemtime($path);
            if (!empty($modified_time) && !empty($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($this->functions->sanitize_text_field($_SERVER['HTTP_IF_MODIFIED_SINCE'])) === $modified_time) {
                $valid_protocols = ['HTTP/1.0', 'HTTP/1.1', 'HTTP/2.0', 'HTTP/3'];
                $protocol = strtoupper($this->functions->sanitize_text_field($_SERVER['SERVER_PROTOCOL'])) ?? false;
                if (!in_array($protocol, $valid_protocols)) {
                    $protocol = $valid_protocols[1]; // use HTTP/1.1 as default protocol
                }
                header($protocol . '304 Not Modified', true, 304);
                exit;
            }
            if (@file_exists($this->header_path) && @is_readable($this->header_path)) {
                /*
                 * can't use here wordpress native functions(WP_Filesystem) because this function fires early in advanced-cache.php
                 */
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- none available before WordPress is loaded..
                $headers = json_decode(@file_get_contents($this->header_path)) ?: []; //phpcs:ignore
                foreach ($headers as $header) {
                    header($header);
                }
            }
            if ($local) {
                $headers = ['X-FastPixel-Local-Cache: HIT'];
            } else {
                $headers = ['X-FastPixel-Cache: HIT'];
            }
            $mtime = filemtime($path);
            $headers[] = 'Age: ' . (time() - $mtime);
            $headers[] = 'X-Fastpixel-Age: ' . (time() - $mtime);
            $headers = apply_filters('fastpixel_return_cached_page_headers', $headers);
            foreach ($headers as $header) {
                header($header);
            }
            if ($local == false && $this->config->get_option('fastpixel_display_cached_for_logged') && $this->functions->user_is_logged_in()) {
                $this->display_for_logged = true;
                //registering hook that will display cached page before page rendering should begin
                add_action('template_redirect', [$this, 'display_optimized_for_logged'], 999);
            } else {
                /*
                * displaying .html file, generated by our service
                * no need to escape output, escape functions are not available in advanced-cache.php
                */
                ob_start();
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- direct file output to the browser.
                readfile($path); // phpcs:ignore
                if ($local) {
                    echo '<!-- Served by FastPixel.io -->';
                } else {
                    echo '<!-- Optimized and served by FastPixel.io -->';
                }
            }
            if ($this->debug) {
                FASTPIXEL_DEBUG::log('Class FASTPIXEL_Cache_Files: returning index'.($local ? '_local':'').'.html');
            }
            $this->cache_exists = true;
            return true;
        }

        public function display_optimized_for_logged()
        {
            global $wp_styles, $wp_scripts;
            ob_start();
            $post_id = get_the_ID();
            do_action('fastpixel/loggeduser/adminbar/beforerender', $post_id);
            foreach ($wp_styles->queue as $handle) {
                wp_print_styles($handle);
            }
            foreach ($wp_scripts->queue as $handle) {
                wp_print_scripts($handle);
            }
            wp_admin_bar_render();
            do_action('fastpixel/loggeduser/adminbar/afterrender', $post_id);
            $admin_bar_output = ob_get_clean();
            ob_start(function ($buffer) use ($admin_bar_output) {
                $regex = '/<\s*\/\s*body/s';
                $buffer = preg_replace($regex, $admin_bar_output . '</body', $buffer);
                $buffer .= '<!-- Optimized and served by FastPixel.io -->';
                return $buffer;
            });
            /*
             * displaying .html file, generated by our service
             * no need to escape output, escape functions are not available in advanced-cache.php
             */
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- direct file output to the browser.
            if (@file_exists($this->html_path) && @is_readable($this->html_path)) {
                readfile($this->html_path); // phpcs:ignore
            }
            ob_get_flush();
            exit;
        }

        protected function handle_wordpress_shutdown() {
            //handle shutdown at the right hook to be able do cache request
            $fastpixel_cache = FASTPIXEL_Cache::get_instance();
            if ($this->display_for_logged == false) {
                if ($this->cache_exists) {
                    if ($this->page_cache_status['need_cache'] //checking if we need cache request
                        && (!$this->page_cache_status['last_cache_request_time'] //when never requested
                        || (time() > ($this->page_cache_status['last_cache_request_time'] + $this->request_wait_time)) //when time spent more than $request_wait_time
                    )) {
                        add_action('wp', function () {
                            if ($this->debug) {
                                FASTPIXEL_Debug::log('Class FASTPIXEL_Cache_files: stopping wordpress on wp hook');
                            }
                            //stopping wordpress, all required functions should be already loaded
                            exit();
                        });
                    } else {
                        if ($this->debug) {
                            FASTPIXEL_Debug::log('Class FASTPIXEL_Cache_files: stopping wordpress and removing request');
                        }
                        @ob_end_flush();
                        flush();
                        //removing default hook which do cache request
                        remove_action('fastpixel/shutdown', [$fastpixel_cache, 'request_page_cache'], 20);
                        exit();
                    }
                }
            } else {
                //removing default hook which do cache request
                remove_action('fastpixel/shutdown', [$fastpixel_cache, 'request_page_cache'], 20);
                if ($this->debug) {
                    FASTPIXEL_Debug::log('Class FASTPIXEL_Cache_files: display_for_logged is true, no need to stop wordpress, page cache request removed');
                }
            }
        }
    }
    new FASTPIXEL_Cache_Files();
}
