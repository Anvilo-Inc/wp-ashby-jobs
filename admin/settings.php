<?php

/**
 * Admin Settings Page
 * Enhanced UX version with better organization and styling
 */

// Prevent direct access
if (!defined('ABSPATH')) {
  exit;
}

class AshbyJobsSettings
{

  /**
   * Constructor
   */
  public function __construct()
  {
    add_action('admin_init', array($this, 'init_settings'));
  }

  /**
   * Initialize settings
   */
  public function init_settings()
  {
    // Register settings
    register_setting('ashby_jobs_settings', 'ashby_jobs_client_name', array(
      'type' => 'string',
      'sanitize_callback' => 'sanitize_text_field',
      'default' => ''
    ));

    register_setting('ashby_jobs_settings', 'ashby_jobs_cache_duration', array(
      'type' => 'integer',
      'sanitize_callback' => array($this, 'sanitize_cache_duration'),
      'default' => 86400
    ));

    register_setting('ashby_jobs_settings', 'ashby_jobs_include_compensation', array(
      'type' => 'boolean',
      'sanitize_callback' => array($this, 'sanitize_checkbox'),
      'default' => false
    ));

    register_setting('ashby_jobs_settings', 'ashby_jobs_show_job_meta', array(
      'type' => 'boolean',
      'sanitize_callback' => array($this, 'sanitize_checkbox'),
      'default' => false
    ));

    register_setting('ashby_jobs_settings', 'ashby_jobs_enable_filters', array(
      'type' => 'boolean',
      'sanitize_callback' => array($this, 'sanitize_checkbox'),
      'default' => true
    ));

    register_setting('ashby_jobs_settings', 'ashby_jobs_custom_css', array(
      'type' => 'string',
      'sanitize_callback' => array($this, 'sanitize_css'),
      'default' => ''
    ));

    // Add settings sections
    add_settings_section(
      'ashby_jobs_api_section',
      esc_html__('API Configuration', 'ashby-jobs'),
      array($this, 'api_section_callback'),
      'ashby_jobs_settings'
    );

    add_settings_section(
      'ashby_jobs_display_section',
      esc_html__('Display Options', 'ashby-jobs'),
      array($this, 'display_section_callback'),
      'ashby_jobs_settings'
    );

    add_settings_section(
      'ashby_jobs_customization_section',
      esc_html__('Customization', 'ashby-jobs'),
      array($this, 'customization_section_callback'),
      'ashby_jobs_settings'
    );

    // Add settings fields
    add_settings_field(
      'ashby_jobs_client_name',
      esc_html__('Ashby Client Name', 'ashby-jobs'),
      array($this, 'client_name_field'),
      'ashby_jobs_settings',
      'ashby_jobs_api_section'
    );

    add_settings_field(
      'ashby_jobs_cache_duration',
      esc_html__('Cache Duration', 'ashby-jobs'),
      array($this, 'cache_duration_field'),
      'ashby_jobs_settings',
      'ashby_jobs_api_section'
    );

    add_settings_field(
      'ashby_jobs_include_compensation',
      esc_html__('Include Compensation Data', 'ashby-jobs'),
      array($this, 'include_compensation_field'),
      'ashby_jobs_settings',
      'ashby_jobs_api_section'
    );

    add_settings_field(
      'ashby_jobs_show_job_meta',
      esc_html__('Show Job Details', 'ashby-jobs'),
      array($this, 'show_job_meta_field'),
      'ashby_jobs_settings',
      'ashby_jobs_display_section'
    );

    add_settings_field(
      'ashby_jobs_enable_filters',
      esc_html__('Enable Job Filters', 'ashby-jobs'),
      array($this, 'enable_filters_field'),
      'ashby_jobs_settings',
      'ashby_jobs_display_section'
    );

    add_settings_field(
      'ashby_jobs_custom_css',
      esc_html__('Custom CSS', 'ashby-jobs'),
      array($this, 'custom_css_field'),
      'ashby_jobs_settings',
      'ashby_jobs_customization_section'
    );
  }

  /**
   * API section callback
   */
  public function api_section_callback()
  {
    echo '<p>' . esc_html__('Configure your connection to the Ashby API. You can find your client name in your Ashby job board URL.', 'ashby-jobs') . '</p>';
  }

  /**
   * Display section callback
   */
  public function display_section_callback()
  {
    echo '<p>' . esc_html__('Customize how jobs are displayed on your website and what features are enabled for visitors.', 'ashby-jobs') . '</p>';
  }

