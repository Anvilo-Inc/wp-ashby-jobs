/**
 * Ashby Jobs Frontend JavaScript
 * Handles filtering, AJAX requests, and user interactions
 */

class AshbyJobs {
  constructor(containerId, initialFilters = {}) {
    this.containerId = containerId;
    this.container = document.getElementById(containerId);
    this.initialFilters = initialFilters;
    this.currentPage = 1;
    this.perPage = 10;
    this.isLoading = false;
    this.debounceTimer = null;
    
    if (this.container) {
      this.init();
    }
  }
  
  init() {
    this.bindEvents();
    this.applyInitialFilters();
  }
  
  bindEvents() {
    // Search input
    const searchInput = this.container.querySelector('.ashby-jobs-search');
    if (searchInput) {
      searchInput.addEventListener('input', (e) => {
        this.debounceFilter(() => {
          this.filterJobs();
        }, 500);
      });
    }
    
    // Filter dropdowns
    const filterSelects = this.container.querySelectorAll('.ashby-jobs-filter');
    filterSelects.forEach(select => {
      if (select.type === 'checkbox') {
        select.addEventListener('change', () => {
          this.filterJobs();
        });
      } else {
        select.addEventListener('change', () => {
          this.filterJobs();
        });
      }
    });
    
    // Clear filters button
    const clearButton = this.container.querySelector('#ashby-clear-filters');
    if (clearButton) {
      clearButton.addEventListener('click', () => {
        this.clearFilters();
      });
    }
    
    // Load more button
    const loadMoreButton = this.container.querySelector('.ashby-jobs-load-more');
    if (loadMoreButton) {
      loadMoreButton.addEventListener('click', () => {
        this.loadMoreJobs();
      });
    }
    
    // Refresh data periodically (every 5 minutes)
    setInterval(() => {
      this.refreshJobs();
    }, 5 * 60 * 1000);
  }
  
  applyInitialFilters() {
    if (Object.keys(this.initialFilters).length > 0) {
      // Set filter values from shortcode attributes
      Object.keys(this.initialFilters).forEach(key => {
        const value = this.initialFilters[key];
        if (value) {
          const input = this.container.querySelector(`#ashby-${key}`);
          if (input) {
            if (input.type === 'checkbox') {
              input.checked = value;
            } else {
              input.value = value;
            }
          }
        }
      });
    }
  }
  
  filterJobs() {
    if (this.isLoading) return;
    
    this.isLoading = true;
    this.currentPage = 1;
    
    const filters = this.getFilterValues();
    
    this.showLoading();
    
    this.makeAjaxRequest('ashby_filter_jobs', {
      ...filters,
      page: this.currentPage,
      per_page: this.perPage
    })
    .then(response => {
      if (response.success) {
        this.updateJobsList(response.data.jobs_html);
        this.updateResultsCount(response.data.total_jobs);
        this.updatePagination(response.data);
      } else {
        this.showError(response.data.message || ashbyJobs.error);
      }
    })
    .catch(error => {
      console.error('Filter error:', error);
      this.showError(ashbyJobs.error);
    })
    .finally(() => {
      this.hideLoading();
      this.isLoading = false;
    });
  }
  
  loadMoreJobs() {
    if (this.isLoading) return;
    
    this.isLoading = true;
    this.currentPage++;
    
    const filters = this.getFilterValues();
    
    const loadMoreButton = this.container.querySelector('.ashby-jobs-load-more');
    if (loadMoreButton) {
      loadMoreButton.textContent = ashbyJobs.loading;
      loadMoreButton.disabled = true;
    }
    
    this.makeAjaxRequest('ashby_load_more_jobs', {
      ...filters,
      page: this.currentPage,
      per_page: this.perPage
    })
    .then(response => {
      if (response.success) {
        this.appendJobs(response.data.jobs_html);
        this.updatePagination(response.data);
      } else {
        this.showError(response.data.message || ashbyJobs.error);
        this.currentPage--; // Revert page increment
      }
    })
    .catch(error => {
      console.error('Load more error:', error);
      this.showError(ashbyJobs.error);
      this.currentPage--; // Revert page increment
    })
    .finally(() => {
      if (loadMoreButton) {
        loadMoreButton.textContent = 'Load More Jobs';
        loadMoreButton.disabled = false;
      }
      this.isLoading = false;
    });
  }
  
  refreshJobs() {
    // Silent refresh - update available filter options
    this.makeAjaxRequest('ashby_refresh_jobs', {})
    .then(response => {
      if (response.success) {
        this.updateFilterOptions(response.data);
      }
    })
    .catch(error => {
      console.warn('Refresh error:', error);
    });
  }
  
  clearFilters() {
    // Reset all filter inputs
    const searchInput = this.container.querySelector('.ashby-jobs-search');
    if (searchInput) {
      searchInput.value = '';
    }
    
    const filterSelects = this.container.querySelectorAll('.ashby-jobs-filter');
    filterSelects.forEach(select => {
      if (select.type === 'checkbox') {
        select.checked = false;
      } else {
        select.value = '';
      }
    });
    
    // Trigger filter
    this.filterJobs();
  }
  
