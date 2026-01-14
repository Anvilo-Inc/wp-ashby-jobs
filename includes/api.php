<?php

/**
 * Ashby Jobs API Handler
 *
 * @package AshbyJobs
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
  exit;
}

/**
 * Handles all communication with the Ashby ATS API.
 *
 * Provides methods for fetching, filtering, and caching job postings
 * from the Ashby public API.
 *
 * @since 1.0.0
 */
final class AshbyJobsAPI
{
  public const string API_BASE_URL = 'https://api.ashbyhq.com/posting-api/job-board';
  public const string CACHE_KEY = 'ashby_jobs_data';

  private readonly string $client_name;
  private readonly int $cache_duration;
  private readonly bool $include_compensation;

  public function __construct()
  {
    $this->client_name = get_option('ashby_jobs_client_name', '');
    $this->cache_duration = (int) get_option('ashby_jobs_cache_duration', 86400);
    $this->include_compensation = (bool) get_option('ashby_jobs_include_compensation', false);
  }

  /**
   * Fetch jobs from Ashby API
   *
   * @return array<string, mixed>|\WP_Error Jobs data or error
   */
  public function fetch_jobs(): array|\WP_Error
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
   * @param array<string, mixed> $job Raw job data
   * @return array<string, mixed> Formatted job data
   */
  public function format_job(array $job): array
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

  private function format_employment_type(string $type): string
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
   * @param array<int, array<string, mixed>> $jobs
   * @return array<int, string>
   */
  public function get_departments(array $jobs): array
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
   * @param array<int, array<string, mixed>> $jobs
   * @return array<int, string>
   */
  public function get_locations(array $jobs): array
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
   * @param array<int, array<string, mixed>> $jobs
   * @return array<int, string>
   */
  public function get_employment_types(array $jobs): array
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
   * @param array<int, array<string, mixed>> $jobs
   * @param array<string, mixed> $filters
   * @return array<int, array<string, mixed>>
   */
  public function filter_jobs(array $jobs, array $filters = []): array
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

  public function has_cache(): bool
  {
    $cached_data = get_transient(self::CACHE_KEY);
    $cache_timestamp = get_transient(self::CACHE_KEY . '_timestamp');

    return ($cached_data !== false && $cache_timestamp !== false);
  }

  public function get_cache_timestamp(): int|false
  {
    return get_transient(self::CACHE_KEY . '_timestamp');
  }

  public function get_cache_expiration(): int|false
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

  public function clear_cache(): void
  {
    delete_transient(self::CACHE_KEY);
    delete_transient(self::CACHE_KEY . '_timestamp');
  }
}
