<?php

/**
 * Plugin Name: Ashby Jobs Integration
 * Description: Displays job postings from Ashby ATS with filtering functionality. Integrates seamlessly with your existing design.
 * Version: 1.4.0
 * Author: Anvilo, Inc.
 * Author URI: https://anvilo.com
 * License: GPL v2 or later
 * Text Domain: ashby-jobs
 * Domain Path: /languages
 * Requires at least: 5.7
 * Requires PHP: 7.4
 * Tested up to: 6.5
 */

// Prevent direct access
if (!defined('ABSPATH')) {
  exit;
}

// Define plugin constants
define('ASHBY_JOBS_VERSION', '1.4.0');
define('ASHBY_JOBS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ASHBY_JOBS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ASHBY_JOBS_PLUGIN_FILE', __FILE__);

/**
 * Main plugin class
 */
class AshbyJobsPlugin
{

  /**
   * Instance of this class
   */
  private static $instance = null;

  /**
   * Get instance
   */
  public static function get_instance()
  {
    if (null === self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Constructor
   */
  private function __construct()
  {
    add_action('init', array($this, 'init'));
    add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

    // Activation and deactivation hooks
    register_activation_hook(__FILE__, array($this, 'activate'));
    register_deactivation_hook(__FILE__, array($this, 'deactivate'));

    // Admin menu
    add_action('admin_menu', array($this, 'add_admin_menu'));

    // Settings link in plugin list
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));
  }

  /**
   * Initialize plugin
   */
  public function init()
  {
    // Load text domain for translations (required for plugins not hosted on WordPress.org).
    // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
    load_plugin_textdomain('ashby-jobs', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // Include required files
    $this->include_files();

    // Initialize components
    if (class_exists('AshbyJobsShortcode')) {
      new AshbyJobsShortcode();
    }

    if (class_exists('AshbyJobsAjax')) {
      new AshbyJobsAjax();
    }
  }

  /**
   * Include required files
   */
  private function include_files()
  {
    require_once ASHBY_JOBS_PLUGIN_DIR . 'includes/api.php';
    require_once ASHBY_JOBS_PLUGIN_DIR . 'includes/shortcode.php';
    require_once ASHBY_JOBS_PLUGIN_DIR . 'includes/ajax.php';
    require_once ASHBY_JOBS_PLUGIN_DIR . 'includes/template.php';

    if (is_admin()) {
      require_once ASHBY_JOBS_PLUGIN_DIR . 'admin/settings.php';
    }
  }

  /**
   * Enqueue frontend scripts and styles
   */
  public function enqueue_scripts()
  {
    // Only load on pages that might have the shortcode
    if ($this->should_load_assets()) {
      wp_enqueue_style(
        'ashby-jobs-style',
        ASHBY_JOBS_PLUGIN_URL . 'assets/ashby-jobs.css',
        array(),
        ASHBY_JOBS_VERSION
      );

      // Add custom CSS inline with the main stylesheet
      $custom_css = get_option('ashby_jobs_custom_css', '');
      if (!empty($custom_css)) {
        wp_add_inline_style('ashby-jobs-style', $custom_css);
      }

      wp_enqueue_script(
        'ashby-jobs-script',
        ASHBY_JOBS_PLUGIN_URL . 'assets/ashby-jobs.js',
        array('jquery'),
        ASHBY_JOBS_VERSION,
        true
      );

      // Localize script for AJAX
      wp_localize_script('ashby-jobs-script', 'ashbyJobs', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ashby_jobs_nonce'),
        'loading' => __('Loading jobs...', 'ashby-jobs'),
        'noJobs' => __('No jobs found matching your criteria.', 'ashby-jobs'),
        'error' => __('Error loading jobs. Please try again.', 'ashby-jobs')
      ));
    }
  }

  /**
   * Enqueue admin scripts and styles
   */
  public function admin_enqueue_scripts($hook)
  {
    if ('settings_page_ashby-jobs' === $hook) {
      wp_enqueue_style(
        'ashby-jobs-admin-style',
        ASHBY_JOBS_PLUGIN_URL . 'assets/admin.css',
        array(),
        ASHBY_JOBS_VERSION
      );
    }
  }

  /**
   * Check if we should load assets on current page
   */
  private function should_load_assets()
  {
    global $post;

    // Always load on careers page or if shortcode is present
    if (
      is_page('careers') ||
      (is_object($post) && has_shortcode($post->post_content, 'ashby_jobs'))
    ) {
      return true;
    }

    return false;
  }

  /**
   * Plugin activation
   */
  public function activate()
  {
    // Set default options
    add_option('ashby_jobs_client_name', '');
    add_option('ashby_jobs_cache_duration', 86400); // 1 day
    add_option('ashby_jobs_include_compensation', false);
    add_option('ashby_jobs_show_job_meta', false);
    add_option('ashby_jobs_enable_filters', true);
    add_option('ashby_jobs_custom_css', '');

    // Clear any existing cache
    delete_transient('ashby_jobs_data');
    delete_transient('ashby_jobs_data_timestamp');

    // Flush rewrite rules
    flush_rewrite_rules();
  }

  /**
   * Plugin deactivation
   */
  public function deactivate()
  {
    // Clear cache
    delete_transient('ashby_jobs_data');
    delete_transient('ashby_jobs_data_timestamp');

    // Flush rewrite rules
    flush_rewrite_rules();
  }

  /**
   * Add admin menu
   */
  public function add_admin_menu()
  {
    add_options_page(
      __('Ashby Jobs Settings', 'ashby-jobs'),
      __('Ashby Jobs', 'ashby-jobs'),
      'manage_options',
      'ashby-jobs',
      array($this, 'admin_page')
    );
  }

  /**
   * Admin page callback
   */
  public function admin_page()
  {
    if (class_exists('AshbyJobsSettings')) {
      AshbyJobsSettings::render_page();
    }
  }

  /**
   * Add settings link to plugin actions
   */
  public function plugin_action_links($links)
  {
    $settings_link = '<a href="' . admin_url('options-general.php?page=ashby-jobs') . '">' . __('Settings', 'ashby-jobs') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
  }
}

/**
 * Helper function to get plugin instance
 */
function ashby_jobs()
{
  return AshbyJobsPlugin::get_instance();
}

/**
 * Initialize plugin
 */
ashby_jobs();
