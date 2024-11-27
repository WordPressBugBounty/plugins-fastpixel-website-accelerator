<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Action_Model')) {
    abstract class FASTPIXEL_Action_Model {

        public $action_name;
        public $is_post_request = false;
        public $nonce_verified = false;
        private $error = false;
        private $error_message;
        private $message_type = 'warning'; //
        private $do_redirect = false;
        private $redirect_to = null;

        public function __construct($action_name) {
            if ($action_name) {
                $this->action_name = $action_name;
            }
            $this->check_is_post_request();
        }

        protected function check_is_post_request()
        {
            if (sanitize_text_field($_SERVER['REQUEST_METHOD']) !== 'POST') // no post, nothing to check, return silent.
            {
                $this->is_post_request = false;
            }
            if (isset($_POST['fastpixel-nonce']) && wp_verify_nonce(sanitize_key($_POST['fastpixel-nonce']), $this->action_name)) {
                $this->nonce_verified = true;
            }
            $this->is_post_request = true;
        }

        abstract protected function do_action();

        protected function add_error($message, $message_type = 'warning') {
            $this->error = true;
            $this->error_message = $message;
            if ($message_type) {
                $this->message_type = $message_type;
            }
        }

        protected function add_redirect($redirect_to = null) {
            $this->do_redirect = true;
            if ($redirect_to) {
                $this->redirect_to = $redirect_to;
            }
        }

        protected function remove_redirect()
        {
            $this->do_redirect = false;
            $this->redirect_to = null;
        }
        public function get_status()
        {
            return [
                'error' => $this->error, 
                'message' => $this->error_message, 
                'message_type' => $this->message_type,
                'do_redirect' => $this->do_redirect,
                'redirect_to' => $this->redirect_to
            ];
        }
    }
}
