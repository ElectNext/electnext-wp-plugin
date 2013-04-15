<?php

class ElectNext {
  private $version = '1.0';
  private $script_url = '/api/v1/info_widget.js';
  private $url_prefix = 'https://';
  private $site_name = 'electnext.com';
  private $editor_pages = array('post-new.php', 'page-new.php', 'post.php', 'page.php');
  private $api_key;
  private $short_title;
  private $title;

  public function __construct() {
    $settings = get_option('electnext_settings');
    $this->api_key = $settings['api_key'];
    $this->short_title = __('Political Profiler', 'electnext');
    $this->title = __('Political Profiler by ElectNext', 'electnext');
  }

  public function getVersion() {
    return $this->version;
  }

  public function run() {
    add_action('admin_head', array($this, 'display_missing_api_key_warning'));
    add_action('admin_init', array($this, 'init_settings'));
    add_action('admin_menu', array($this, 'add_settings_page'));
    add_action('admin_enqueue_scripts', array($this, 'add_admin_scripts'));
    add_action('add_meta_boxes', array($this, 'init_meta_box'));
    add_action('save_post', array($this, 'save_meta_box_data'));
    add_filter('the_content', array($this, 'add_politician_profiles'));
  }

  public function display_missing_api_key_warning() {
    $pages_for_message = $this->editor_pages;
    $pages_for_message[] = 'plugins.php';

    if (in_array(basename($_SERVER['SCRIPT_NAME']), $pages_for_message) && !$this->api_key) {
      echo '<div class="error"><p>';
      _e('Please go to the', 'electnext');
      $link_text = $this->short_title . ' ' . __('settings page', 'electnext');
      echo " <a href='options-general.php?page=electnext'>$link_text</a> ";
      _e('to enter you API key.', 'electnext');
      echo '</p></div>';
    }
  }

  public function init_settings() {
    register_setting('electnext_settings', 'electnext_settings');
    add_settings_section('electnext_main', null, array($this, 'display_electnext_main'), 'electnext');
    add_settings_field(
      'electnext_api_key',
      __('ElectNext API Key', 'electnext'),
      array($this, 'display_api_input'),
      'electnext',
      'electnext_main'
    );
  }

  public function display_electnext_main() {
    echo null; // no need to bother displaying a header since there's just one section
  }

  public function display_api_input() {
    echo "<input id='electnext_api_key' name='electnext_settings[api_key]' type='text' value='{$this->api_key}' size='40'>";
  }

  public function add_settings_page() {
    add_options_page(
      $this->short_title,
      $this->short_title,
      'manage_options',
      'electnext',
      array($this, 'display_settings_page')
    );
  }

  public function display_settings_page() {
    ?>
    <div class="wrap">
      <?php screen_icon(); ?>
      <h2><?php echo $this->title; ?></h2>

      <form action="options.php" method="post">
        <?php settings_fields('electnext_settings'); ?>
        <?php do_settings_sections('electnext'); ?>
        <p class="submit">
          <input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes', 'electnext'); ?>" class="button button-primary" />
        </p>

        <p>[More information here about how to get an API key]</p>
      </form>
    </div>
    <?php
  }

  public function add_admin_scripts($page) {
    if (!in_array($page, $this->editor_pages) ) {
      return null;
    }

    // jquery-ui-sortable is automatically included in the post editor in WP 3.5
    // but we don't want to assume it always will be in future versions, so enqueue it
    wp_enqueue_script('jquery-ui-sortable');
    wp_enqueue_script('jquery-ui-autocomplete');

    $css_url = plugins_url(basename(dirname(__FILE__)) .'/editor.css');
    wp_register_style('enxt-editor', $css_url, false, $this->version);
    wp_enqueue_style('enxt-editor');

    $tipsy_url = plugins_url(basename(dirname(__FILE__)) . '/jquery.tipsy.js');
    wp_register_script('enxt-tipsy', $tipsy_url, array('jquery'), $this->version);
    wp_enqueue_script('enxt-tipsy');
  }

