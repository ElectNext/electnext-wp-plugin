<?php

class ElectNext {
  private $version = '0.1';
  private $site_url = 'https://electnext.dev';
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
    echo "<script async src='{$this->site_url}/api/v1/info_widget.js'></script>\n";
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

        // search by name and add the results to the list
        $('#electnext-find-btn').on('click', function(ev) {
          ev.preventDefault();

          if ($('#electnext-search-name').val().length == 0) {
            alert('Please enter the name of a politician.');
          }

          else {
            var electnext_search_url = "<?php echo $this->site_url; ?>/api/v1/name_search.js?callback=?";
            var dataToSend = { q: $('#electnext-search-name').val() };

            $.getJSON(electnext_search_url, dataToSend, function(dataReceived) {
              if (dataReceived.length == 0) {
                $('#electnext-search em').text('No results found');
              }

              else {
                $('#electnext-search em').empty();
                $.each(dataReceived, function(idx, el) {
                  if (!$('#electnext-pol-id-' + el.id).length) {
                    electnext_add_to_list(el);
                  }
                });
              }
            });
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
    <div style="float: right; margin-left: 10px;">
      <p id="electnext-search">
        <label for="electnext-search-name">Add a politician by name:</label>
        <br><input type="text" name="electnext-search-name" id="electnext-search-name">
        <br><a class="button" href="#" id="electnext-find-btn">Find</a> <em></em>
      </p>
    </div>

    <div id="electnext-pols">
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
    }
  }

  public function add_front_end_scripts() {
    wp_enqueue_script('jquery');
  }

  public function add_info_boxes($content) {
    global $post;
    // the is_main_query() check ensures we don't add to sidebars, footers, etc
    // we could also add an is_single() check if we only want them on single post pages
    if (is_main_query()) {
      $pols = get_post_meta($post->ID, 'electnext_pols', true);
      $pols_string = '';

      if (is_array($pols)) {
        foreach ($pols as $pol) {
          $pols_string .= "ids[]={$pol['id']}&";
        }

        $pols_string = substr($pols_string, 0, -1);
      }

      if (strlen($pols_string)) {
        $new_content = "
          <script async src='{$this->site_url}/api/v1/info_widget.js'></script>
          <div id='electnext-widgets'><a href='#' id='electnext-test'>Click me</a></div>
          <script>
            jQuery(document).ready(function($) {
              $('#electnext-test').on('click', function(ev) {
                ev.preventDefault();

                ElectNext.fetch_candidates('$pols_string', function(data) {
                  if (data.length == 0) {
                    $('#electnext-widgets').text('Error');
                  }

                  else {
                    $('#electnext-widgets').html(data.content);
                  }
                });
              });
            });
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
