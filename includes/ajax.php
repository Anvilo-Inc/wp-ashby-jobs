<?php

/**
 * Ashby Jobs AJAX Handler
 */

// Prevent direct access
if (!defined('ABSPATH')) {
  exit;
}

class AshbyJobsAjax
{

  /**
   * Constructor
   */
  public function __construct()
  {
    // Public and private AJAX hooks
    add_action('wp_ajax_ashby_filter_jobs', array($this, 'filter_jobs'));
    add_action('wp_ajax_nopriv_ashby_filter_jobs', array($this, 'filter_jobs'));

    add_action('wp_ajax_ashby_refresh_jobs', array($this, 'refresh_jobs'));
    add_action('wp_ajax_nopriv_ashby_refresh_jobs', array($this, 'refresh_jobs'));

    add_action('wp_ajax_ashby_load_more_jobs', array($this, 'load_more_jobs'));
    add_action('wp_ajax_nopriv_ashby_load_more_jobs', array($this, 'load_more_jobs'));
  }

  /**
   * Filter jobs via AJAX
   */
  public function filter_jobs()
  {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ashby_jobs_nonce')) {
      wp_die(__('Security check failed', 'ashby-jobs'));
    }

    try {
      // Get filter parameters
      $filters = $this->get_filter_parameters();

      // Get jobs
      $api = new AshbyJobsAPI();
      $data = $api->fetch_jobs();

      if (is_wp_error($data)) {
        wp_send_json_error(array(
          'message' => $data->get_error_message()
        ));
        return;
      }

      $jobs = isset($data['jobs']) ? $data['jobs'] : array();

      // Apply filters
      $filtered_jobs = $api->filter_jobs($jobs, $filters);

      // Apply pagination
      $page = isset($_POST['page']) ? intval($_POST['page']) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Missing
      $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 10; // phpcs:ignore WordPress.Security.NonceVerification.Missing
      $offset = ($page - 1) * $per_page;

      $total_jobs = count($filtered_jobs);
      $paged_jobs = array_slice($filtered_jobs, $offset, $per_page);

      // Render jobs HTML
      ob_start();

      if (empty($paged_jobs)) {
        $this->render_no_jobs_message();
      } else {
        $show_compensation = get_option('ashby_jobs_include_compensation', false);
        $show_job_meta = get_option('ashby_jobs_show_job_meta', false);
        foreach ($paged_jobs as $job) {
          $this->render_job_card($job, $show_compensation, $show_job_meta);
        }
      }

      $jobs_html = ob_get_clean();

      // Calculate pagination info
      $has_more = $total_jobs > ($offset + $per_page);
      $total_pages = ceil($total_jobs / $per_page);

      // Send response
      wp_send_json_success(array(
        'jobs_html' => $jobs_html,
        'total_jobs' => $total_jobs,
        'current_page' => $page,
        'total_pages' => $total_pages,
        'has_more' => $has_more,
        'filters_applied' => $filters
      ));
    } catch (Exception $e) {
      if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Ashby Jobs AJAX Error: ' . $e->getMessage());
      }
      wp_send_json_error(array(
        'message' => __('An error occurred while filtering jobs', 'ashby-jobs')
      ));
    }
  }

  /**
   * Refresh jobs data via AJAX
   */
  public function refresh_jobs()
  {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ashby_jobs_nonce')) {
      wp_die(__('Security check failed', 'ashby-jobs'));
    }

    try {
      // Clear cache and fetch fresh data
      $api = new AshbyJobsAPI();
      $api->clear_cache();
      $data = $api->fetch_jobs();

      if (is_wp_error($data)) {
        wp_send_json_error(array(
          'message' => $data->get_error_message()
        ));
        return;
      }

      $jobs = isset($data['jobs']) ? $data['jobs'] : array();

      // Get filter options
      $departments = $api->get_departments($jobs);
      $locations = $api->get_locations($jobs);
      $employment_types = $api->get_employment_types($jobs);

      wp_send_json_success(array(
        'total_jobs' => count($jobs),
        'departments' => $departments,
        'locations' => $locations,
        'employment_types' => $employment_types,
        'last_updated' => current_time('mysql')
      ));
    } catch (Exception $e) {
      if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Ashby Jobs Refresh Error: ' . $e->getMessage());
      }
      wp_send_json_error(array(
        'message' => __('An error occurred while refreshing jobs', 'ashby-jobs')
      ));
    }
  }

  /**
   * Load more jobs via AJAX (pagination)
   */
  public function load_more_jobs()
  {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ashby_jobs_nonce')) {
      wp_die(__('Security check failed', 'ashby-jobs'));
    }

    try {
      // Get parameters
      $filters = $this->get_filter_parameters();
      $page = isset($_POST['page']) ? intval($_POST['page']) : 2; // phpcs:ignore WordPress.Security.NonceVerification.Missing
      $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 10; // phpcs:ignore WordPress.Security.NonceVerification.Missing

      // Get jobs
      $api = new AshbyJobsAPI();
      $data = $api->fetch_jobs();

      if (is_wp_error($data)) {
        wp_send_json_error(array(
          'message' => $data->get_error_message()
        ));
        return;
      }

      $jobs = isset($data['jobs']) ? $data['jobs'] : array();

      // Apply filters
      $filtered_jobs = $api->filter_jobs($jobs, $filters);

      // Get jobs for this page
      $offset = ($page - 1) * $per_page;
      $paged_jobs = array_slice($filtered_jobs, $offset, $per_page);

      // Render jobs HTML
      ob_start();

      if (!empty($paged_jobs)) {
        $show_compensation = get_option('ashby_jobs_include_compensation', false);
        $show_job_meta = get_option('ashby_jobs_show_job_meta', false);
        foreach ($paged_jobs as $job) {
          $this->render_job_card($job, $show_compensation, $show_job_meta);
        }
      }

      $jobs_html = ob_get_clean();

      // Calculate if there are more pages
      $total_jobs = count($filtered_jobs);
      $has_more = $total_jobs > ($offset + $per_page);

      wp_send_json_success(array(
        'jobs_html' => $jobs_html,
        'current_page' => $page,
        'has_more' => $has_more
      ));
    } catch (Exception $e) {
      if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Ashby Jobs Load More Error: ' . $e->getMessage());
      }
      wp_send_json_error(array(
        'message' => __('An error occurred while loading more jobs', 'ashby-jobs')
      ));
    }
  }

  /**
   * Get filter parameters from POST data
   *
   * @return array Filter parameters
   */
  private function get_filter_parameters()
  {
    // This private helper is only called by filter_jobs and load_more_jobs after nonce verification.
    // Therefore, phpcs:ignore for WordPress.Security.NonceVerification.Missing is appropriate here.
    $filters = array(
      'department' => isset($_POST['department']) ? sanitize_text_field(wp_unslash($_POST['department'])) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
      'location' => isset($_POST['location']) ? sanitize_text_field(wp_unslash($_POST['location'])) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
      'employment_type' => isset($_POST['employment_type']) ? sanitize_text_field(wp_unslash($_POST['employment_type'])) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing
      'search' => isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '' // phpcs:ignore WordPress.Security.NonceVerification.Missing
    );

    // Only include remote filter if it's explicitly set to true
    if (isset($_POST['remote']) && filter_var($_POST['remote'], FILTER_VALIDATE_BOOLEAN)) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
      $filters['remote'] = true;
    }

    return $filters;
  }

  /**
   * Render a job card (duplicate from shortcode for AJAX)
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

          <?php if (!empty($job['department'])): ?>
            <div class="ashby-job-department">
              <?php echo esc_html($job['department']); ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="ashby-job-meta">
          <?php if (!empty($job['location'])): ?>
            <span class="ashby-job-location">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" />
              </svg>
              <?php echo esc_html($job['location']); ?>
            </span>
          <?php endif; ?>

          <?php if (!empty($job['employment_type'])): ?>
            <span class="ashby-job-type">
              <?php echo esc_html($job['employment_type']); ?>
            </span>
          <?php endif; ?>

          <?php if ($job['is_remote']): ?>
            <span class="ashby-job-remote">
              <?php esc_html_e('Remote', 'ashby-jobs'); ?>
            </span>
          <?php endif; ?>

          <?php if (!empty($job['team'])): ?>
            <span class="ashby-job-team">
              <?php echo esc_html($job['team']); ?>
            </span>
          <?php endif; ?>
        </div>

        <?php if (!empty($job['description_plain'])): ?>
          <div class="ashby-job-description">
            <?php echo esc_html(wp_trim_words($job['description_plain'], 25, '...')); ?>
          </div>
        <?php endif; ?>

        <?php if ($show_compensation && !empty($job['compensation'])): ?>
          <div class="ashby-job-compensation">
            <?php $this->render_compensation($job['compensation']); ?>
          </div>
        <?php endif; ?>

        <div class="ashby-job-actions">
          <a href="<?php echo esc_url($job['apply_url'] ?: $job['job_url']); ?>"
            target="_blank"
            rel="noopener"
            class="ashby-job-apply-btn">
            <?php esc_html_e('Apply Now', 'ashby-jobs'); ?>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path fill-rule="evenodd" clip-rule="evenodd" d="M13.6727 22V20.7826L21.5453 12.5H1.56407H0.762085V11.5H1.56407H21.5453L13.6727 3.21739V2L23.2379 12L13.6727 22Z" fill="currentColor" />
            </svg>
          </a>
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
  private function render_no_jobs_message()
  {
  ?>
    <div class="ashby-jobs-empty">
      <p><?php esc_html_e('No jobs found matching your criteria.', 'ashby-jobs'); ?></p>
      <p><?php esc_html_e('Try adjusting your filters or search terms.', 'ashby-jobs'); ?></p>
    </div>
<?php
  }
}
