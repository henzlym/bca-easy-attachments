<?php

/**
 * Plugin Name: Blk Canvas - Easy Attachments
 * Plugin URI: https://github.com/henzlym/blkcanvas-easy-attachments/
 * Description: Effortlessly enhance your content with stunning, high-quality photos at no cost. Download and integrate beautiful images from Unsplash directly into your WordPress media library.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Henzly Meghie
 * Author URI: https://henzlymeghie.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: easy-attachments
 * Domain Path: /languages
 *
 * @package EasyAttachments
 */

namespace EasyAttachments;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

// Define plugin constants.
define('EASY_ATTACHMENTS_VERSION', '1.0.0');
define('EASY_ATTACHMENTS_PATH', plugin_dir_path(__FILE__));
define('EASY_ATTACHMENTS_URI', plugin_dir_url(__FILE__));
define('EASY_ATTACHMENTS_BASENAME', plugin_basename(__FILE__));

/**
 * Load plugin functionality.
 */
require_once EASY_ATTACHMENTS_PATH . 'src/init.php';
