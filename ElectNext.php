<?php

class ElectNext {
  private $version = '0.1';
  private $script_url = '/api/v1/info_widget.js';
  private $utils;
  private $api_key = 'abc123';

  // dev
  // private $url_prefix = 'https://';
  // private $site_name = 'electnext.dev';

  // staging
  // private $url_prefix = 'http://';
  // private $site_name = 'staging.electnext.com';

  // production: check in with these uncommented!
  private $url_prefix = 'https://';
  private $site_name = 'electnext.com';


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
    add_action('admin_enqueue_scripts', array($this, 'add_admin_scripts'));
    add_action('wp_enqueue_scripts', array($this, 'add_front_end_scripts'));
    add_filter('the_content', array($this, 'add_info_boxes'));
    return true;
  }

  public function init_meta_box() {
    add_meta_box('electnext', 'ElectNext', array($this, 'render_meta_box'), 'post', 'normal', 'high');
  }

  public function render_meta_box($post) {
    $meta_pols = get_post_meta($post->ID, 'electnext_pols', true);
    $pols = empty($meta_pols) ? array() : $meta_pols;
    wp_nonce_field('electnext_meta_box_nonce', 'electnext_meta_box_nonce');
    echo "<script async src='{$this->url_prefix}{$this->site_name}{$this->script_url}'></script>\n";
    ?>


    <script>
      jQuery(document).ready(function($) {
        function electnext_add_to_list(pol) {
          $('#electnext-pols ul').append(
            '<li class="electnext-pol" id="electnext-pol-id-' + pol.id + '">'
            + '<strong>' + pol.name + '</strong>'
            + (pol.title ? (' - <span>' + pol.title + '</span>') : '')
            + '<i style="display:none;">' + pol.id + '</i>'
            + ' [ <a href="#" class="electnext-pol-remove">x</a> ]</li>'
          );
        }
        // this relies on jquery-ui-sortable being loaded
        $('#electnext-pols ul').sortable();

        // scan the post content for politician names, and add ones we find to the list
        $('#electnext-scan-btn').on('click', function(ev) {
          ev.preventDefault();
          var content = tinyMCE.get('content').getContent().replace(/(<([^>]+)>)/ig,"");
          var possibles = ElectNext.scan_string(content);
          ElectNext.search_candidates(possibles, function(data) {
            if (data.length == 0) {
              $('#electnext-scan em').text('No names found');
            }

            else {
              $('#electnext-scan em').empty();
              $.each(data, function(idx, el) {
                if (!$('#electnext-pol-id-' + el.id).length) {
                  electnext_add_to_list(el);
                }
              })
            }
          });
        });

        // remove names on demand
        $('#electnext-pols ul').on('click', '.electnext-pol-remove', function(ev) {
          ev.preventDefault();
          $(this).parent().remove();
        });

        // search for pols by name
        $('#electnext-search-name').autocomplete({
          delay: 500, // recommended for remote data calls

          source: function(req, add) {
            $.getJSON('<?php echo $this->url_prefix . $this->site_name; ?>/api/v1/name_search.js?callback=?', { q: req.term }, function(data) {
              var suggestions = [];
              $.each(data, function(i, val) {
                // "suggestions" wants item and label values
                val.item = val.id;
                val.label = val.name + (val.title == null ? '' : (' - ' + val.title));
                suggestions.push(val);
              });
              add(suggestions);
            });
          },

          select: function(ev, ui) {
            pol = ui.item;

            if (!$('#electnext-pol-id-' + pol.id).length) {
               electnext_add_to_list(pol);
            }

            $("#electnext-search-name").val('');
            ev.preventDefault(); // this prevents the selected value from going back into the input field
          }
        });

        // save the final set of names when the post is saved
        $('#post').submit(function() {
          for (var i = 0; i < $('.electnext-pol').length; i++) {
            $('#post').append(
              '<input type="hidden"'
                + ' name="electnext_pols_meta[' + i + '][id]"'
                + ' value="' + $('.electnext-pol:eq(' + i + ') i').text() + '">'
              + '<input type="hidden"'
                + ' name="electnext_pols_meta[' + i + '][name]"'
                + ' value="' + $('.electnext-pol:eq(' + i + ') strong').text() + '">'
              + '<input type="hidden"'
                + ' name="electnext_pols_meta[' + i + '][title]"'
                + ' value="' + $('.electnext-pol:eq(' + i + ') span').text() + '">'
            );
          }
        });
      });

    </script>
    <style>li.electnext-pol { cursor: ns-resize; }</style>
    <div id="electnext-pols" style="float: left; margin-right: 10%;">
      <ul>
      <?php
        if (!empty($pols)) {
          for ($i=0; $i < count($pols); ++$i)  {
            echo "<li class='electnext-pol' id='electnext-pol-id-{$pols[$i]['id']}'>"
              . "<strong>{$pols[$i]['name']}</strong>"
              . (strlen($pols[$i]['title']) ? " - <span>{$pols[$i]['title']}</span>" : "")
              . "<i style='display:none;'>{$pols[$i]['id']}</i>"
              . " [ <a href='#' class='electnext-pol-remove'>x</a> ]</li>\n";
          }
        }
      ?>
      </ul>

      <p id="electnext-scan"><a class="button" href="#" id="electnext-scan-btn">Scan post</a> <em></em></p>
    </div>

    <div style="float: left;">
      <p id="electnext-search">
        <label for="electnext-search-name">Add a politician by name:</label>
        <br><input type="text" name="electnext-search-name" id="electnext-search-name">
      </p>
    </div>

    <div class="clear"></div>

    <?php
  }

  public function save_meta_box_data($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset( $_POST['electnext_meta_box_nonce']) || !wp_verify_nonce($_POST['electnext_meta_box_nonce'], 'electnext_meta_box_nonce')) return;
    if (!current_user_can('edit_post')) return;

    $pols = $this->utils->array_map_recursive('sanitize_text_field', $_POST['electnext_pols_meta']);
    update_post_meta($post_id, 'electnext_pols', $pols);
  }

  public function add_admin_scripts($hook) {
    // jquery-ui-sortable is automatically included in the post editor in WP 3.5
    // but we don't want to assume it always will be in future versions, so enqueue it
    if ($hook == 'post-new.php' || $hook == 'post.php') {
      wp_enqueue_script('jquery-ui-sortable');
      wp_enqueue_script('jquery-ui-autocomplete');
    }
  }

  public function add_front_end_scripts() {
    wp_enqueue_script('jquery');
  }

  public function add_info_boxes($content) {
    global $post;
    // the is_main_query() check ensures we don't add to sidebars, footers, etc
    if (is_main_query() && is_single()) {
      $pols = get_post_meta($post->ID, 'electnext_pols', true);

      if (is_array($pols)) {
        $pol_ids = array();
        foreach ($pols as $pol) {
          $pol_ids[] = $pol['id'];
        }
        $pols_js = json_encode($pol_ids);
        $new_content = "
          <script data-electnext id='enxt-script' type='text/javascript'>
            //<![CDATA[
              var _enxt = _enxt || [];
              _enxt.push(['set_account', '{$this->api_key}']);
              _enxt.push(['wp_setup_profiles', $pols_js]);
              (function() {
                var enxt = document.createElement('script'); enxt.type = 'text/javascript'; enxt.async = true;
                enxt.src = '//{$this->site_name}{$this->script_url}';
                var k = document.getElementById('enxt-script');
                k.parentNode.insertBefore(enxt, k);
              })();
            //]]>
          </script>
        ";

        $content .= $new_content;
      }
    }
    return $content;
  }

  public function render_exception_message($e) {
    return '<p><strong>'
      . __('ElectNext plugin error', 'electnext')
      . ':</strong></p><pre>'
      . $e->getMessage()
      . '</pre>';
  }
}
