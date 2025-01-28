<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Diag_Test_Empty_Hostname')) {
    class FASTPIXEL_Diag_Test_Empty_Hostname extends FASTPIXEL_Diag_Test
    {
        protected $order_id = 40;
        protected $name = 'Empty Hostname';
        protected $visible_on_diagnostics_page = false;


        public function __construct()
        {
            parent::__construct();
        }

        public function test()
        {
            $host_name = '';
            if (!empty($_SERVER['HTTP_HOST'])) {
                $host_name = rtrim($_SERVER['HTTP_HOST'], '/');
            } else if (defined('WP_HOME')) {
                $host_name = rtrim(parse_url(WP_HOME, PHP_URL_HOST), '/');
            } else if (!empty($_SERVER['SERVER_NAME'])) {
                $host_name = rtrim($_SERVER['SERVER_NAME'], '/');
            }
            if (!empty($host_name)) {
                $this->passed = true;
            } else {
                $this->add_notification_message(esc_html__('Can\'t detect HOST name.', 'fastpixel-website-accelerator'), 'error');
            }
        }

        public function l10n_name()
        {
            $this->name = esc_html__('Empty Hostname', 'fastpixel-website-accelerator');
        }

    }
    new FASTPIXEL_Diag_Test_Empty_Hostname();
}
