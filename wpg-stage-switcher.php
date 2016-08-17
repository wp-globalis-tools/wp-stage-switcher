<?php
/*
Plugin Name:  Stage Switcher
Plugin URI:   https://github.com/wp-globalis-tools/wpg-stage-switcher
Description:  A WordPress plugin that allows you to switch between different stages from the admin bar.
Version:      2.0.0
Author:       Roots
Author URI:   https://roots.io/
License:      MIT License
*/

namespace Roots\StageSwitcher;

use Purl\Url;

/**
 * Require Composer autoloader if installed on it's own
 */
if (file_exists($composer = __DIR__ . '/vendor/autoload.php')) {
  require_once $composer;
}

/**
 * Add stage/stage switcher to admin bar
 * Inspired by http://37signals.com/svn/posts/3535-beyond-the-default-rails-stages
 *
 * STAGES constant must be a serialized array of 'stage' => 'url' elements:
 *
 *   $stages = [
 *    'development' => 'http://example.dev',
 *    'staging'     => 'http://example-staging.com',
 *    'production'  => 'http://example.com'
 *   ];
 *
 *   define('STAGES', serialize($stages));
 *
 * WP_STAGE must be defined as the current stage
 */
class StageSwitcher {
  public function __construct() {
    add_action('admin_bar_menu', [$this, 'admin_bar_stage_switcher']);
    add_action('wp_before_admin_bar_render', [$this, 'admin_css']);
  }

  public function admin_bar_stage_switcher($admin_bar) {
    if (!defined('STAGES') && !defined('WP_STAGE') && !apply_filters('bedrock/stage_switcher_visibility', is_super_admin())) {
      return;
    }

    $stages = unserialize(STAGES);
    $current_stage = WP_STAGE;
    $current_stage_url = parse_url($stages[WP_STAGE]);
    $current_stage_path = isset($current_stage_url['path']) ? $current_stage_url['path'] : '';

    $admin_bar->add_menu([
      'id'     => 'stage',
      'parent' => 'top-secondary',
      'title'  => ucwords($current_stage),
      'href'   => '#'
    ]);

    foreach($stages as $stage => $url) {
      if ($stage === $current_stage) {
        continue;
      }

      $stage_url  = parse_url($url);
      $stage_path = isset($stage_url['path']) ? $stage_url['path'] : '';
      $request    = str_replace($current_stage_path, '', $_SERVER['REQUEST_URI']);

      if (is_multisite() && defined('SUBDOMAIN_INSTALL') && SUBDOMAIN_INSTALL && !is_main_site()) {
        $url = $this->multisite_url($url) . $request;
      } else {
        $request = str_replace($current_stage_path, '', $_SERVER['REQUEST_URI']);
        $url .= $request;
      }

      $admin_bar->add_menu([
        'id'     => "stage_$stage",
        'parent' => 'stage',
        'title'  => ucwords($stage),
        'href'   => $url
      ]);
    }
  }

  public function admin_css() { ?>
    <style>
      #wp-admin-bar-stage > a:before {
        content: "\f177";
        top: 2px;
      }
    </style>
    <?php
  }

  private function multisite_url($url) {
    $stage_url = new Url($url);
    $current_site = new Url(get_home_url(get_current_blog_id()));
    $current_site->host = str_replace($current_site->registerableDomain, $stage_url->registerableDomain, $current_site->host);

    return rtrim($current_site->getUrl(), '/') . $_SERVER['REQUEST_URI'];
  }
}

new StageSwitcher;