  public function init_meta_box() {
    $powered_by = __('powered by', 'electnext');
    $elect = __('Elect', 'electnext');
    $next = __('Next', 'electnext');

    foreach (array('post', 'page') as $type) {
      add_meta_box(
        'electnext',
        "{$this->short_title} <span class='enxt-small'>$powered_by <span class='enxt-red'>$elect</span><span class='enxt-blue'>$next</span></span>",
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
      var _enxt = _enxt || [];
      _enxt.push(['set_account', '<?php echo $this->api_key; ?>']);

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
            + '<i class="enxt-icon-move" title="<?php esc_attr_e('Change the order of the profiles by dragging', 'electnext'); ?>"></i>'
            + '<strong>' + pol.name + '</strong>'
            + (pol.title ? (' - <span>' + pol.title + '</span>') : '')
            + '<a href="#" class="enxt-pol-remove"><i class="enxt-icon-remove" title="<?php esc_attr_e('Remove this profile', 'electnext'); ?>"></i></a></li>'
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
              $('.enxt-scan em').text('<?php esc_attr_e('No politician names found', 'electnext') ?>');
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
                $('.enxt-scan em').text('<?php esc_attr_e('No new politician names found', 'electnext') ?>');
              }

              else {
                $('.enxt-scan em').text(
                  '<?php esc_attr_e('Found', 'electnext') ?> '
                  + found_new
                  + ' '
                  + (found_new > 1
                    ? '<?php esc_attr_e('politician names', 'electnext') ?>'
                    : '<?php esc_attr_e('politician name', 'electnext') ?>')
                );
              }
            }
          });
        });

        // remove names on demand (attach to #enxt-pols so this works
        // for any politicians added after the initial page load)
        $('#enxt-pols').on('click', '.enxt-pol-remove', function(ev) {
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
        <span><?php _e('Profiles to display in this article', 'electnext') ?> <i class="enxt-icon-info" title="<?php esc_attr_e('Use the "Scan post" button to search your content for politicians. After scanning, a list of politician profiles to be displayed with your article will appear below.', 'electnext') ?>"></i></span>
        <div class="enxt-scan"><a href="#" class="enxt-scan-btn button">Scan Article</a> <em></em></div>
      </div>
      <div class="enxt-header enxt-search-header">
        <span><label for="enxt-search-name"><?php _e('Add a politician by name', 'electnext') ?></label> <i class="enxt-icon-info" title="<?php esc_attr_e('Type a politician\'s name in the box below to manually add a profile.', 'electnext') ?>"></i></span>
        <div><input type="text" placeholder="<?php esc_attr_e('Type a politician\'s name', 'electnext') ?>" name="enxt-search-name" id="enxt-search-name"></div>
      </div>
    </div>
    <div id="enxt-pols">
      <ol>
        <?php
        if (!empty($pols)) {
          for ($i=0; $i < count($pols); ++$i)  {
            echo "<li class='enxt-pol' id='enxt-pol-id-{$pols[$i]['id']}' data-pol_id='{$pols[$i]['id']}'>"
              . "<i class='enxt-icon-move' title='"
              . esc_attr(__('Change the order of the profiles by dragging', 'electnext'))
              . "'></i>"
              . "<strong>{$pols[$i]['name']}</strong>"
              . (strlen($pols[$i]['title']) ? " - <span>{$pols[$i]['title']}</span>" : "")
              . "<a href='#' class='enxt-pol-remove'><i class='enxt-icon-remove' title='"
              . esc_attr(__('Remove this profile', 'electnext'))
              . "'></i></a></li>";
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

    $pols = $this->array_map_recursive('sanitize_text_field', $_POST['electnext_pols_meta']);
    update_post_meta($post_id, 'electnext_pols', $pols);
  }

  public function add_politician_profiles($content) {
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

  // from http://php.net/manual/en/function.array-map.php#107808
  private function array_map_recursive($fn, $arr) {
    $rarr = array();
    if (is_array($arr)) {
      foreach ($arr as $k => $v) {
        $rarr[$k] = is_array($v)
          ? $this->array_map_recursive($fn, $v)
          : $fn($v);
      }
    }
    return $rarr;
  }
}
