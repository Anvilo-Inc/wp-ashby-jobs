<?php

/**
 * Template Functions for Ashby Jobs
 * Helper functions for displaying job data in themes
 */

// Prevent direct access
if (!defined('ABSPATH')) {
  exit;
}

/**
 * Get all jobs from Ashby API
 *
 * @param array $filters Optional filters to apply
 * @return array|WP_Error Jobs array or error
 */
function ashby_get_jobs($filters = array())
{
  $api = new AshbyJobsAPI();
  $data = $api->fetch_jobs();

  if (is_wp_error($data)) {
    return $data;
  }

  $jobs = isset($data['jobs']) ? $data['jobs'] : array();

  if (!empty($filters)) {
    $jobs = $api->filter_jobs($jobs, $filters);
  }

  return $jobs;
}

/**
 * Display a single job
 *
 * @param array $job Job data
 * @param array $args Display arguments
 */
function ashby_display_job($job, $args = array())
{
  $defaults = array(
    'show_description' => true,
    'show_compensation' => get_option('ashby_jobs_include_compensation', false),
    'show_meta' => true,
    'excerpt_length' => 25,
    'css_class' => 'ashby-job-item'
  );

  $args = wp_parse_args($args, $defaults);

?>
  <div class="<?php echo esc_attr($args['css_class']); ?>"
    data-job-id="<?php echo esc_attr($job['id']); ?>"
    data-department="<?php echo esc_attr($job['department']); ?>"
    data-location="<?php echo esc_attr($job['location']); ?>">

    <h3 class="ashby-job-title">
      <a href="<?php echo esc_url($job['apply_url'] ?: $job['job_url']); ?>"
        target="_blank"
        rel="noopener">
        <?php echo esc_html($job['title']); ?>
      </a>
    </h3>

    <?php if ($args['show_meta']): ?>
      <div class="ashby-job-meta">
        <?php if (!empty($job['department'])): ?>
          <span class="ashby-job-department">
            <?php echo esc_html($job['department']); ?>
          </span>
        <?php endif; ?>

        <?php if (!empty($job['location'])): ?>
          <span class="ashby-job-location">
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
      </div>
    <?php endif; ?>

    <?php if ($args['show_description'] && !empty($job['description_plain'])): ?>
      <div class="ashby-job-description">
        <?php echo wp_trim_words(esc_html($job['description_plain']), $args['excerpt_length'], '...'); ?>
      </div>
    <?php endif; ?>

    <?php if ($args['show_compensation'] && !empty($job['compensation'])): ?>
      <div class="ashby-job-compensation">
        <?php ashby_display_compensation($job['compensation']); ?>
      </div>
    <?php endif; ?>

    <div class="ashby-job-actions">
      <a href="<?php echo esc_url($job['apply_url'] ?: $job['job_url']); ?>"
        target="_blank"
        rel="noopener"
        class="ashby-job-apply-link">
        <?php esc_html_e('Apply Now', 'ashby-jobs'); ?>
      </a>
    </div>

  </div>
<?php
}

/**
 * Display compensation information
 *
 * @param array $compensation Compensation data from Ashby
 */
function ashby_display_compensation($compensation)
{
  if (empty($compensation)) {
    return;
  }

?>
  <div class="ashby-compensation">
    <?php if (isset($compensation['compensationTierSummary'])): ?>
      <div class="ashby-compensation-summary">
        <strong><?php esc_html_e('Compensation:', 'ashby-jobs'); ?></strong>
        <?php echo esc_html($compensation['compensationTierSummary']); ?>
      </div>
    <?php endif; ?>

    <?php if (isset($compensation['scrapeableCompensationSalarySummary'])): ?>
      <div class="ashby-compensation-salary">
        <strong><?php esc_html_e('Salary:', 'ashby-jobs'); ?></strong>
        <?php echo esc_html($compensation['scrapeableCompensationSalarySummary']); ?>
      </div>
    <?php endif; ?>
  </div>
<?php
}

/**
 * Get unique departments from current jobs
 *
 * @return array Departments list
 */
function ashby_get_departments()
{
  $api = new AshbyJobsAPI();
  $data = $api->fetch_jobs();

  if (is_wp_error($data)) {
    return array();
  }

  $jobs = isset($data['jobs']) ? $data['jobs'] : array();
  return $api->get_departments($jobs);
}

/**
 * Get unique locations from current jobs
 *
 * @return array Locations list
 */
function ashby_get_locations()
{
  $api = new AshbyJobsAPI();
  $data = $api->fetch_jobs();

  if (is_wp_error($data)) {
    return array();
  }

  $jobs = isset($data['jobs']) ? $data['jobs'] : array();
  return $api->get_locations($jobs);
}