  /**
   * Customization section callback
   */
  public function customization_section_callback()
  {
    echo '<p>' . esc_html__('Add custom styling to match your site\'s design. CSS will be applied to all pages with job listings.', 'ashby-jobs') . '</p>';
  }

  /**
   * Client name field
   */
  public function client_name_field()
  {
    $value = get_option('ashby_jobs_client_name', '');
    echo '<input type="text" id="ashby_jobs_client_name" name="ashby_jobs_client_name" value="' . esc_attr($value) . '" class="regular-text" placeholder="' . esc_attr__('yourcompany', 'ashby-jobs') . '" />';
    echo '<p class="description">' . esc_html__('Your Ashby client name (e.g., if your job board is at jobs.ashbyhq.com/yourcompany, enter "yourcompany").', 'ashby-jobs') . '</p>';
  }

  /**
   * Cache duration field
   */
  public function cache_duration_field()
  {
    $value = get_option('ashby_jobs_cache_duration', 86400);
    $options = array(
      86400 => __('1 Day (recommended)', 'ashby-jobs'),
      604800 => __('1 Week', 'ashby-jobs'),
      2592000 => __('1 Month', 'ashby-jobs')
    );

    echo '<select id="ashby_jobs_cache_duration" name="ashby_jobs_cache_duration">';
    foreach ($options as $duration => $label) {
      echo '<option value="' . esc_attr($duration) . '"' . selected($value, $duration, false) . '>';
      echo esc_html($label);
      echo '</option>';
    }
    echo '</select>';
    echo '<p class="description">' . esc_html__('How long to cache job data from Ashby. Shorter durations mean more up-to-date jobs but more API requests.', 'ashby-jobs') . '</p>';
  }

  /**
   * Include compensation field
   */
  public function include_compensation_field()
  {
    $value = get_option('ashby_jobs_include_compensation', false);
    echo '<input type="checkbox" id="ashby_jobs_include_compensation" name="ashby_jobs_include_compensation" value="1" ' . checked(1, $value, false) . ' />';
    echo '<label for="ashby_jobs_include_compensation">' . esc_html__('Display salary and compensation information when available', 'ashby-jobs') . '</label>';
  }

  /**
   * Show job meta field
   */
  public function show_job_meta_field()
  {
    $value = get_option('ashby_jobs_show_job_meta', false);
    echo '<input type="checkbox" id="ashby_jobs_show_job_meta" name="ashby_jobs_show_job_meta" value="1" ' . checked(1, $value, false) . ' />';
    echo '<label for="ashby_jobs_show_job_meta">' . esc_html__('Display department, location, employment type, and remote status below job titles', 'ashby-jobs') . '</label>';
  }

  /**
   * Enable filters field
   */
  public function enable_filters_field()
  {
    $value = get_option('ashby_jobs_enable_filters', true);
    echo '<input type="checkbox" id="ashby_jobs_enable_filters" name="ashby_jobs_enable_filters" value="1" ' . checked(1, $value, false) . ' />';
    echo '<label for="ashby_jobs_enable_filters">' . esc_html__('Allow users to filter jobs by department, location, and employment type', 'ashby-jobs') . '</label>';
  }

  /**
   * Custom CSS field
   */
  public function custom_css_field()
  {
    $value = get_option('ashby_jobs_custom_css', '');
    echo '<textarea id="ashby_jobs_custom_css" name="ashby_jobs_custom_css" rows="8" cols="80" class="large-text code">' . esc_textarea($value) . '</textarea>';
    echo '<p class="description">' . esc_html__('Add custom CSS to override default styling. This CSS will be loaded on pages with the Ashby Jobs shortcode.', 'ashby-jobs') . '</p>';
    echo '<p class="description"><strong>' . esc_html__('Example:', 'ashby-jobs') . '</strong> <code>.ashby-jobs-container { background: #f5f5f5; border-radius: 8px; }</code></p>';
  }

  /**
   * Sanitize cache duration
   */
  public function sanitize_cache_duration($input)
  {
    $valid_durations = array(86400, 604800, 2592000); // 1 day, 1 week, 1 month
    $input = intval($input);

    if (in_array($input, $valid_durations)) {
      return $input;
    }

    // Default to 1 day if invalid value
    return 86400;
  }

  /**
   * Sanitize checkbox
   */
  public function sanitize_checkbox($input)
  {
    return $input ? 1 : 0;
  }

