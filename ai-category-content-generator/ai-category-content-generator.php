<?php
/**
 * Plugin Name:       AI Category Content & Image Generator
 * Plugin URI:        https://bizsitenow.com/
 * Description:       Generates Post content and featured image based on custom prompts
 * Version:           1.1
 * Author:            Joseph Triplett
 * Author URI:        https://bizsitenow.com/
 * Text Domain:       ai-cat-content-gen-google
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) exit;

// Includes
require_once plugin_dir_path(__FILE__) . 'includes/constants.php';
require_once plugin_dir_path(__FILE__) . 'includes/textdomain.php';
require_once plugin_dir_path(__FILE__) . 'includes/all-functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/cron-handler.php';
