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


    <script src="https://electnext.dev/api/v1/info_widget.js"></script>

    <script>
      jQuery(document).ready(function($) {
        $('#electnext_add_pol').click(function(ev) {
          $('<p><label for="electnext_pol[][id]">ID:</label> <input type="text" name="electnext_pol[][id]"> <label for="electnext_pol[][name]">Name:</label> <input type="text" name="electnext_pol[][name]"></p>').appendTo(electnext_pols);
          ev.preventDefault();
        });

        $('#electnext_scan_btn').on('click', function(ev) {
          ev.preventDefault();
          var content = tinyMCE.get('content').getContent().replace(/(<([^>]+)>)/ig,"");
          var possibles = ElectNext.scan_string(content);
          ElectNext.search_candidates(possibles)
        });
      });

    </script>
    <div id="electnext_pols">
      <?php
      if (!empty($pols)) {
        echo '<ul>';
        for ($i=0; $i < count($pols); ++$i)  {
          echo "<li>ID: <input type='text' name='electnext_pol[$i][id]' value='{$pols[$i]['id']}'> ";
          echo "Name: <input type='text' name='electnext_pol[$i][name]' value='{$pols[$i]['name']}'></li>";
        }
        echo '</ul>';
      }
      ?>

      <p><a href="#" id="electnext_add_pol">Add another</a></p>
      <p><a class="button" href="#" id="electnext_scan_btn">Scan post</a></p>
      <div id="electnext-results"></div>
    </div>
    <?php
  }

  public function save_meta_box_data($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset( $_POST['electnext_meta_box_nonce']) || !wp_verify_nonce($_POST['electnext_meta_box_nonce'], 'electnext_meta_box_nonce')) return;
    if (!current_user_can('edit_post')) return;

    // working here
    $pols = array_map('esc_attr', $_POST['electnext_pol']);
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
