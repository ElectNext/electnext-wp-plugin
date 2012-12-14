<?php

class ElectNextUtils {
  public function __construct() {
  }

  public function get_site_url($path_to_append_to_url = null, $scheme = null) {
    if (!$scheme && isset($_SERVER['HTTPS'])) {
      $scheme = 'https';
    }

    return site_url($path_to_append_to_url, $scheme);
  }

  public function get_admin_url($path_to_append_to_url = null, $scheme = null) {
    if (!$scheme && is_ssl()) {
      $scheme = 'https';
    }

    elseif (!$scheme) {
      $scheme = 'admin';
    }

    return admin_url($path_to_append_to_url, $scheme);
  }

  public function get_url_for_customizable_file($file_name, $base_file, $relative_path = null) {
    if (file_exists(get_stylesheet_directory() . '/' . $file_name)) {
      $url = get_bloginfo('stylesheet_directory') . '/' . $file_name;
    }

    else {
      $url = plugins_urls($relative_path . $file_name, $base_file);
    }

    return $url;
  }

  public function get_http_request_object() {
    require_once(ABSPATH . WPINC . '/class-http.php');
    return new WP_Http();
  }

  public function call_function_for_network_sites($callback, $check_networkwide = true) {
    global $wpdb;

    if (function_exists('is_multisite') && is_multisite()) {
      // for a plugin uninstall, $_GET['networkwide'] is not set
      if (!$check_networkwide || ($check_networkwide && isset($_GET['networkwide']) && $_GET['networkwide'] == 1)) {
        $blog_ids = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM $wpdb->blogs"));

        foreach ($blog_ids as $blog_id) {
          switch_to_blog($blog_id);
          call_user_func($callback);
        }

        restore_current_blog();
        return true;
      }
    }

    call_user_func($callback);
    return true;
  }

  // from http://php.net/manual/en/function.array-map.php#107808
  public function array_map_recursive($fn, $arr) {
    $rarr = array();
    foreach ($arr as $k => $v) {
      $rarr[$k] = is_array($v)
        ? $this->array_map_recursive($fn, $v)
        : $fn($v);
    }
    return $rarr;
  }
}
