<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Backend_Actions')) {
    class FASTPIXEL_Backend_Actions extends FASTPIXEL_Backend_Controller
    {
        private $action;
        public function __construct()
        {
            parent::__construct();
            //checking if any fastpixel action requested
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- wordpress page is accessed without any nonces, no data is posted.
            $this->action = isset($_REQUEST['fastpixel-action']) ? sanitize_key(wp_unslash($_REQUEST['fastpixel-action'])) : null;
            //setting action name to variable
            if ($this->action) {
                //adding action run at later time
                add_action('admin_init', [$this, 'run_action']);
            }
        }

        public function run_action() 
        {
            //generating class name
            $class_name = 'FASTPIXEL\FASTPIXEL_Action_' . $this->action;
            //checking if class exists
            if (class_exists($class_name)) {
                //creating new class instance
                $action_class = new $class_name($this->action);
                //extra check if class have do_action method
                if (method_exists($action_class, 'do_action')) {
                    //running action
                    $action_class->do_action();
                    //getting action results
                    $status = $action_class->get_status();
                    //displaying error if set
                    if ($status['error']) {
                        $this->notices->add_flash_notice($status['message'], $status['message_type']);
                    }
                    //doing redirect if set
                    if ($status['do_redirect']) {
                        $this->do_redirect($status['redirect_to']);
                    }
                }
            }
        }
    }
    new FASTPIXEL_Backend_Actions();
}
