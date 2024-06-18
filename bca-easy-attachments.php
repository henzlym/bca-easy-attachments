<?php

/**
 * Plugin Name: Blk Canvas - Easy Attachments
 * Plugin URI: https://github.com/henzlym/blkcanvas-easy-attachments/
 * Description: Effortlessly enhance your content with stunning, high-quality photos at no cost. Blk Canvas - Easy Attachments allows you to seamlessly download and integrate beautiful images into your posts and pages, without the hassle of licensing fees or complicated processes.
 * Author: Henzly Meghie
 * Author URI: https://henzlymeghie.com/
 * Version: 1.0.0
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 *
 * @package CGB
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}
define('EASY_ATTACHMENTS_PATH', plugin_dir_path( __FILE__ ) );
define('EASY_ATTACHMENTS_URI', plugin_dir_url( __FILE__ ) );
/**
 * Block Initializer.
 */
require_once plugin_dir_path(__FILE__) . 'src/init.php';
