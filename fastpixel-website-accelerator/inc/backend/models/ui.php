<?php
namespace FASTPIXEL;

if (!class_exists('FASTPIXEL\FASTPIXEL_UI')) {
    abstract class FASTPIXEL_UI
    {

        protected static $instance;
        protected $tabs = [];
        protected $icon_url;
        protected $current_tab = 'cache-status';
        protected $allowed_tags = [
            'h2'  => [
                'class' => []
            ],
            'div' => [
                'class' => []
            ],
            'a'   => [
                'href'   => [],
                'target' => []
            ]
        ];

        public function __construct()
        {
            self::$instance = $this;
            if (!\is_user_logged_in()) {
                return;
            }
            if (file_exists(FASTPIXEL_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'icons' . DIRECTORY_SEPARATOR . 'FastPixel-Happy.svg')) {
                $this->icon_url = esc_url(FASTPIXEL_PLUGIN_URL . 'icons/FastPixel-Happy.svg');
            }
            global $pagenow;
            if ($pagenow == 'plugins.php') {
                add_filter('plugin_action_links', [$this, 'plugin_action_links'], 10, 2);
                add_filter('plugin_action_links_' . plugin_basename(FASTPIXEL_PLUGIN_FILE), array($this, 'filter_action_links'));
                add_filter('network_admin_plugin_action_links_' . plugin_basename(FASTPIXEL_PLUGIN_FILE), array($this, 'filter_action_links'));
                add_action('admin_init', [$this, 'deactivation_popup']);
                add_action('admin_footer', function () {
                    echo '<div class="fastpixel-deactivate-form-bg"></div>';
                });
            }
            wp_enqueue_style('fastpixel_admin_menu_css', FASTPIXEL_PLUGIN_URL . 'inc/backend/assets/admin-menu.css?' . time(), [], FASTPIXEL_VERSION);
            //initializing tabs only when page is opened
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- wordpress page is accessed without any nonces, no data is posted.
            $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : false;
            if ($pagenow == 'admin.php' && $page && in_array($page, [FASTPIXEL_TEXTDOMAIN, FASTPIXEL_TEXTDOMAIN . '-settings'])) {
                wp_enqueue_style('fastpixel_admin_css', FASTPIXEL_PLUGIN_URL . 'inc/backend/assets/backend.css?' . time(), [], FASTPIXEL_VERSION);
                add_action('admin_init', [$this, 'init_tabs']);
                add_action('load-toplevel_page_' . FASTPIXEL_TEXTDOMAIN, [$this, 'screen_options']);
                add_filter('set-screen-option', [$this, 'save_screen_options'], 10, 3);
            }
            //adding scripts only when page is opened
            add_action('admin_enqueue_scripts', function () {
                wp_register_script('fastpixel-backend', FASTPIXEL_PLUGIN_URL . 'inc/backend/assets/backend.js?' . time(), array('jquery-ui-core', 'jquery-ui-tabs'), FASTPIXEL_VERSION, false);
                wp_localize_script('fastpixel-backend', 'fastpixel_backend', [
                    'ajax_url'                 => admin_url('admin-ajax.php'),
                    'nonce'                    => wp_create_nonce('cache_status_nonce'),
                    'deactivate_plugin_nonce'  => wp_create_nonce("fastpixel_deactivate_plugin"),
                    'delete_cached_files_text' => esc_html__('Delete Cached Files', 'fastpixel-website-accelerator'),
                    'delete_cached_files_link' => sprintf('admin-post.php?action=%1$s&nonce=%2$s&post_id=', 'fastpixel_admin_delete_cached', wp_create_nonce('cache_status_nonce')),
                    'cache_now_text'           => esc_html__('Cache Now', 'fastpixel-website-accelerator'),
                    'purge_cache_text'         => esc_html__('Purge Cache', 'fastpixel-website-accelerator'),
                    'purge_post_link'          => sprintf('admin-post.php?action=%1$s&nonce=%2$s&post_id=', 'fastpixel_admin_purge_post_cache', wp_create_nonce('cache_status_nonce')),
                    'deactivate_plugin_text'   => esc_html__('DEACTIVATED', 'fastpixel-website-accelerator')
                ]);
                global $pagenow;
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- wordpress page is accessed without any nonces, no data is posted.
                $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : false;
                if ($pagenow == 'admin.php' && $page && in_array($page, [FASTPIXEL_TEXTDOMAIN, FASTPIXEL_TEXTDOMAIN . '-settings'])) {
                    wp_enqueue_script('fastpixel-backend');
                }
            });
        }

        public static function get_instance()
        {
            return self::$instance;
        }

        public function init_tabs()
        {
            if ($handle = opendir(FASTPIXEL_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . 'tabs')) {
                while (false !== ($entry = readdir($handle))) {
                    if (!in_array($entry, ['.', '..'])) {
                        try {
                            include_once FASTPIXEL_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . 'tabs' . DIRECTORY_SEPARATOR . $entry;
                        } catch (\Exception $e) {
                            FASTPIXEL_DEBUG::log('Exception message -> ', $e->getMessage());
                        }
                    }
                }
                closedir($handle);
            }
            usort(
                $this->tabs,
                function ($a, $b) {
                    return $a->get_order() > $b->get_order() ? 1 : ($a->get_order() == $b->get_order() ? 0 : -1);
                }
            );
        }

        public function page_title($page_name)
        {
            switch ($page_name) {
                case 'status':
                    return '<h2 class="fastpixel-page-title">' . esc_html__('FastPixel', 'fastpixel-website-accelerator') . '</h2>';
                    break;
                case 'settings':
                    return '<h2 class="fastpixel-page-title">' . esc_html__('FastPixel Settings', 'fastpixel-website-accelerator') . '</h2>';
                    break;
                default:
                    return '<h2 class="fastpixel-page-title">' . esc_html__('FastPixel', 'fastpixel-website-accelerator') . '</h2>';
                    break;
            }
        }

        public function add_tab($tab)
        {
            $this->tabs[] = $tab;
        }

        public function get_tabs()
        {
            return $this->tabs;
        }

        public function plugin_action_links($plugin_actions, $plugin_file)
        {
            if (!$this->check_capabilities()) {
                return;
            }
            if (defined('FASTPIXEL_PLUGIN_FILE') && strpos(FASTPIXEL_PLUGIN_FILE, $plugin_file)) {
                return array_merge(
                    array(
                        'settings' => '<a href="' . esc_url(\admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN . '-settings')) . '">' . esc_html__('Settings', 'fastpixel-website-accelerator') . '</a>',
                    ),
                    $plugin_actions
                );
            }
            return $plugin_actions;
        }

        abstract public function admin_bar();
        abstract public function admin_menu();

        public function get_icon()
        {
            return '<div class="fastpixel-top-menu-icon"><img src="' . $this->icon_url . '" /></div>';
        }

        public function submenu()
        {
            return '<div class="fastpixel-submenu">
                    <a href="https://fastpixel.io/pricing/" target="_blank">' . esc_html__('Upgrade Plan', 'fastpixel-website-accelerator') . '</a>
                    <a href="https://fastpixel.io/docs/" target="_blank">' . esc_html__('Knowledge Base', 'fastpixel-website-accelerator') . '</a>
                    <a href="https://fastpixel.io/#contact" target="_blank">' . esc_html__('Contact Support', 'fastpixel-website-accelerator') . '</a>
                    <a href="https://dash.fastpixel.io/" target="_blank">' . esc_html__('FastPixel Account', 'fastpixel-website-accelerator') . '</a>
                    </div>';
        }

        // add screen options
        public function screen_options()
        {
            $screen = get_current_screen();
            // get out of here if we are not on our settings page
            if (!is_object($screen) || $screen->id != 'toplevel_page_' . FASTPIXEL_TEXTDOMAIN) {
                return;
            }
            $args = array(
                'label'   => esc_html__('Number of items per page', 'fastpixel-website-accelerator'),
                'default' => 20,
                'option'  => 'fastpixel_per_page'
            );
            add_screen_option('per_page', $args);
        }

        public function save_screen_options($status, $option, $value)
        {
            if ($option == 'fastpixel_per_page') {
                // Process and save the value as needed
                return (int) $value;
            }
            return $status;
        }

        public function status_page()
        {
            if (!$this->check_capabilities()) {
                return;
            }
            echo '<div class="wrap fastpixel-website-accelerator-wrap">';
            echo wp_kses($this->page_title('status'), $this->allowed_tags);
            echo wp_kses($this->submenu(), $this->allowed_tags);
            foreach ($this->tabs as $tab) {
                if (!in_array($tab->get_slug(), array('cache-status')) || !$tab->is_enabled()) {
                    continue;
                }
                $tab->view();
            }
            echo '</div>';
        }

        public function settings_page()
        {
            if (!$this->check_capabilities()) {
                return;
            }
            $page_tabs = array('settings', 'javascript', 'images', 'fonts', 'diagnostics', 'presets');
            echo '<div class="wrap fastpixel-website-accelerator-wrap">';
            echo wp_kses($this->page_title('settings'), $this->allowed_tags);
            echo wp_kses($this->submenu(), $this->allowed_tags);
            echo '<div id="fastpixel-tabs"><ul class="fastpixel-settings-tabs">';
            foreach ($this->tabs as $tab) {
                if (!in_array($tab->get_slug(), $page_tabs) || !$tab->is_enabled()) {
                    continue;
                }
                echo '<li data-slug="' . esc_attr($tab->get_slug()) . '"><a class="fastpixel-tab" href="#' . esc_attr($tab->get_slug()) . '">' . wp_kses_post($tab->get_name()) . '</a></li>';
            }
            echo '</ul><form id="fastpixel-settings-form" name="fastpixel-settings-form" method="post">';
            wp_nonce_field('fastpixel-settings', 'fastpixel-nonce', false);
            echo '<input type="hidden" name="fastpixel-action" value="save_settings" />';
            foreach ($this->tabs as $tab) {
                if (!in_array($tab->get_slug(), $page_tabs) || !$tab->is_enabled()) {
                    continue;
                }
                echo '<div id="' . esc_attr($tab->get_slug()) . '" class="fastpixel-tab-content">';
                $tab->view();
                echo '</div>';
            }
            echo '</form></div>';
        }
        public function deactivation_popup()
        {
            global $pagenow;
            if (strcmp($pagenow, 'plugins.php') == 0) {
                add_action('admin_enqueue_scripts', function () {
                    wp_enqueue_style('fastpixel-popup', FASTPIXEL_PLUGIN_URL . 'inc/backend/assets/backend.css', [], FASTPIXEL_VERSION);
                    wp_enqueue_script('fastpixel-popup', FASTPIXEL_PLUGIN_URL . 'inc/backend/assets/popup.js', ['jquery'], FASTPIXEL_VERSION, false);
                    wp_localize_script('fastpixel-popup', 'fastpixel_popup', [
                        'nonce'              => wp_create_nonce('fastpixel_deactivate_plugin'),
                        'deactivate_link_id' => "#fastpixel-deactivate-link",
                        'form_container'     => '#fastpixel-deactivate-form-container',
                        'options'            => [
                            'temporary' => [
                                'text' => esc_html__('Temporary deactivation', 'fastpixel-website-accelerator'),
                                'display_textarea' => false,
                                'textarea_text' => ''
                            ],
                            'site-broken-after-optimization' => [
                                'text' => esc_html__('Site is broken after optimization', 'fastpixel-website-accelerator'),
                                'display_textarea' => true,
                                'textarea_text' => esc_html__('If possible, please describe issue', 'fastpixel-website-accelerator')
                            ],
                            'not-fast-enough' => [
                                'text'             => esc_html__('Not fast enough', 'fastpixel-website-accelerator'),
                                'display_textarea' => true,
                                'textarea_text'    => esc_html__('Please specify', 'fastpixel-website-accelerator')
                            ],
                            'setup' => [
                                'text' => esc_html__('Set up is too difficult', 'fastpixel-website-accelerator'),
                                'display_textarea' => true,
                                'textarea_text'    => esc_html__('What was the dificult part ?', 'fastpixel-website-accelerator')
                            ],
                            'docs' => [
                                'text' => esc_html__('Lack of documentation', 'fastpixel-website-accelerator'),
                                'display_textarea' => true,
                                'textarea_text'    => esc_html__('What can we describe more ?', 'fastpixel-website-accelerator')
                            ],
                            'features' => [
                                'text' => esc_html__('Not the features I wanted', 'fastpixel-website-accelerator'),
                                'display_textarea' => true,
                                'textarea_text'    => esc_html__('How could we improve ?', 'fastpixel-website-accelerator')
                            ],
                            'better-plugin' => [
                                'text' =>esc_html__('Found a better plugin', 'fastpixel-website-accelerator'),
                                'display_textarea' => true,
                                'textarea_text'    => esc_html__('Can you mention it ?', 'fastpixel-website-accelerator')
                            ],
                            'incompatibility' => [
                                'text' => esc_html__('Incompatible with theme or plugin', 'fastpixel-website-accelerator'),
                                'display_textarea' => true,
                                'textarea_text'    => esc_html__('With what plugin or theme is incompatible ?', 'fastpixel-website-accelerator')
                            ],
                            'other' => [
                                'text'             => esc_html__('Other', 'fastpixel-website-accelerator'),
                                'display_textarea' => true,
                                'textarea_text'    => esc_html__('Please specify', 'fastpixel-website-accelerator')
                            ],
                        ],
                        'translations'   => [
                            'title'                     => esc_html__('Sorry to see you go', 'fastpixel-website-accelerator'),
                            'main_text'                 => esc_html__('Before you deactivate the plugin, would you quickly give us your reason for doing so?', 'fastpixel-website-accelerator'),
                            'submitting_form'           => esc_html__('Submitting form', 'fastpixel-website-accelerator'),
                            'send_anonymous'            => esc_html__('Send anonymous', 'fastpixel-website-accelerator'),
                            'delete_cached_files'       => esc_html__('Delete cached files', 'fastpixel-webisite-accelerator'),
                            'btn_deactivate'            => esc_html__('Deactivate', 'fastpixel-webisite-accelerator'),
                            'btn_submit_and_deactivate' => esc_html__('Submit and Deactivate', 'fastpixel-webisite-accelerator'),
                            // 'deactivation_message' => esc_html__('Do you want to delete cached files before plugin deactivation?', 'fastpixel-webisite-accelerator'),
                        ],
                    ]);
                    if (is_multisite()) {
                        wp_localize_script('fastpixel-popup', 'fastpixel_popup_deactivation_links', [
                            'deactivate_link' => wp_nonce_url(network_admin_url('plugins.php?action=deactivate&plugin=' . plugin_basename(FASTPIXEL_PLUGIN_FILE)), 'deactivate-plugin_' . plugin_basename(FASTPIXEL_PLUGIN_FILE)),
                            'delete_link'     => wp_nonce_url(network_admin_url('plugins.php?action=deactivate&plugin=' . plugin_basename(FASTPIXEL_PLUGIN_FILE)) . '&fastpixel-action=delete_cached_files', 'deactivate-plugin_' . plugin_basename(FASTPIXEL_PLUGIN_FILE)),
                        ]);
                    } else {
                        wp_localize_script('fastpixel-popup', 'fastpixel_popup_deactivation_links', [
                            'deactivate_link' => wp_nonce_url(admin_url('plugins.php?action=deactivate&plugin=' . plugin_basename(FASTPIXEL_PLUGIN_FILE)), 'deactivate-plugin_' . plugin_basename(FASTPIXEL_PLUGIN_FILE)),
                            'delete_link'     => wp_nonce_url(admin_url('plugins.php?action=deactivate&plugin=' . plugin_basename(FASTPIXEL_PLUGIN_FILE)) . '&fastpixel-action=delete_cached_files', 'deactivate-plugin_' . plugin_basename(FASTPIXEL_PLUGIN_FILE)),
                        ]);
                    }
                });
            }
        }

        protected function check_capabilities() {
            // check user capabilities
            if (!current_user_can('manage_options')) {
                return false;
            }
            return true;
        }

        public function filter_action_links($links) {
            if (!$this->check_capabilities()) {
                return;
            }
            if (isset($links['deactivate'])) {
                $deactivation_link = $links['deactivate'];
                // Insert an onClick action to allow form before deactivating
                $deactivation_link = str_replace('<a ',
                '<div class="fastpixel-deactivate-form-wrapper" id="fastpixel-deactivate-form-container"></div><a id="fastpixel-deactivate-link" ',
                $deactivation_link
                );
                $links['deactivate'] = $deactivation_link;
            }
            return $links;
        }

        protected function admin_bar_purge_button($wp_admin_bar) {
            if (!is_admin() && function_exists('is_user_logged_in') && is_user_logged_in() && !empty($wp_admin_bar)) {
                $page_on_front = get_option('page_on_front');
                if (!$page_on_front && is_home()) {
                    $link = admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN . '&fastpixel-action=fastpixel_purge_single_cache&purge_id=homepage');
                } else if (is_tag() || is_tax() || is_category()) {
                    $tax = get_term(get_queried_object());
                    if (isset($tax->term_id) && !empty($tax->term_id)) {
                        $link = admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN . '&fastpixel-action=fastpixel_purge_single_cache&purge_id=' . $tax->term_id . '&purge_type=taxonomy');
                    }
                } else if (is_author()) {
                    $author = get_queried_object();
                    if (isset($author->ID) && !empty($author->ID)) {
                        $link = admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN . '&fastpixel-action=fastpixel_purge_single_cache&purge_id=' . $author->ID . '&purge_type=author');
                    }
                } else if (is_archive()) {
                    $archive = get_queried_object();
                    if (isset($archive->name) && !empty($archive->name)) {
                        $link = admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN . '&fastpixel-action=fastpixel_purge_single_cache&purge_id=' . $archive->name . '&purge_type=archive');
                    }
                } else {
                    global $post;
                    if (isset($post->ID) && !empty($post->ID) && is_numeric($post->ID)) {
                        $link = admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN . '&fastpixel-action=fastpixel_purge_single_cache&purge_id=' . $post->ID . '&purge_type=' . $post->post_type);
                    }
                }
                if (isset($link) && !empty($link)) {
                    $wp_admin_bar->add_node([
                        'id'     => 'fastpixel-top-' . FASTPIXEL_TEXTDOMAIN . '-purge-current-cache',
                        'parent' => 'fastpixel-top-' . FASTPIXEL_TEXTDOMAIN . '-menu',
                        'href'   => esc_url(wp_nonce_url($link, 'fastpixel_purge_cache', 'fastpixel_cache_nonce')),
                        'title'  => esc_html__('Purge Curent Page Cache', 'fastpixel-website-accelerator'),
                    ]);
                }
            }
        }
    }
}
