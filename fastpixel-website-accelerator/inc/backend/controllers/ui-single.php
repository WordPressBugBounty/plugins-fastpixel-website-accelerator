<?php
namespace FASTPIXEL;

if (!class_exists('FASTPIXEL\FASTPIXEL_UI_Single')) {
    class FASTPIXEL_UI_Single extends FASTPIXEL_UI
    {

        public function __construct()
        {
            parent::__construct();
            add_action('admin_bar_menu', [$this, 'admin_bar'], 90);
            add_action('admin_menu', [$this, 'admin_menu']);
        }

        public function admin_menu()
        {
            if (!$this->check_capabilities()) {
                return;
            }
            add_menu_page(FASTPIXEL_NAME, FASTPIXEL_NAME, 'manage_options', FASTPIXEL_TEXTDOMAIN, [$this, 'status_page'], ($this->icon_url ? $this->icon_url : ''), 99);
            add_submenu_page(FASTPIXEL_TEXTDOMAIN, esc_html__('Cache Status', 'fastpixel-website-accelerator'), esc_html__('Cache Status', 'fastpixel-website-accelerator'), 'manage_options', FASTPIXEL_TEXTDOMAIN, [$this, 'status_page'], 1);
            add_submenu_page(FASTPIXEL_TEXTDOMAIN, esc_html__('Settings', 'fastpixel-website-accelerator'), esc_html__('Settings', 'fastpixel-website-accelerator'), 'manage_options', FASTPIXEL_TEXTDOMAIN . '-settings', [$this, 'settings_page'], 2);
            //we can display purge all button in left menu if client needs it and define it in wp-config
            if (defined('FASTPIXEL_LEFT_MENU_PURGE') && FASTPIXEL_LEFT_MENU_PURGE) {
                add_submenu_page(FASTPIXEL_TEXTDOMAIN, esc_html__('Purge All Cache', 'fastpixel-website-accelerator'), esc_html__('Purge All Cache', 'fastpixel-website-accelerator'), 'manage_options', esc_url(wp_nonce_url(admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN . '&fastpixel-action=fastpixel_purge_cache'), 'fastpixel_purge_cache', 'fastpixel_cache_nonce')), null, 3);
            }
        }

        public function admin_bar()
        {
            if (!$this->check_capabilities()) {
                return;
            }
            global $wp_admin_bar;
            $wp_admin_bar->add_menu([
                'id'    => 'fastpixel-top-' . FASTPIXEL_TEXTDOMAIN . '-menu',
                'href'  => esc_url(admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN)),
                'title' => ($this->get_icon() ? $this->get_icon() : '') . '<span class="fastpixel-text">' . esc_html__('FastPixel Cache', 'fastpixel-website-accelerator') . '</span>',
                'meta'  => ['title' => esc_html__('FastPixel Cache', 'fastpixel-website-accelerator')],
            ]);
            $wp_admin_bar->add_node([
                'id'     => 'fastpixel-top-' . FASTPIXEL_TEXTDOMAIN . '-status',
                'parent' => 'fastpixel-top-' . FASTPIXEL_TEXTDOMAIN . '-menu',
                'href'   => esc_url(admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN)),
                'title'  => esc_html__('Cache Status', 'fastpixel-website-accelerator'),
            ]);
            $wp_admin_bar->add_node([
                'id'     => 'fastpixel-top-' . FASTPIXEL_TEXTDOMAIN . '-settings',
                'parent' => 'fastpixel-top-' . FASTPIXEL_TEXTDOMAIN . '-menu',
                'href'   => esc_url(admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN . '-settings')),
                'title'  => esc_html__('Settings', 'fastpixel-website-accelerator'),
            ]);
            $this->admin_bar_purge_button($wp_admin_bar); //there can be button for purge single post/page/taxonomy/author/archive
            $wp_admin_bar->add_node([
                'id'     => 'fastpixel-top-' . FASTPIXEL_TEXTDOMAIN . '-purge-cache',
                'parent' => 'fastpixel-top-' . FASTPIXEL_TEXTDOMAIN . '-menu',
                'href'   => esc_url(wp_nonce_url(admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN . '&fastpixel-action=fastpixel_purge_cache'), 'fastpixel_purge_cache', 'fastpixel_cache_nonce')),
                'title'  => esc_html__('Purge All Cache', 'fastpixel-website-accelerator'),
            ]);
        }
    }
}
