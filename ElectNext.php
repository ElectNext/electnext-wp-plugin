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

  public function run() {
    add_action('admin_enqueue_scripts', array($this, 'add_admin_scripts'));
    add_action('add_meta_boxes', array($this, 'init_meta_box'));
    add_action('save_post', array($this, 'save_meta_box_data'));
    add_filter('the_content', array($this, 'add_info_boxes'));
    return true;
  }

  public function add_admin_scripts($page) {
    if (!in_array($page, array('post-new.php', 'post.php')) ) {
      return null;
    }

    // jquery-ui-sortable is automatically included in the post editor in WP 3.5
    // but we don't want to assume it always will be in future versions, so enqueue it
    wp_enqueue_script('jquery-ui-sortable');
    wp_enqueue_script('jquery-ui-autocomplete');

    $css_url = plugins_url('electnext-wp-plugin/editor.css');
    wp_register_style('electnext_editor_css', $css_url, false, $this->version);
    wp_enqueue_style('electnext_editor_css');

    $tipsy_url = plugins_url('electnext-wp-plugin/jquery.tipsy.js');
    wp_register_script('tipsy', $tipsy_url, array('jquery'));
    wp_enqueue_script('tipsy');
  }

  public function init_meta_box() {
    $title = __('Politician Profiles', 'electnext');
    $powered_by = __('powered by', 'electnext');

    foreach (array('post', 'page') as $type) {
      add_meta_box(
        'electnext',
        "$title <span class='enxt-small'>$powered_by <span class='enxt-red'>Elect</span><span class='enxt-blue'>Next</span></span>",
        array($this, 'render_meta_box'),
        $type,
        'normal',
        'high'
      );
    }
  }

  public function render_meta_box($post) {
    $meta_pols = get_post_meta($post->ID, 'electnext_pols', true);
    $pols = empty($meta_pols) ? array() : $meta_pols;
    wp_nonce_field('electnext_meta_box_nonce', 'electnext_meta_box_nonce');
    echo "<script async src='{$this->url_prefix}{$this->site_name}{$this->script_url}'></script>";
    ?>

    <script>
      jQuery(document).ready(function($) {
        $('.enxt-icon-info').tipsy({
          className: 'enxt-tipsy',
          namespace: 'enxt-',
          gravity: 'se'
        });

        $('.enxt-icon-move').tipsy({
          className: 'enxt-tipsy',
          namespace: 'enxt-',
          gravity: 's'
        });

        function electnext_add_to_list(pol) {
          $('#enxt-pols ol').append(
            '<li class="enxt-pol" id="enxt-pol-id-' + pol.id + '" data-pol_id="' + pol.id + '">'
            + '<i class="enxt-icon-move" title="Change the order of the profiles by dragging"></i>'
            + '<strong>' + pol.name + '</strong>'
            + (pol.title ? (' - <span>' + pol.title + '</span>') : '')
            + '<a href="#" class="enxt-pol-remove"><i class="enxt-icon-remove" title="Remove this profile"></i></a></li>'
          );
        }

        // this relies on jquery-ui-sortable being loaded
        $('#enxt-pols ol').sortable();

        // scan the post content for politician names, and add ones we find to the list
        $('.enxt-scan-btn').on('click', function(ev) {
          ev.preventDefault();
          $('.enxt-scan em').empty();

          // this works for TinyMCE and the HTML editor
          var content = $('#content').val().replace(/(<([^>]+)>)/ig,"");
          var possibles = ElectNext.scan_string(content);

          ElectNext.search_candidates(possibles, function(data) {
            if (data.length == 0) {
              $('.enxt-scan em').text('No politician names found');
            }

            else {
              var found_new = 0;

              $.each(data, function(idx, el) {
                if (!$('#enxt-pol-id-' + el.id).length) {
                  electnext_add_to_list(el);
                  found_new += 1;
                }
              })

              if (found_new == 0) {
                $('.enxt-scan em').text('No new politician names found');
              }

              else {
                $('.enxt-scan em').text('Found ' + found_new + ' politician name' + (found_new > 1 ? 's' : ''));
              }
            }
          });
        });

        // remove names on demand
        $('.enxt-pol-remove').on('click', function(ev) {
          ev.preventDefault();
          $(this).parents('.enxt-pol').remove();
        });

        // search for pols by name
        $('#enxt-search-name').autocomplete({
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

            if (!$('#enxt-pol-id-' + pol.id).length) {
               electnext_add_to_list(pol);
            }

            $("#enxt-search-name").val('');
            ev.preventDefault(); // this prevents the selected value from going back into the input field
          }
        });

        // save the final set of names when the post is saved
        $('#post').submit(function() {
          for (var i = 0; i < $('.enxt-pol').length; i++) {
            $('#post').append(
              '<input type="hidden"'
                + ' name="electnext_pols_meta[' + i + '][id]"'
                + ' value="' + $('.enxt-pol:eq(' + i + ')').attr('data-pol_id') + '">'
              + '<input type="hidden"'
                + ' name="electnext_pols_meta[' + i + '][name]"'
                + ' value="' + $('.enxt-pol:eq(' + i + ') strong').text() + '">'
              + '<input type="hidden"'
                + ' name="electnext_pols_meta[' + i + '][title]"'
                + ' value="' + $('.enxt-pol:eq(' + i + ') span').text() + '">'
            );
          }
        });
      });

    </script>
    <div class="enxt-group">
      <div class="enxt-header enxt-scan-header">
        <span>Profiles to display in this article <i class="enxt-icon-info" title="Use the 'Scan post' button to search your content for politicians. After scanning, a list of politician profiles to be displayed with your article will appear below."></i></span>
        <div class="enxt-scan"><a href="#" class="enxt-scan-btn button">Scan Article</a> <em></em></div>
      </div>
      <div class="enxt-header enxt-search-header">
        <span><label for="enxt-search-name">Add a politician by name</label> <i class="enxt-icon-info" title="Type a politician's name in the box below to manually add a profile."></i></span>
        <div><input type="text" placeholder="Type a politician's name" name="enxt-search-name" id="enxt-search-name"></div>
      </div>
    </div>
    <div id="enxt-pols">
      <ol>
        <?php
        if (!empty($pols)) {
          for ($i=0; $i < count($pols); ++$i)  {
            echo "<li class='enxt-pol' id='enxt-pol-id-{$pols[$i]['id']}' data-pol_id='{$pols[$i]['id']}'>"
              . "<i class='enxt-icon-move' title='Change the order of the profiles by dragging'></i>"
              . "<strong>{$pols[$i]['name']}</strong>"
              . (strlen($pols[$i]['title']) ? " - <span>{$pols[$i]['title']}</span>" : "")
              . "<a href='#' class='enxt-pol-remove'><i class='enxt-icon-remove' title='Remove this profile'></i></a></li>";
          }
        }
        ?>
      </ol>
    </div>

    <?php
  }

  public function save_meta_box_data($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset( $_POST['electnext_meta_box_nonce']) || !wp_verify_nonce($_POST['electnext_meta_box_nonce'], 'electnext_meta_box_nonce')) return;
    if (!current_user_can('edit_post')) return;

    $pols = $this->utils->array_map_recursive('sanitize_text_field', $_POST['electnext_pols_meta']);
    update_post_meta($post_id, 'electnext_pols', $pols);
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
}
