<?php
defined('ABSPATH') || die;

/*
Plugin Name: WPU Attachments Enhanced
Description: Add enhancements to attachments in WordPress
Version: 0.1.1
Plugin URI: https://github.com/WordPressUtilities/wpu_attachments_enhanced/
Update URI: https://github.com/WordPressUtilities/wpu_attachments_enhanced/
Author: Darklg
Author URI: https://darklg.me/
Text Domain: wpu_attachments_enhanced
Domain Path: /lang
Requires at least: 6.2
Requires PHP: 8.0
Network: Optional
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

require_once __DIR__ . '/inc/disable-attachments-deletion.php';

add_action('init', function () {
    $lang_dir = dirname(plugin_basename(__FILE__)) . '/lang/';
    if (strpos(__DIR__, 'mu-plugins') !== false) {
        load_muplugin_textdomain('wpu_attachments_enhanced', $lang_dir);
    } else {
        load_plugin_textdomain('wpu_attachments_enhanced', false, $lang_dir);
    }
});
