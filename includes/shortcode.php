<?php

/**
 * Ashby Jobs Shortcode Handler
 */

// Prevent direct access
if (!defined('ABSPATH')) {
  exit;
}

class AshbyJobsShortcode
{

  /**
   * Constructor
   */
  public function __construct()
  {
    add_shortcode('ashby_jobs', array($this, 'render_shortcode'));
  }

  /**
   * Render the shortcode
   *
   * @param array $atts Shortcode attributes
   * @param string $content Shortcode content
   * @return string HTML output
   */
  public function render_shortcode($atts, $content = '')
  {
    // Parse shortcode attributes
    $atts = shortcode_atts(array(
      'limit' => 0,
      'department' => '',
      'location' => '',
      'employment_type' => '',
      'show_filters' => 'true',
      'show_search' => 'true',
      'show_job_meta' => 'auto', // auto, true, false
      'layout' => 'grid', // grid or list
      'show_compensation' => 'auto' // auto, true, false
    ), $atts, 'ashby_jobs');

    // Sanitize attributes
    $limit = absint($atts['limit']);
    $department = sanitize_text_field($atts['department']);
    $location = sanitize_text_field($atts['location']);
    $employment_type = sanitize_text_field($atts['employment_type']);
    $show_filters = filter_var($atts['show_filters'], FILTER_VALIDATE_BOOLEAN);
    $show_search = filter_var($atts['show_search'], FILTER_VALIDATE_BOOLEAN);
    $show_job_meta = $atts['show_job_meta'];
    $layout = in_array($atts['layout'], array('grid', 'list')) ? $atts['layout'] : 'grid';
    $show_compensation = $atts['show_compensation'];

    // Override filters setting if disabled globally
    if (!get_option('ashby_jobs_enable_filters', true)) {
      $show_filters = false;
    }

    // Override job meta setting
    if ($show_job_meta === 'auto') {
      $show_job_meta = get_option('ashby_jobs_show_job_meta', false);
    } else {
      $show_job_meta = filter_var($show_job_meta, FILTER_VALIDATE_BOOLEAN);
    }

    // Override compensation setting
    if ($show_compensation === 'auto') {
      $show_compensation = get_option('ashby_jobs_include_compensation', false);
    } else {
      $show_compensation = filter_var($show_compensation, FILTER_VALIDATE_BOOLEAN);
    }

    // Get jobs data
    $api = new AshbyJobsAPI();
    $data = $api->fetch_jobs();

    if (is_wp_error($data)) {
      return $this->render_error($data->get_error_message());
    }

    $jobs = isset($data['jobs']) ? $data['jobs'] : array();

    // Apply initial filters from shortcode attributes
    $initial_filters = array();
    if (!empty($department)) {
      $initial_filters['department'] = $department;
    }
    if (!empty($location)) {
      $initial_filters['location'] = $location;
    }
    if (!empty($employment_type)) {
      $initial_filters['employment_type'] = $employment_type;
    }

    if (!empty($initial_filters)) {
      $jobs = $api->filter_jobs($jobs, $initial_filters);
    }

    // Apply limit
    if ($limit > 0 && count($jobs) > $limit) {
      $jobs = array_slice($jobs, 0, $limit);
    }

    // Generate unique ID for this instance
    $instance_id = 'ashby-jobs-' . uniqid();

    // Start output buffering
    ob_start();

    echo '<div id="' . esc_attr($instance_id) . '" class="ashby-jobs-container ashby-jobs-layout-' . esc_attr($layout) . '">';

    // Render filters if enabled
    if ($show_filters && !empty($jobs)) {
      $this->render_filters($api, $jobs, $show_search);
    }

    // Render loading indicator
    echo '<div class="ashby-jobs-loading" style="display: none;">';
    echo '<div class="ashby-jobs-spinner"></div>';
    echo '<p>' . __('Loading jobs...', 'ashby-jobs') . '</p>';
    echo '</div>';

    // Render jobs container
    echo '<div class="ashby-jobs-list" data-layout="' . esc_attr($layout) . '">';

    if (empty($jobs)) {
      $this->render_no_jobs();
    } else {
      foreach ($jobs as $job) {
        $this->render_job_card($job, $show_compensation, $show_job_meta);
      }
    }

    echo '</div>'; // .ashby-jobs-list

    // Render results count after jobs list
    echo '<div class="ashby-jobs-results-count">';
    /* translators: %d: number of jobs being displayed */
    echo '<span id="ashby-results-text">' . sprintf(__('Showing %d jobs', 'ashby-jobs'), count($jobs)) . '</span>';
    echo '</div>';

    // Add pagination if we have many jobs
    if (count($jobs) > 10) {
      $this->render_pagination();
    }

    echo '</div>'; // .ashby-jobs-container

    // Add inline script for this instance
    $this->add_inline_script($instance_id, $initial_filters);

    return ob_get_clean();
  }

