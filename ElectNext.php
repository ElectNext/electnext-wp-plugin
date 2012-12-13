<?php

class ElectNext {
  private $version = '0.1';
  private $utils;

  public function __construct($utils) {
    $this->utils = $utils;
  }

  public function getVersion() {
    return $this->version;
  }

  public function install() {
    try {
      // installation code here
      //return $status;
    }

    catch (Exception $e) {
      return $this->render_exception_message($e);
    }
  }

  public function run() {
    add_action('add_meta_boxes', array($this, 'init_meta_box'));
    add_action('save_post', array($this, 'save_meta_box_data'));
    return true;
  }

  public function init_meta_box() {
    add_meta_box('electnext', 'ElectNext', array($this, 'render_meta_box'), 'post', 'normal', 'high');
  }

  public function render_meta_box($post) {
    $meta_pols = get_post_meta($post->ID, 'electnext_pols', true);
    $pols = empty($meta_pols) ? array() : $meta_pols;
    wp_nonce_field('electnext_meta_box_nonce', 'electnext_meta_box_nonce');
    ?>
      <div style="float: left;">
      <p>Temporary inputs to test saving</p>
      <p>
        <label for="electnext_pol_name">Politician Name</label>
        <input type="text" name="electnext_pol_name" id="electnext_pol_name" />
      </p>
      <p>
        <label for="electnext_pol_id">Politician ID</label>
        <input type="text" name="electnext_pol_id" id="electnext_pol_id" />
      </p>
      </div>
      <div style="float: left; margin-left: 30px;">
        <?php
          if (!empty($pols)) {
            echo '<ul>';
            foreach ($pols as $pol) {
              echo "<li>ID: {$pol['id']} Name: {$pol['name']}</li>";
            }
            echo '</ul>';
          }
        ?>
      </div>
      <div class="clear"></div>
    <?php
  }

  public function save_meta_box_data($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset( $_POST['electnext_meta_box_nonce']) || !wp_verify_nonce($_POST['electnext_meta_box_nonce'], 'electnext_meta_box_nonce')) return;
    if (!current_user_can('edit_post')) return;

    if ($parent_id = wp_is_post_revision($post_id)) {
      $post_id = $parent_id;
    }

    $meta_pols = get_post_meta($post_id, 'electnext_pols', true);
    $pols = empty($meta_pols) ? array() : $meta_pols;
    $existing = false;

    if (isset($_POST['electnext_pol_name']) && isset($_POST['electnext_pol_id'])) {
      for ($i=0; $i < count($pols); ++$i) {
        if ($pols[$i]['id'] == $_POST['electnext_pol_id']) {
          $pols[$i]['name'] = esc_attr($_POST['electnext_pol_name']);
          $existing = true;
          break;
        }
      }
    }

    if ($existing === false) {
      $pols[] = array('name' => esc_attr($_POST['electnext_pol_name']), 'id' => esc_attr($_POST['electnext_pol_id']));
    }

    update_post_meta($post_id, 'electnext_pols', $pols);
  }

  public function render_exception_message($e) {
    return '<p><strong>'
      . __('ElectNext plugin error', 'electnext')
      . ':</strong></p><pre>'
      . $e->getMessage()
      . '</pre>';
  }
}
