<?php
namespace FASTPIXEL;

if (!class_exists('FASTPIXEL\FASTPIXEL_UI_Tab')) {
    abstract class FASTPIXEL_UI_Tab
    {
        protected $enabled = true;
        protected $order = 0;
        protected $name;
        protected $slug;
        protected $functions;
        protected $be_functions;

        public function __construct() {
            $this->functions = FASTPIXEL_Functions::get_instance();
            $this->be_functions = FASTPIXEL_Backend_Functions::get_instance();
            //registering tab settings
            $this->settings();
            //adding tab to UI
            $ui = FASTPIXEL_UI::get_instance();
            $ui->add_tab($this);
        }

        public function enable() {
            $this->enabled = true;
        }
        
        public function disable() {
            $this->enabled = false;
        }

        public function is_enabled() {
            return $this->enabled;
        }

        public function get_order() {
            return $this->order;
        }
        public function get_name() {
            return $this->name;
        }

        public function get_slug() {
            return $this->slug;
        }

        public function get_link() {
            if (!is_multisite()) {
                return esc_url(admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN . '&tab=' . $this->slug));
            } else {
                return esc_url(network_admin_url('admin.php?page=' . FASTPIXEL_TEXTDOMAIN . '&tab=' . $this->slug));
            }
        }

        abstract public function settings();

        public function view() {
            $slug = str_replace('_', '-', $this->slug);
            if (file_exists(FASTPIXEL_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . $slug . '.php')) {
                include_once FASTPIXEL_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . $slug . '.php';
            }
        }

        protected function check_capabilities()
        {
            // check user capabilities
            if (!current_user_can('manage_options')) {
                return false;
            }
            return true;
        }
    }
}