  /**
   * Render filter controls
   *
   * @param AshbyJobsAPI $api API instance
   * @param array $jobs Jobs array
   * @param bool $show_search Whether to show search
   */
  private function render_filters($api, $jobs, $show_search)
  {
    $departments = $api->get_departments($jobs);
    $locations = $api->get_locations($jobs);
    $employment_types = $api->get_employment_types($jobs);
?>
    <div class="ashby-jobs-filters">
      <div class="ashby-jobs-filters-row">

        <?php if ($show_search): ?>
          <div class="ashby-jobs-filter-group">
            <label for="ashby-search" class="ashby-jobs-filter-label">
              <?php esc_html_e('Search Jobs', 'ashby-jobs'); ?>
            </label>
            <input type="text"
              id="ashby-search"
              class="ashby-jobs-search"
              placeholder="<?php esc_attr_e('Search by title, department, or description...', 'ashby-jobs'); ?>" />
          </div>
        <?php endif; ?>

        <?php if (!empty($departments)): ?>
          <div class="ashby-jobs-filter-group">
            <label for="ashby-department" class="ashby-jobs-filter-label">
              <?php esc_html_e('Department', 'ashby-jobs'); ?>
            </label>
            <select id="ashby-department" class="ashby-jobs-filter">
              <option value=""><?php esc_html_e('All Departments', 'ashby-jobs'); ?></option>
              <?php foreach ($departments as $dept): ?>
                <option value="<?php echo esc_attr($dept); ?>">
                  <?php echo esc_html($dept); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <?php if (!empty($locations)): ?>
          <div class="ashby-jobs-filter-group">
            <label for="ashby-location" class="ashby-jobs-filter-label">
              <?php esc_html_e('Location', 'ashby-jobs'); ?>
            </label>
            <select id="ashby-location" class="ashby-jobs-filter">
              <option value=""><?php esc_html_e('All Locations', 'ashby-jobs'); ?></option>
              <?php foreach ($locations as $loc): ?>
                <option value="<?php echo esc_attr($loc); ?>">
                  <?php echo esc_html($loc); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <?php if (!empty($employment_types)): ?>
          <div class="ashby-jobs-filter-group">
            <label for="ashby-employment-type" class="ashby-jobs-filter-label">
              <?php esc_html_e('Type', 'ashby-jobs'); ?>
            </label>
            <select id="ashby-employment-type" class="ashby-jobs-filter">
              <option value=""><?php esc_html_e('All Types', 'ashby-jobs'); ?></option>
              <?php foreach ($employment_types as $type): ?>
                <option value="<?php echo esc_attr($type); ?>">
                  <?php echo esc_html($type); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <div class="ashby-jobs-filter-group">
          <label class="ashby-jobs-checkbox-label">
            <input type="checkbox" id="ashby-remote" class="ashby-jobs-filter" />
            <?php esc_html_e('Remote jobs only', 'ashby-jobs'); ?>
          </label>
        </div>

        <div class="ashby-jobs-filter-group">
          <button type="button" id="ashby-clear-filters" class="ashby-jobs-clear-btn">
            <?php esc_html_e('Clear Filters', 'ashby-jobs'); ?>
          </button>
        </div>

      </div>
    </div>
  <?php
  }

