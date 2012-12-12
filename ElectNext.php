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
    // put all your initializing hooks and filters here, for example...
    //add_action('admin_menu', array($this, 'init_settings_menu'));
    //add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    //add_shortcode('shashin', array($this, 'handle_shortcode'));
    add_action('add_meta_boxes', array($this, 'init_meta_box'));
    add_action('save_post', array($this, 'save_meta_box_data'));
    return true;
  }

  public function init_meta_box() {
    add_meta_box('electnext', 'ElectNext', array($this, 'render_meta_box'), 'post', 'normal', 'high');
  }

  public function render_meta_box() {
    global $post;
    $meta_pols = get_post_meta($post->ID, 'electnext_pols', true);
    $pols = isset($meta_pols) ? unserialize($meta_pols) : array();
    $pol_name = !empty($pols) ? $pols[0]['name'] : '';
    $pol_id = !empty($pols) ? $pols[0]['id'] : '';
    wp_nonce_field('electnext_meta_box_nonce', 'electnext_meta_box_nonce');
    ?>
      <p>Temporary inputs to test saving</p>
      <p>
        <label for="electnext_pol_name">Politician Name</label>
        <input type="text" name="electnext_pol_name" id="electnext_pol_name" value="<?php echo $pol_name; ?>" />
      </p>
      <p>
        <label for="electnext_pol_id">Politician Name</label>
        <input type="text" name="electnext_pol_id" id="electnext_pol_id" value="<?php echo $pol_id; ?>" />
      </p>
    <?php
  }

  public function save_meta_box_data($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset( $_POST['electnext_meta_box_nonce']) || !wp_verify_nonce($_POST['electnext_meta_box_nonce'], 'electnext_meta_box_nonce')) return;
    if (!current_user_can('edit_post')) return;

    $meta_pols = get_post_meta($post_id, 'electnext_pols', true);
    $pols = isset($meta_pols) ? unserialize($meta_pols) : array();

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

    update_post_meta($post_id, 'electnext_pols', serialize($pols));
  }

  public function render_exception_message($e) {
    return '<p><strong>'
      . __('ElectNext plugin error', 'electnext')
      . ':</strong></p><pre>'
      . $e->getMessage()
      . '</pre>';
  }
}
