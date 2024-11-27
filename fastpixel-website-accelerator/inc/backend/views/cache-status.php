<?php 
namespace FASTPIXEL;

defined('ABSPATH') || exit;
// Prepare table
$table = $this->get_table();
$table->prepare_items();
// Display table
$table->display();
