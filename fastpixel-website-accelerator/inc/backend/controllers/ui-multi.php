<?php
namespace FASTPIXEL;

if (!class_exists('FASTPIXEL\FASTPIXEL_UI_Multi')) {
    class FASTPIXEL_UI_Multi extends FASTPIXEL_UI
    {
        public function __construct()
        {
            parent::__construct();
            self::$instance = $this;
            add_action('admin_bar_menu', [$this, 'admin_bar'], 90);
            add_action('admin_menu', [$this, 'admin_menu']);
            add_action('network_admin_menu', [$this, 'network_admin_menu']);
        }

        public static function get_instance()
        {
            if (empty(self::$instance)) {
                self::$instance = new FASTPIXEL_UI_Multi();
            }
            return self::$instance;
        }

        public function admin_menu()
        {
            add_menu_page(FASTPIXEL_NAME, FASTPIXEL_NAME, 'manage_options', FASTPIXEL_TEXTDOMAIN, [$this, 'status_page'], ($this->icon_url ? $this->icon_url : ''), 99);
            add_submenu_page(FASTPIXEL_TEXTDOMAIN, esc_html__('Cache Status', 'fastpixel-website-accelerator'), esc_html__('Cache Status', 'fastpixel-website-accelerator'), 'manage_options', FASTPIXEL_TEXTDOMAIN, [$this, 'status_page'], 1);
            //we can display purge all button in left menu if client needs it and define it in wp-config
            if (defined('FASTPIXEL_LEFT_MENU_PURGE') && FASTPIXEL_LEFT_MENU_PURGE) {
                add_submenu_page(FASTPIXEL_TEXTDOMAIN, esc_html__('Purge All Cache', 'fastpixel-website-accelerator'), esc_html__('Purge All Cache', 'fastpixel-website-accelerator'), 'manage_options', esc_url(wp_nonce_url(admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN . '&fastpixel-action=fastpixel_purge_cache'), 'fastpixel_purge_cache', 'fastpixel_cache_nonce')), null, 2);
            }
        }

        public function network_admin_menu()
        {
            add_menu_page(FASTPIXEL_NAME, FASTPIXEL_NAME, 'manage_network', FASTPIXEL_TEXTDOMAIN . '-settings', [$this, 'settings_page'], ($this->icon_url ? $this->icon_url : null), 99);
        }

        public function admin_bar()
        {
            if (!$this->check_capabilities()) {
                return;
            }
            global $wp_admin_bar;
            $wp_admin_bar->add_node([
                'id'    => 'fastpixel-top-' . FASTPIXEL_TEXTDOMAIN . '-menu',
                'href'  => esc_url(admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN)),
                'title' => ($this->get_icon() ? $this->get_icon() : '') . '<span class="fastpixel-text">' . esc_html__('FastPixel Cache', 'fastpixel-website-accelerator') . '</span>',
                'meta'  => ['title' => esc_html__('FastPixel Cache', 'fastpixel-website-accelerator')]
            ]);
            //we can add status link when not in network admin
            if (!is_network_admin() && $this->check_network_capabilities()) {
                $wp_admin_bar->add_node([
                    'id'     => 'fastpixel-top-' . FASTPIXEL_TEXTDOMAIN . '-status',
                    'parent' => 'fastpixel-top-' . FASTPIXEL_TEXTDOMAIN . '-menu',
                    'href'   => esc_url(admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN)),
                    'title'  => esc_html__('Cache Status', 'fastpixel-website-accelerator'),
                ]);
            }
            if ($this->check_network_capabilities()) {
                $wp_admin_bar->add_node([
                    'id'     => 'fastpixel-top-' . FASTPIXEL_TEXTDOMAIN . '-settings',
                    'parent' => 'fastpixel-top-' . FASTPIXEL_TEXTDOMAIN . '-menu',
                    'href'   => esc_url(network_admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN . '-settings')),
                    'title'  => esc_html__('Settings', 'fastpixel-website-accelerator'),
                ]);
            }
            $this->admin_bar_purge_button($wp_admin_bar); //there can be button for purge single post/page/taxonomy/author/archive
            $wp_admin_bar->add_node([
                'id'     => 'fastpixel-top-' . FASTPIXEL_TEXTDOMAIN . '-purge-cache',
                'parent' => 'fastpixel-top-' . FASTPIXEL_TEXTDOMAIN . '-menu',
                'href'   => esc_url(wp_nonce_url(admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN . '&amp;fastpixel-action=fastpixel_purge_cache'), 'fastpixel_purge_cache', 'fastpixel_cache_nonce')),
                'title'  => esc_html__('Purge All Cache', 'fastpixel-website-accelerator'),
            ]);
        }

        protected function check_network_capabilities()
        {
            // check user capabilities
            if (!current_user_can('manage_network')) {
                return false;
            }
            return true;
        }
    }
}
