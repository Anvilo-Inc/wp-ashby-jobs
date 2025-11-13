# Ashby Jobs WordPress Plugin

A WordPress plugin that displays job postings from Ashby ATS with filtering and search functionality.

## Features

- Fetches job data from Ashby’s public API  
- Real-time filtering by department, location, employment type, and remote status  
- Search functionality  
- Caching for performance optimization  
- Easy shortcode integration  


---

## Installation

### Method 1: Upload Plugin Files

1. Download or create the plugin files.  
2. Upload the `ashby-jobs` folder to `/wp-content/plugins/`.  
3. Activate the plugin from **Plugins > Installed Plugins**.  
4. Configure settings under **Settings > Ashby Jobs**.

## Configuration

1. Navigate to **Settings > Ashby Jobs**.  
2. Enter your Ashby client name (the identifier in your job board URL).  
3. Adjust display and caching options.  
4. Test the API connection.

### Finding Your Client Name

If your Ashby job board URL is `https://jobs.ashbyhq.com/company_name`, the client name is `company_name`.

---

## Usage

### Basic Shortcode

Display all jobs with default settings:

```
[ashby_jobs]
```

### Shortcode Parameters

| Parameter | Description |
|------------|--------------|
| `limit` | Limit the number of jobs displayed |
| `department` | Filter by department |
| `location` | Filter by location |
| `employment_type` | Filter by employment type |
| `show_filters` | Show or hide filter controls (`true`/`false`) |
| `show_search` | Show or hide search box (`true`/`false`) |
| `layout` | Display layout (`grid` or `list`) |
| `show_compensation` | Show compensation data (`auto`, `true`, or `false`) |

**Examples**

```
[ashby_jobs limit="5"]
[ashby_jobs department="Engineering" show_filters="false"]
[ashby_jobs layout="list" show_compensation="true"]
```

---

## Settings Overview

### API Configuration

- **Ashby Client Name** – Identifier from your Ashby job board URL  
- **Cache Duration** – How long API data should be cached (recommended: 86400 seconds)  
- **Include Compensation** – Whether to include salary information

### Display Options

- **Enable Job Filters** – Allow filtering by department, location, type, and remote status

---

## Caching

- Uses WordPress transients to store API responses.  
- Default duration: day.  
- Cache clears automatically when settings are updated or manually from admin.  

---

## Troubleshooting

**No jobs displaying**

1. Confirm the Ashby client name is correct.  
2. Test the API connection.  
3. Clear cache and retry.  
4. Check browser console for errors.

**Styling issues**

1. Confirm your theme supports the required CSS variables.  
2. Look for CSS conflicts.  
3. Ensure all plugin assets load correctly.

**Filters not working**

1. Make sure JavaScript is enabled.  
2. Verify AJAX requests succeed (browser network tab).  
3. Clear cache and reload.

---

## API Rate Limiting

The Ashby API has rate limits.  
This plugin minimizes requests by caching data for a day and only using public job postings.

---

## Customization

### Custom CSS

Add your own styles in your theme:

```css
.ashby-jobs-container {
  /* Custom styling */
}
```

### Hooks and Filters

```php
// Modify job data before display
add_filter('ashby_jobs_format_job', 'my_custom_job_formatter');

// Add custom content inside job card
add_action('ashby_jobs_after_job_content', 'my_custom_job_content');
```

---

## License

Licensed under the GNU General Public License v2 or later.