/**
 * Get unique employment types from current jobs
 *
 * @return array Employment types list
 */
function ashby_get_employment_types()
{
  $api = new AshbyJobsAPI();
  $data = $api->fetch_jobs();

  if (is_wp_error($data)) {
    return array();
  }

  $jobs = isset($data['jobs']) ? $data['jobs'] : array();
  return $api->get_employment_types($jobs);
}

/**
 * Check if there are any jobs available
 *
 * @return bool True if jobs exist
 */
function ashby_has_jobs()
{
  $jobs = ashby_get_jobs();
  return !is_wp_error($jobs) && !empty($jobs);
}

/**
 * Get total count of available jobs
 *
 * @param array $filters Optional filters
 * @return int Number of jobs
 */
function ashby_get_jobs_count($filters = array())
{
  $jobs = ashby_get_jobs($filters);

  if (is_wp_error($jobs)) {
    return 0;
  }

  return count($jobs);
}

/**
 * Format job publish date
 *
 * @param string $date_string ISO date string
 * @param string $format Date format (default: WordPress setting)
 * @return string Formatted date
 */
function ashby_format_job_date($date_string, $format = '')
{
  if (empty($date_string)) {
    return '';
  }

  if (empty($format)) {
    $format = get_option('date_format');
  }

  return date_i18n($format, strtotime($date_string));
}

/**
 * Generate job permalink for custom job pages
 *
 * @param array $job Job data
 * @return string Job URL
 */
function ashby_get_job_permalink($job)
{
  // Use Ashby's apply URL by default
  if (!empty($job['apply_url'])) {
    return $job['apply_url'];
  }

  // Fall back to job URL
  if (!empty($job['job_url'])) {
    return $job['job_url'];
  }

  // If neither exists, return empty string
  return '';
}

/**
 * Display a jobs filter form
 *
 * @param array $args Filter form arguments
 */