  getFilterValues() {
    const searchInput = this.container.querySelector('.ashby-jobs-search');
    const departmentSelect = this.container.querySelector('#ashby-department');
    const locationSelect = this.container.querySelector('#ashby-location');
    const employmentTypeSelect = this.container.querySelector('#ashby-employment-type');
    const remoteCheckbox = this.container.querySelector('#ashby-remote');
    
    const filters = {
      search: searchInput ? searchInput.value.trim() : '',
      department: departmentSelect ? departmentSelect.value : '',
      location: locationSelect ? locationSelect.value : '',
      employment_type: employmentTypeSelect ? employmentTypeSelect.value : ''
    };
    
    // Only include remote filter if checkbox is checked
    if (remoteCheckbox && remoteCheckbox.checked) {
      filters.remote = true;
    }
    
    return filters;
  }
  
  updateJobsList(jobsHtml) {
    const jobsList = this.container.querySelector('.ashby-jobs-list');
    if (jobsList) {
      jobsList.innerHTML = jobsHtml;
      this.animateJobCards();
    }
  }
  
  appendJobs(jobsHtml) {
    const jobsList = this.container.querySelector('.ashby-jobs-list');
    if (jobsList) {
      const tempDiv = document.createElement('div');
      tempDiv.innerHTML = jobsHtml;
      
      // Append each job card individually for better animation
      const newCards = tempDiv.querySelectorAll('.ashby-job-card');
      newCards.forEach((card, index) => {
        // Reset animation delay for new cards
        card.style.animationDelay = `${(index + 1) * 0.1}s`;
        jobsList.appendChild(card);
      });
    }
  }
  
  updateResultsCount(totalJobs) {
    const resultsText = this.container.querySelector('#ashby-results-text');
    if (resultsText) {
      const text = totalJobs === 1 ? 
        `Showing ${totalJobs} job` : 
        `Showing ${totalJobs} jobs`;
      resultsText.textContent = text;
    }
  }
  
  updatePagination(data) {
    const paginationContainer = this.container.querySelector('.ashby-jobs-pagination');
    const loadMoreButton = this.container.querySelector('.ashby-jobs-load-more');
    
    if (paginationContainer && loadMoreButton) {
      if (data.has_more) {
        paginationContainer.style.display = 'block';
      } else {
        paginationContainer.style.display = 'none';
      }
    }
  }
  
  updateFilterOptions(data) {
    // Update department options
    const departmentSelect = this.container.querySelector('#ashby-department');
    if (departmentSelect && data.departments) {
      this.updateSelectOptions(departmentSelect, data.departments, 'All Departments');
    }
    
    // Update location options
    const locationSelect = this.container.querySelector('#ashby-location');
    if (locationSelect && data.locations) {
      this.updateSelectOptions(locationSelect, data.locations, 'All Locations');
    }
    
    // Update employment type options
    const employmentTypeSelect = this.container.querySelector('#ashby-employment-type');
    if (employmentTypeSelect && data.employment_types) {
      this.updateSelectOptions(employmentTypeSelect, data.employment_types, 'All Types');
    }
  }
  
  updateSelectOptions(selectElement, options, defaultText) {
    const currentValue = selectElement.value;
    
    // Clear existing options except the first one
    selectElement.innerHTML = `<option value="">${defaultText}</option>`;
    
    // Add new options
    options.forEach(option => {
      const optionElement = document.createElement('option');
      optionElement.value = option;
      optionElement.textContent = option;
      selectElement.appendChild(optionElement);
    });
    
    // Restore previous value if it still exists
    if (currentValue && options.includes(currentValue)) {
      selectElement.value = currentValue;
    }
  }
  
  showLoading() {
    const loading = this.container.querySelector('.ashby-jobs-loading');
    const jobsList = this.container.querySelector('.ashby-jobs-list');
    
    if (loading) {
      loading.style.display = 'flex';
    }
    if (jobsList) {
      jobsList.style.opacity = '0.5';
    }
  }
  
  hideLoading() {
    const loading = this.container.querySelector('.ashby-jobs-loading');
    const jobsList = this.container.querySelector('.ashby-jobs-list');
    
    if (loading) {
      loading.style.display = 'none';
    }
    if (jobsList) {
      jobsList.style.opacity = '1';
    }
  }
  
  showError(message) {
    const jobsList = this.container.querySelector('.ashby-jobs-list');
    if (jobsList) {
      jobsList.innerHTML = `
        <div class="ashby-jobs-error">
          <p>${message}</p>
          <button onclick="location.reload()" class="ashby-jobs-clear-btn">
            Try Again
          </button>
        </div>
      `;
    }
  }
  
  animateJobCards() {
    // Reset animations for job cards
    const jobCards = this.container.querySelectorAll('.ashby-job-card');
    jobCards.forEach((card, index) => {
      card.style.animationDelay = `${index * 0.1}s`;
    });
  }
  
  debounceFilter(callback, delay) {
    clearTimeout(this.debounceTimer);
    this.debounceTimer = setTimeout(callback, delay);
  }
  
  makeAjaxRequest(action, data) {
    const formData = new FormData();
    formData.append('action', action);
    formData.append('nonce', ashbyJobs.nonce);
    
    // Append data to form
    Object.keys(data).forEach(key => {
      formData.append(key, data[key]);
    });
    
    return fetch(ashbyJobs.ajaxUrl, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    })
    .then(response => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.json();
    });
  }
}

// Auto-initialize for existing containers
document.addEventListener('DOMContentLoaded', function() {
  // Look for job containers that haven't been initialized
  const containers = document.querySelectorAll('.ashby-jobs-container');
  containers.forEach(container => {
    if (!container.hasAttribute('data-ashby-initialized')) {
      new AshbyJobs(container.id);
      container.setAttribute('data-ashby-initialized', 'true');
    }
  });
});

// Make class available globally
window.AshbyJobs = AshbyJobs;