  /**
   * Render a job card
   *
   * @param array $job Job data
   * @param bool $show_compensation Whether to show compensation
   * @param bool $show_job_meta Whether to show job metadata
   */
  private function render_job_card($job, $show_compensation = false, $show_job_meta = false)
  {
  ?>
    <div class="ashby-job-card"
      data-department="<?php echo esc_attr($job['department']); ?>"
      data-location="<?php echo esc_attr($job['location']); ?>"
      data-employment-type="<?php echo esc_attr($job['employment_type']); ?>"
      data-remote="<?php echo $job['is_remote'] ? 'true' : 'false'; ?>">

      <div class="ashby-job-card-content">
        <div class="ashby-job-header">
          <h3 class="ashby-job-title">
            <a href="<?php echo esc_url($job['apply_url'] ?: $job['job_url']); ?>"
              target="_blank"
              rel="noopener">
              <?php echo esc_html($job['title']); ?>
            </a>
          </h3>

          <?php if ($show_job_meta): ?>
            <div class="ashby-job-meta-line">
              <?php
              $meta_parts = array();

              if (!empty($job['department'])) {
                $meta_parts[] = esc_html($job['department']);
              }

              if ($job['is_remote']) {
                $meta_parts[] = __('Remote', 'ashby-jobs');
              } elseif (!empty($job['location'])) {
                $meta_parts[] = esc_html($job['location']);
              }

              if (!empty($job['employment_type'])) {
                $meta_parts[] = esc_html($job['employment_type']);
              }

              if ($job['is_remote'] && !empty($job['location'])) {
                $meta_parts[] = esc_html($job['location']);
              }

              echo implode(' • ', $meta_parts);
              ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php
  }

  /**
   * Render compensation information
   *
   * @param array $compensation Compensation data
   */
  private function render_compensation($compensation)
  {
    if (isset($compensation['compensationTierSummary'])) {
      echo '<strong>' . esc_html__('Compensation:', 'ashby-jobs') . '</strong> ';
      echo esc_html($compensation['compensationTierSummary']);
    }
  }

  /**
   * Render no jobs message
   */
  private function render_no_jobs()
  {
  ?>
    <div class="ashby-jobs-empty">
      <p><?php esc_html_e('No job openings are currently available.', 'ashby-jobs'); ?></p>
      <p><?php esc_html_e('Please check back later for new opportunities.', 'ashby-jobs'); ?></p>
    </div>
  <?php
  }

  /**
   * Render pagination
   */
  private function render_pagination()
  {
  ?>
    <div class="ashby-jobs-pagination" style="display: none;">
      <button class="ashby-jobs-load-more"><?php esc_html_e('Load More Jobs', 'ashby-jobs'); ?></button>
    </div>
  <?php
  }

  /**
   * Render error message
   *
   * @param string $message Error message
   * @return string HTML
   */
  private function render_error($message)
  {
    return '<div class="ashby-jobs-error"><p>' .
      /* translators: %s: error message */
      sprintf(__('Error loading jobs: %s', 'ashby-jobs'), esc_html($message)) .
      '</p></div>';
  }

  /**
   * Add inline script for this shortcode instance
   *
   * @param string $instance_id Unique instance ID
   * @param array $initial_filters Initial filter values
   */
  private function add_inline_script($instance_id, $initial_filters)
  {
  ?>
    <script type="text/javascript">
      document.addEventListener('DOMContentLoaded', function() {
        if (typeof AshbyJobs !== 'undefined') {
          new AshbyJobs(<?php echo wp_json_encode($instance_id); ?>, <?php echo wp_json_encode($initial_filters); ?>);
        }
      });
    </script>
<?php
  }
}
