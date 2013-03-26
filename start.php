<?php
/*
Plugin Name: Political Profiler by ElectNext
Plugin URI: http://www.electnext.com/
Description: A plugin for automatically displaying "baseball cards" for politicians mentioned in posts.
Author: ElectNext
Version: 1.0
Author URI: http://www.electnext.com
License: GPLv2 or later
*/

require_once 'ElectNext.php';

add_action('wpmu_new_blog', 'electnext_activate_for_new_network_site');
register_activation_hook(__FILE__, 'electnext_activate');
load_plugin_textdomain('electnext', false, basename(dirname(__FILE__)) . '/languages/');

$electnext = new ElectNext();
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
  deactivate_plugins(__FILE__);
  wp_die($message);
}
