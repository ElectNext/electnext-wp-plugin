<?php
/*
Plugin Name: ElectNext
Plugin URI: http://www.electnext.com/
Description: A plugin for automatically displaying "info boxes" at the end of posts about politicians, based on names mentioned in the posts.
Author: ElectNext
Version: 0.1
Author URI: http://www.electnext.com
License: GPLv2 or later
*/

require_once 'ElectNextUtils.php';
require_once 'ElectNext.php';

add_action('wpmu_new_blog', 'electnext_activate_for_new_network_site');
register_activation_hook(__FILE__, 'electnext_activate');
// uncomment if needed
//register_deactivation_hook(__FILE__, 'electnext_deactivate_for_network_sites');
load_plugin_textdomain('electnext', false, basename(dirname(__FILE__)) . '/languages/');

$electnext_utils = new ElectNextUtils();
$electnext = new ElectNext($electnext_utils);
$electnext->run();

function electnext_activate_for_new_network_site($blog_id) {
  global $wpdb;

  if (is_plugin_active_for_network(__FILE__)) {
    $old_blog = $wpdb->blogid;
    switch_to_blog($blog_id);
    electnext_activate();
    switch_to_blog($old_blog);
  }
}

function electnext_activate() {
  $status = electnext_activation_checks();

  if (is_string($status)) {
    electnext_cancel_activation($status);
  }

  return null;
}

function electnext_activation_checks() {
  if (version_compare(get_bloginfo('version'), '3.0', '<')) {
    return __('ElectNext plugin not activated. You must have at least WordPress 3.0 to use it.', 'electnext');
  }

  return true;
}

function electnext_cancel_activation($message) {
  deactivate_plugins(dirname(__FILE__) . '/' . __FILE__);
  wp_die($message);
}

// uncomment if there's any deactivation steps needed
//function electnext_deactivate_for_network_sites() {
//  $utils = new ElectNextUtils();
//  $utils->call_function_for_network_sites('electnext_deactivate');
//}
//
//function electnext_deactivate() {
//
//}
