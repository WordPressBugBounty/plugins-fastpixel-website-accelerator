<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit;

if (!class_exists('FASTPIXEL\FASTPIXEL_Module_Elementor')) {
    class FASTPIXEL_Module_Elementor extends FASTPIXEL_Module 
    {

        public function __construct() {
            parent::__construct();
        }

        public function init() {
            add_action('fastpixel/loggeduser/adminbar/beforerender', function ($post_id = null) {
                if (empty($post_id)) {
                    return false;
                }
                if (class_exists('Elementor\Frontend') && method_exists('Elementor\Frontend', 'instance')) {
                    $frontend = \Elementor\Frontend::instance();
                    if (method_exists($frontend, 'register_scripts')) {
                        $frontend->register_scripts();
                    }
                    if (class_exists('Elementor\Plugin') && method_exists('Elementor\Plugin', 'instance')) {
                        $plugin = \Elementor\Plugin::instance();
                        if (isset($plugin->documents) && !empty($plugin->documents) && method_exists($plugin->documents, 'get_doc_for_frontend')) {
                            $document = $plugin->documents->get_doc_for_frontend($post_id);
                            if (!$document || (!method_exists($document, 'is_built_with_elementor')) || !$document->is_built_with_elementor()) {
                                return false;
                            }
                        }
                    }
                    if (class_exists('Elementor\Modules\AdminBar\Module') && method_exists('Elementor\Modules\AdminBar\Module', 'instance')) {
                        $elementor_admin_bar = \Elementor\Modules\AdminBar\Module::instance();
                        if (method_exists($elementor_admin_bar, 'add_document_to_admin_bar')) {
                            $elementor_admin_bar->add_document_to_admin_bar($document, false);
                        }
                        if (method_exists($elementor_admin_bar, 'enqueue_scripts')) {
                            $elementor_admin_bar->enqueue_scripts();
                        }
                    }
                }
            });
        }
    }
    new FASTPIXEL_Module_Elementor();
}
