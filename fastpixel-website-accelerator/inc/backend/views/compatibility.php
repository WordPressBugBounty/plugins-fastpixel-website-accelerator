<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit; 
// show error/update messages
settings_errors('fastpixel_messages');
settings_fields(FASTPIXEL_TEXTDOMAIN);
do_settings_sections(FASTPIXEL_TEXTDOMAIN . '-compatibility');
submit_button(esc_html__('Save Settings', 'fastpixel-website-accelerator'), 'primary', 'settings-submit');
