<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Diag_Test')) {
    abstract class FASTPIXEL_Diag_Test
    {
        protected $order_id = 0;
        protected $name;
        protected $passed = false;
        protected $notification_messages = [];
        protected $array_result = false;
        protected $visible_on_diagnostics_page = true;

        public function __construct()
        {
            if (!class_exists('FASTPIXEL\FASTPIXEL_Diag')) {
                return;
            }
            $diag_controller = FASTPIXEL_Diag::get_instance();
            $diag_controller->add_test_model($this);
            add_action('init', [$this, 'l10n_name'], 10);
        }

        public function get_order_id() {
            return $this->order_id;
        }

        protected function add_notification_message($text, $type = 'error')
        {
            $this->notification_messages[] = ['text' => $text, 'type' => $type];
        }

        abstract public function test();

        public function activation_test() {
            return true;
        }

        public function get_information() {
            //running test before return tests info
            $this->test();
            return [
                'name'                  => $this->name,
                'status'                => $this->passed,
                'notification_messages' => $this->notification_messages,
                'array_result'          => $this->array_result,
                'display'               => $this->get_display()
            ];
        }

        public function get_display() {
            return $this->passed;
        }
        
        public function display_on_diag_page() {
            return $this->visible_on_diagnostics_page;
        }

        abstract public function l10n_name();
    }
}
