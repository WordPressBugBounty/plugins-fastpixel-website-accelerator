<?php
namespace FASTPIXEL;

defined('ABSPATH') || exit; 
// show error/update messages
settings_errors('fastpixel_messages');
settings_fields(FASTPIXEL_TEXTDOMAIN);
do_settings_sections(FASTPIXEL_TEXTDOMAIN);
$this->be_functions->print_save_button();