  /**
   * Sanitize CSS
   */
  public function sanitize_css($input)
  {
    // Strip all tags first
    $input = wp_strip_all_tags($input);

    // Remove potentially dangerous CSS patterns
    $dangerous_patterns = array(
      '/javascript\s*:/i',           // javascript: protocol
      '/expression\s*\(/i',          // IE expression()
      '/-moz-binding/i',             // Mozilla binding
      '/behaviour\s*:/i',            // IE behavior
      '/@import/i',                  // @import rules
      '/vbscript\s*:/i',             // vbscript: protocol
      '/data\s*:\s*text\/html/i',    // data:text/html
    );

    $input = preg_replace($dangerous_patterns, '', $input);

    // Remove any remaining null bytes
    $input = str_replace(chr(0), '', $input);

    return trim($input);
  }

  /**
   * Render admin page
   */
  public static function render_page()
  {
    // Handle cache clearing
    if (isset($_POST['clear_cache']) && isset($_POST['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'clear_ashby_cache')) {
      delete_transient('ashby_jobs_data');
      delete_transient('ashby_jobs_data_timestamp');
      echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Cache cleared successfully!', 'ashby-jobs') . '</p></div>';
    }

    // Handle test connection
    $test_result = '';
    if (isset($_POST['test_connection']) && isset($_POST['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'test_ashby_connection')) {
      $test_result = self::test_api_connection();
    }

    // Get current status
    $api_status = self::get_api_status();
?>
    <div class="wrap ashby-jobs-admin-wrapper">
      <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

      <div class="ashby-admin-layout">
        <!-- Main Content - Settings -->
        <div class="ashby-main-content">
          <form method="post" action="options.php">
            <?php
            settings_fields('ashby_jobs_settings');
            do_settings_sections('ashby_jobs_settings');
            submit_button(esc_html__('Save Settings', 'ashby-jobs'), 'primary large');
            ?>
          </form>
        </div>

        <!-- Right Sidebar - API Status -->
        <div class="ashby-sidebar">
          <div class="ashby-sidebar-section">
            <h3><?php esc_html_e('API Status', 'ashby-jobs'); ?></h3>

            <?php if ($test_result): ?>
              <div class="notice <?php echo strpos($test_result, 'Error') !== false ? 'notice-error' : 'notice-success'; ?> is-dismissible">
                <p><?php echo esc_html($test_result); ?></p>
              </div>
            <?php endif; ?>

            <div class="status-list">
              <div class="status-item">
                <span class="status-label"><?php esc_html_e('Connection Status', 'ashby-jobs'); ?>:</span>
                <span class="status-value ashby-status-indicator <?php echo $api_status['connection'] === 'success' ? 'success' : 'error'; ?>">
                  <?php echo $api_status['connection'] === 'success' ? esc_html__('Connected', 'ashby-jobs') : esc_html__('Not Connected', 'ashby-jobs'); ?>
                </span>
              </div>
              <div class="status-item">
                <span class="status-label"><?php esc_html_e('Jobs Found', 'ashby-jobs'); ?>:</span>
                <span class="status-value"><?php echo esc_html($api_status['job_count']); ?></span>
              </div>
              <div class="status-item">
                <span class="status-label"><?php esc_html_e('Cache Status', 'ashby-jobs'); ?>:</span>
                <span class="status-value ashby-status-indicator <?php echo $api_status['cache'] === 'active' ? 'success' : 'warning'; ?>">
                  <?php echo $api_status['cache'] === 'active' ? esc_html__('Active', 'ashby-jobs') : esc_html__('Empty', 'ashby-jobs'); ?>
                </span>
              </div>
              <div class="status-item">
                <span class="status-label"><?php esc_html_e('Last Updated', 'ashby-jobs'); ?>:</span>
                <span class="status-value"><?php echo esc_html($api_status['last_updated']); ?></span>
              </div>
            </div>

            <div class="ashby-tools">
              <h4><?php esc_html_e('Tools', 'ashby-jobs'); ?></h4>
              <form method="post" style="margin-bottom: 10px;">
                <?php wp_nonce_field('clear_ashby_cache'); ?>
                <input type="submit" name="clear_cache" class="button ashby-button-secondary" value="<?php esc_attr_e('Clear Cache', 'ashby-jobs'); ?>" />
              </form>

              <form method="post">
                <?php wp_nonce_field('test_ashby_connection'); ?>
                <input type="submit" name="test_connection" class="button ashby-button-secondary" value="<?php esc_attr_e('Test API Connection', 'ashby-jobs'); ?>" />
              </form>
            </div>
          </div>

          <div class="ashby-sidebar-section">
            <h3><?php esc_html_e('Usage Instructions', 'ashby-jobs'); ?></h3>

            <div class="ashby-usage-examples">
              <div class="usage-example">
                <h4><?php esc_html_e('Basic Usage', 'ashby-jobs'); ?></h4>
                <code>[ashby_jobs]</code>
              </div>

              <div class="usage-example">
                <h4><?php esc_html_e('With Filters', 'ashby-jobs'); ?></h4>
                <code>[ashby_jobs department="Engineering"]</code>
              </div>

              <div class="usage-example">
                <h4><?php esc_html_e('Limit Results', 'ashby-jobs'); ?></h4>
                <code>[ashby_jobs limit="5"]</code>
              </div>

              <div class="usage-example">
                <h4><?php esc_html_e('Hide Filters', 'ashby-jobs'); ?></h4>
                <code>[ashby_jobs show_filters="false"]</code>
              </div>

              <div class="usage-example">
                <h4><?php esc_html_e('Show Job Details', 'ashby-jobs'); ?></h4>
                <code>[ashby_jobs show_job_meta="true"]</code>
              </div>
            </div>

            <h4><?php esc_html_e('All Parameters', 'ashby-jobs'); ?></h4>
            <div class="ashby-parameters">
              <ul>
                <li><strong>limit</strong> - <?php esc_html_e('Number of jobs to display', 'ashby-jobs'); ?></li>
                <li><strong>department</strong> - <?php esc_html_e('Filter by specific department', 'ashby-jobs'); ?></li>
                <li><strong>location</strong> - <?php esc_html_e('Filter by specific location', 'ashby-jobs'); ?></li>
                <li><strong>employment_type</strong> - <?php esc_html_e('Filter by employment type', 'ashby-jobs'); ?></li>
                <li><strong>show_filters</strong> - <?php esc_html_e('Show/hide filter controls (true/false)', 'ashby-jobs'); ?></li>
                <li><strong>show_search</strong> - <?php esc_html_e('Show/hide search box (true/false)', 'ashby-jobs'); ?></li>
                <li><strong>show_job_meta</strong> - <?php esc_html_e('Show/hide job details line (auto/true/false)', 'ashby-jobs'); ?></li>
                <li><strong>layout</strong> - <?php esc_html_e('Display layout (grid/list)', 'ashby-jobs'); ?></li>
                <li><strong>show_compensation</strong> - <?php esc_html_e('Show compensation data (auto/true/false)', 'ashby-jobs'); ?></li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>

<?php
  }

  /**
   * Get API status
   */
  private static function get_api_status()
  {
    $api = new AshbyJobsAPI();

    $status = array(
      'connection' => 'error',
      'job_count' => 0,
      'cache' => 'empty',
      'last_updated' => esc_html__('Never', 'ashby-jobs')
    );

    // Check cache first without triggering a fetch
    if ($api->has_cache()) {
      $status['cache'] = 'active';
      $cache_timestamp = $api->get_cache_timestamp();
      if ($cache_timestamp) {
        $status['last_updated'] = human_time_diff($cache_timestamp) . ' ago';
      }

      // Get cached data to count jobs
      $cached_data = get_transient('ashby_jobs_data');
      if ($cached_data && isset($cached_data['jobs'])) {
        $status['connection'] = 'success';
        $status['job_count'] = count($cached_data['jobs']);
      }
    } else {
      // No cache exists, try to fetch to test connection
      $data = $api->fetch_jobs();
      if (!is_wp_error($data)) {
        $status['connection'] = 'success';
        $status['job_count'] = isset($data['jobs']) ? count($data['jobs']) : 0;
        $status['cache'] = 'active';
        $cache_timestamp = $api->get_cache_timestamp();
        if ($cache_timestamp) {
          $status['last_updated'] = human_time_diff($cache_timestamp) . ' ago';
        }
      }
    }

    return $status;
  }

  /**
   * Test API connection
   */
  private static function test_api_connection()
  {
    if (!class_exists('AshbyJobsAPI')) {
      return esc_html__('Error: API class not found.', 'ashby-jobs');
    }

    $api = new AshbyJobsAPI();
    $result = $api->fetch_jobs();

    if (is_wp_error($result)) {
      /* translators: %s: error message */
      return sprintf(esc_html__('Error: %s', 'ashby-jobs'), $result->get_error_message());
    }

    if (isset($result['jobs']) && is_array($result['jobs'])) {
      $count = count($result['jobs']);
      /* translators: %d: number of job postings found */
      return sprintf(esc_html__('Success! Found %d job posting(s).', 'ashby-jobs'), $count);
    }

    return esc_html__('Error: Invalid response from API.', 'ashby-jobs');
  }
}

// Initialize settings
new AshbyJobsSettings();
