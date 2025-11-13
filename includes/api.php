<?php

/**
 * Ashby Jobs API Handler
 */

// Prevent direct access
if (!defined('ABSPATH')) {
  exit;
}

class AshbyJobsAPI
{

  /**
   * API base URL
   */
  const API_BASE_URL = 'https://api.ashbyhq.com/posting-api/job-board';

  /**
   * Transient key for caching
   */
  const CACHE_KEY = 'ashby_jobs_data';

  /**
   * Client name
   */
  private $client_name;

  /**
   * Cache duration
   */
  private $cache_duration;

  /**
   * Include compensation
   */
  private $include_compensation;

  /**
   * Constructor
   */
  public function __construct()
  {
    $this->client_name = get_option('ashby_jobs_client_name', '');
    $this->cache_duration = get_option('ashby_jobs_cache_duration', 86400); // Default to 1 day
    $this->include_compensation = get_option('ashby_jobs_include_compensation', false);
  }

  /**
   * Fetch jobs from Ashby API
   *
   * @return array|WP_Error Jobs data or error
   */
  public function fetch_jobs()
  {
    // Check cache first
    $cached_data = get_transient(self::CACHE_KEY);
    if ($cached_data !== false) {
      return $cached_data;
    }

    // Build API URL
    $url = self::API_BASE_URL . '/' . $this->client_name;
    if ($this->include_compensation) {
      $url .= '?includeCompensation=true';
    }

    // Make API request
    $response = wp_remote_get($url, array(
      'timeout' => 30,
      'headers' => array(
        'Accept' => 'application/json',
        'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
      )
    ));

    // Check for errors
    if (is_wp_error($response)) {
      if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Ashby Jobs API Error: ' . $response->get_error_message());
      }
      return $response;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
      $error_message = sprintf('API request failed with status %d', $response_code);
      if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Ashby Jobs API Error: ' . $error_message);
      }
      return new WP_Error('api_error', $error_message);
    }

    // Parse response
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      $error_message = 'Invalid JSON response from API';
      if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Ashby Jobs API Error: ' . $error_message);
      }
      return new WP_Error('json_error', $error_message);
    }

    // Filter only listed jobs for public display
    if (isset($data['jobs']) && is_array($data['jobs'])) {
      $data['jobs'] = array_filter($data['jobs'], function ($job) {
        return isset($job['isListed']) && $job['isListed'] === true;
      });

      // Format jobs
      $data['jobs'] = array_map(array($this, 'format_job'), $data['jobs']);
    } else {
      $data['jobs'] = array();
    }

    // Cache the result with timestamp
    set_transient(self::CACHE_KEY, $data, $this->cache_duration);

    // Store cache creation timestamp separately
    set_transient(self::CACHE_KEY . '_timestamp', time(), $this->cache_duration);

    return $data;
  }

  /**
   * Format job data for consistent output
   *
   * @param array $job Raw job data
   * @return array Formatted job data
   */
  public function format_job($job)
  {
    return array(
      'id' => isset($job['id']) ? $job['id'] : wp_generate_uuid4(),
      'title' => isset($job['title']) ? sanitize_text_field($job['title']) : __('Untitled Position', 'ashby-jobs'),
      'department' => isset($job['department']) ? sanitize_text_field($job['department']) : '',
      'team' => isset($job['team']) ? sanitize_text_field($job['team']) : '',
      'location' => isset($job['location']) ? sanitize_text_field($job['location']) : '',
      'is_remote' => isset($job['isRemote']) ? (bool) $job['isRemote'] : false,
      'employment_type' => $this->format_employment_type($job['employmentType'] ?? ''),
      'description_html' => isset($job['descriptionHtml']) ? wp_kses_post($job['descriptionHtml']) : '',
      'description_plain' => isset($job['descriptionPlain']) ? sanitize_textarea_field($job['descriptionPlain']) : '',
      'published_at' => isset($job['publishedAt']) ? $job['publishedAt'] : '',
      'apply_url' => isset($job['applyUrl']) ? esc_url($job['applyUrl']) : '',
      'job_url' => isset($job['jobUrl']) ? esc_url($job['jobUrl']) : '',
      'compensation' => isset($job['compensation']) ? $job['compensation'] : null,
      'secondary_locations' => isset($job['secondaryLocations']) ? $job['secondaryLocations'] : array(),
      'address' => isset($job['address']) ? $job['address'] : null
    );
  }

  /**
   * Format employment type for display
   *
   * @param string $type Raw employment type
   * @return string Formatted employment type
   */
  private function format_employment_type($type)
  {
    $types = array(
      'FullTime' => __('Full-time', 'ashby-jobs'),
      'PartTime' => __('Part-time', 'ashby-jobs'),
      'Contract' => __('Contract', 'ashby-jobs'),
      'Temporary' => __('Temporary', 'ashby-jobs'),
      'Internship' => __('Internship', 'ashby-jobs')
    );

    return isset($types[$type]) ? $types[$type] : sanitize_text_field($type);
  }

  /**
   * Get unique departments from jobs
   *
   * @param array $jobs Jobs array
   * @return array Unique departments
   */
  public function get_departments($jobs)
  {
    $departments = array();

    foreach ($jobs as $job) {
      if (!empty($job['department']) && !in_array($job['department'], $departments)) {
        $departments[] = $job['department'];
      }
    }

    sort($departments);
    return $departments;
  }

  /**
   * Get unique locations from jobs
   *
   * @param array $jobs Jobs array
   * @return array Unique locations
   */
  public function get_locations($jobs)
  {
    $locations = array();

    foreach ($jobs as $job) {
      if (!empty($job['location']) && !in_array($job['location'], $locations)) {
        $locations[] = $job['location'];
      }
    }

    sort($locations);
    return $locations;
  }

  /**
   * Get unique employment types from jobs
   *
   * @param array $jobs Jobs array
   * @return array Unique employment types
   */
  public function get_employment_types($jobs)
  {
    $types = array();

    foreach ($jobs as $job) {
      if (!empty($job['employment_type']) && !in_array($job['employment_type'], $types)) {
        $types[] = $job['employment_type'];
      }
    }

    sort($types);
    return $types;
  }

  /**
   * Filter jobs by criteria
   *
   * @param array $jobs Jobs array
   * @param array $filters Filter criteria
   * @return array Filtered jobs
   */
  public function filter_jobs($jobs, $filters = array())
  {
    if (empty($filters)) {
      return $jobs;
    }

    return array_filter($jobs, function ($job) use ($filters) {
      // Filter by department
      if (
        !empty($filters['department']) &&
        $job['department'] !== $filters['department']
      ) {
        return false;
      }

      // Filter by location
      if (
        !empty($filters['location']) &&
        $job['location'] !== $filters['location']
      ) {
        return false;
      }

      // Filter by employment type
      if (
        !empty($filters['employment_type']) &&
        $job['employment_type'] !== $filters['employment_type']
      ) {
        return false;
      }

      // Filter by remote
      if (
        isset($filters['remote']) &&
        $job['is_remote'] !== (bool) $filters['remote']
      ) {
        return false;
      }

      // Filter by search term
      if (!empty($filters['search'])) {
        $search_term = strtolower($filters['search']);
        $searchable_text = strtolower(
          $job['title'] . ' ' .
            $job['department'] . ' ' .
            $job['team'] . ' ' .
            $job['description_plain']
        );

        if (strpos($searchable_text, $search_term) === false) {
          return false;
        }
      }

      return true;
    });
  }

  /**
   * Check if cache exists (without triggering a fetch)
   *
   * @return bool True if cache exists
   */
  public function has_cache()
  {
    $cached_data = get_transient(self::CACHE_KEY);
    $cache_timestamp = get_transient(self::CACHE_KEY . '_timestamp');

    return ($cached_data !== false && $cache_timestamp !== false);
  }

  /**
   * Get cache creation timestamp
   *
   * @return int|false Cache creation timestamp or false if not cached
   */
  public function get_cache_timestamp()
  {
    return get_transient(self::CACHE_KEY . '_timestamp');
  }

  /**
   * Get cache expiration time
   *
   * @return int|false Expiration timestamp or false if not cached
   */
  public function get_cache_expiration()
  {
    $cached_data = get_transient(self::CACHE_KEY);
    if ($cached_data === false) {
      return false;
    }

    $timestamp = $this->get_cache_timestamp();
    if ($timestamp === false) {
      return false;
    }

    return $timestamp + $this->cache_duration;
  }

  /**
   * Clear cache
   */
  public function clear_cache()
  {
    delete_transient(self::CACHE_KEY);
    delete_transient(self::CACHE_KEY . '_timestamp');
  }
}