function ashby_display_filters($args = array())
{
  $defaults = array(
    'show_search' => true,
    'show_department' => true,
    'show_location' => true,
    'show_type' => true,
    'show_remote' => true,
    'form_id' => 'ashby-filters',
    'css_class' => 'ashby-filters-form'
  );

  $args = wp_parse_args($args, $defaults);

  // Get filter options
  $departments = ashby_get_departments();
  $locations = ashby_get_locations();
  $employment_types = ashby_get_employment_types();

?>
  <form id="<?php echo esc_attr($args['form_id']); ?>"
    class="<?php echo esc_attr($args['css_class']); ?>"
    method="get">

    <?php if ($args['show_search']): ?>
      <div class="ashby-filter-field">
        <label for="ashby-job-search">
          <?php esc_html_e('Search Jobs', 'ashby-jobs'); ?>
        </label>
        <input type="text"
          id="ashby-job-search"
          name="job_search"
          placeholder="<?php esc_attr_e('Search by title or description...', 'ashby-jobs'); ?>"
          value="<?php echo isset($_GET['job_search']) ? esc_attr(sanitize_text_field(wp_unslash($_GET['job_search']))) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>" />
      </div>
    <?php endif; ?>

    <?php if ($args['show_department'] && !empty($departments)): ?>
      <div class="ashby-filter-field">
        <label for="ashby-job-department">
          <?php esc_html_e('Department', 'ashby-jobs'); ?>
        </label>
        <select id="ashby-job-department" name="job_department">
          <option value=""><?php esc_html_e('All Departments', 'ashby-jobs'); ?></option>
          <?php foreach ($departments as $dept): ?>
            <option value="<?php echo esc_attr($dept); ?>"
              <?php selected(isset($_GET['job_department']) ? sanitize_text_field(wp_unslash($_GET['job_department'])) : '', $dept); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>>
              <?php echo esc_html($dept); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>

    <?php if ($args['show_location'] && !empty($locations)): ?>
      <div class="ashby-filter-field">
        <label for="ashby-job-location">
          <?php esc_html_e('Location', 'ashby-jobs'); ?>
        </label>
        <select id="ashby-job-location" name="job_location">
          <option value=""><?php esc_html_e('All Locations', 'ashby-jobs'); ?></option>
          <?php foreach ($locations as $location): ?>
            <option value="<?php echo esc_attr($location); ?>"
              <?php selected(isset($_GET['job_location']) ? sanitize_text_field(wp_unslash($_GET['job_location'])) : '', $location); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>>
              <?php echo esc_html($location); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>

    <?php if ($args['show_type'] && !empty($employment_types)): ?>
      <div class="ashby-filter-field">
        <label for="ashby-job-type">
          <?php esc_html_e('Employment Type', 'ashby-jobs'); ?>
        </label>
        <select id="ashby-job-type" name="job_type">
          <option value=""><?php esc_html_e('All Types', 'ashby-jobs'); ?></option>
          <?php foreach ($employment_types as $type): ?>
            <option value="<?php echo esc_attr($type); ?>"
              <?php selected(isset($_GET['job_type']) ? sanitize_text_field(wp_unslash($_GET['job_type'])) : '', $type); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>>
              <?php echo esc_html($type); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>

    <?php if ($args['show_remote']): ?>
      <div class="ashby-filter-field">
        <label class="ashby-checkbox-label">
          <input type="checkbox"
            id="ashby-job-remote"
            name="job_remote"
            value="1"
            <?php checked(isset($_GET['job_remote']) ? sanitize_text_field(wp_unslash($_GET['job_remote'])) : '', '1'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?> />
          <?php esc_html_e('Remote jobs only', 'ashby-jobs'); ?>
        </label>
      </div>
    <?php endif; ?>

    <div class="ashby-filter-actions">
      <button type="submit" class="ashby-filter-submit">
        <?php esc_html_e('Filter Jobs', 'ashby-jobs'); ?>
      </button>
      <button type="reset" class="ashby-filter-clear">
        <?php esc_html_e('Clear', 'ashby-jobs'); ?>
      </button>
    </div>

  </form>
<?php
}

/**
 * Display jobs list with optional filtering
 *
 * @param array $args Display arguments
 */
function ashby_display_jobs_list($args = array())
{
  $defaults = array(
    'limit' => 0,
    'department' => '',
    'location' => '',
    'employment_type' => '',
    'remote' => null,
    'search' => '',
    'show_filters' => false,
    'show_description' => true,
    'show_compensation' => get_option('ashby_jobs_include_compensation', false),
    'css_class' => 'ashby-jobs-list',
    'no_jobs_message' => __('No jobs found.', 'ashby-jobs')
  );

  $args = wp_parse_args($args, $defaults);

  // Build filters array
  $filters = array();
  if (!empty($args['department'])) {
    $filters['department'] = $args['department'];
  }
  if (!empty($args['location'])) {
    $filters['location'] = $args['location'];
  }
  if (!empty($args['employment_type'])) {
    $filters['employment_type'] = $args['employment_type'];
  }
  if ($args['remote'] !== null) {
    $filters['remote'] = $args['remote'];
  }
  if (!empty($args['search'])) {
    $filters['search'] = $args['search'];
  }

  // Get jobs
  $jobs = ashby_get_jobs($filters);

  if (is_wp_error($jobs)) {
    echo '<div class="ashby-error">';
    /* translators: %s: error message */
    echo '<p>' . sprintf(__('Error loading jobs: %s', 'ashby-jobs'), esc_html($jobs->get_error_message())) . '</p>';
    echo '</div>';
    return;
  }

  // Apply limit
  if ($args['limit'] > 0 && count($jobs) > $args['limit']) {
    $jobs = array_slice($jobs, 0, $args['limit']);
  }

  // Display filters if requested
  if ($args['show_filters']) {
    ashby_display_filters();
  }

?>
  <div class="<?php echo esc_attr($args['css_class']); ?>">
    <?php if (empty($jobs)): ?>
      <div class="ashby-no-jobs">
        <p><?php echo esc_html($args['no_jobs_message']); ?></p>
      </div>
    <?php else: ?>
      <?php foreach ($jobs as $job): ?>
        <?php ashby_display_job($job, $args); ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
<?php
}

/**
 * Clear Ashby jobs cache
 */
function ashby_clear_cache()
{
  $api = new AshbyJobsAPI();
  $api->clear_cache();
}

/**
 * Get cache expiration time
 *
 * @return int|false Expiration timestamp or false if not cached
 */
function ashby_get_cache_expiration()
{
  $api = new AshbyJobsAPI();
  return $api->get_cache_expiration();
}

/**
 * Hook for theme developers to modify job data before display
 */
function ashby_format_job_for_display($job)
{
  /**
   * Filter job data before display
   *
   * @param array $job Job data
   * @return array Modified job data
   */
  return apply_filters('ashby_jobs_format_job', $job);
}

/**
 * Get plugin version
 *
 * @return string Plugin version
 */
function ashby_get_plugin_version()
{
  return ASHBY_JOBS_VERSION;
}
