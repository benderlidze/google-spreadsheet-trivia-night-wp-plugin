<?php
/**
 * Plugin Name: Trivia Night Finder
 * Plugin URI: https://yoursite.com/trivia-night-finder
 * Description: Find trivia nights with an interactive map and filterable list
 * Version: 1.2.0
 * Author: Your Name
 * Author URI: https://yoursite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: trivia-night-finder
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Trivia_Night_Finder {
    
    private static $instance = null;
    private $google_maps_api_key = '';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_shortcode('trivia_finder', array($this, 'render_shortcode'));
    }
    
    /**
     * Add settings page to WordPress admin
     */
    public function add_settings_page() {
        add_options_page(
            'Trivia Finder Settings',
            'Trivia Finder',
            'manage_options',
            'trivia-finder-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('trivia_finder_settings', 'trivia_finder_google_maps_key');
        register_setting('trivia_finder_settings', 'trivia_finder_default_csv_url');
        register_setting('trivia_finder_settings', 'trivia_finder_default_height');
        register_setting('trivia_finder_settings', 'trivia_finder_default_title');
        
        add_settings_section(
            'trivia_finder_main_section',
            'Main Settings',
            array($this, 'settings_section_callback'),
            'trivia-finder-settings'
        );
        
        add_settings_field(
            'trivia_finder_google_maps_key',
            'Google Maps API Key',
            array($this, 'google_maps_key_callback'),
            'trivia-finder-settings',
            'trivia_finder_main_section'
        );
        
        add_settings_field(
            'trivia_finder_default_csv_url',
            'Default CSV URL',
            array($this, 'default_csv_url_callback'),
            'trivia-finder-settings',
            'trivia_finder_main_section'
        );
        
        add_settings_field(
            'trivia_finder_default_height',
            'Default Height',
            array($this, 'default_height_callback'),
            'trivia-finder-settings',
            'trivia_finder_main_section'
        );
        
        add_settings_field(
            'trivia_finder_default_title',
            'Default Title',
            array($this, 'default_title_callback'),
            'trivia-finder-settings',
            'trivia_finder_main_section'
        );
    }
    
    public function settings_section_callback() {
        echo '<p>Configure default settings for the Trivia Finder plugin. These can be overridden in individual shortcodes.</p>';
    }
    
    public function google_maps_key_callback() {
        $value = get_option('trivia_finder_google_maps_key', '');
        echo '<input type="text" name="trivia_finder_google_maps_key" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Your Google Maps API key. Get one at <a href="https://console.cloud.google.com/google/maps-apis" target="_blank">Google Cloud Console</a>.</p>';
    }
    
    public function default_csv_url_callback() {
        $value = get_option('trivia_finder_default_csv_url', 'https://docs.google.com/spreadsheets/d/e/2PACX-1vT-Q4OkIVSk-6mCopFiKKluz8VkSQQ1HSHxw4dIitH7V-sFjovlk14MaLKmU3de68Lki8I60d8FxpMa/pub?output=csv');
        echo '<input type="url" name="trivia_finder_default_csv_url" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Default URL for your CSV data source.</p>';
    }
    
    public function default_height_callback() {
        $value = get_option('trivia_finder_default_height', '100vh');
        echo '<input type="text" name="trivia_finder_default_height" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Default container height (e.g., 100vh, 800px, 90vh).</p>';
    }
    
    public function default_title_callback() {
        $value = get_option('trivia_finder_default_title', 'Find A Trivia Night');
        echo '<input type="text" name="trivia_finder_default_title" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Default title displayed at the top of the finder.</p>';
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (isset($_GET['settings-updated'])) {
            add_settings_error('trivia_finder_messages', 'trivia_finder_message', 'Settings Saved', 'updated');
        }
        
        settings_errors('trivia_finder_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('trivia_finder_settings');
                do_settings_sections('trivia-finder-settings');
                submit_button('Save Settings');
                ?>
            </form>
            
            <hr>
            
            <h2>Shortcode Usage</h2>
            <p>Use the shortcode <code>[trivia_finder]</code> in any page or post to display the trivia finder.</p>
            
            <h3>Basic Usage (uses settings above):</h3>
            <pre><code>[trivia_finder]</code></pre>
            
            <h3>Override Individual Settings:</h3>
            <pre><code>[trivia_finder title="Melbourne Trivia Nights"]</code></pre>
            <pre><code>[trivia_finder height="800px"]</code></pre>
            <pre><code>[trivia_finder csv_url="https://your-custom-url.com/data.csv"]</code></pre>
            
            <h3>Available Parameters:</h3>
            <ul>
                <li><strong>title</strong> - Override the title</li>
                <li><strong>height</strong> - Override the container height</li>
                <li><strong>csv_url</strong> - Override the CSV data source</li>
                <li><strong>google_maps_key</strong> - Override the Google Maps API key (not recommended)</li>
            </ul>
        </div>
        <?php
    }
    
    public function enqueue_assets() {
        // Only enqueue if shortcode is present
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'trivia_finder')) {
            // Enqueue CSS
            wp_enqueue_style(
                'trivia-finder-css',
                plugin_dir_url(__FILE__) . 'assets/css/trivia-finder.css',
                array(),
                '1.2.0'
            );
            
            // Enqueue D3.js
            wp_enqueue_script(
                'd3js',
                'https://d3js.org/d3.v7.min.js',
                array(),
                '7.0.0',
                true
            );
            
            // Enqueue main JS BEFORE Google Maps
            wp_enqueue_script(
                'trivia-finder-js',
                plugin_dir_url(__FILE__) . 'assets/js/trivia-finder.js',
                array('d3js'),
                '1.2.0',
                true
            );
            
            // Enqueue Google Maps with API key
            if (!empty($this->google_maps_api_key)) {
                wp_enqueue_script(
                    'google-maps',
                    'https://maps.googleapis.com/maps/api/js?key=' . esc_attr($this->google_maps_api_key) . '&callback=triviaFinderInitMap',
                    array('trivia-finder-js'),
                    null,
                    true
                );
            }
        }
    }
    
    public function render_shortcode($atts) {
        // Get saved settings
        $saved_google_maps_key = get_option('trivia_finder_google_maps_key', '');
        $saved_csv_url = get_option('trivia_finder_default_csv_url', 'https://docs.google.com/spreadsheets/d/e/2PACX-1vT-Q4OkIVSk-6mCopFiKKluz8VkSQQ1HSHxw4dIitH7V-sFjovlk14MaLKmU3de68Lki8I60d8FxpMa/pub?output=csv');
        $saved_height = get_option('trivia_finder_default_height', '100vh');
        $saved_title = get_option('trivia_finder_default_title', 'Find A Trivia Night');
        
        // Merge with shortcode attributes (shortcode overrides settings)
        $atts = shortcode_atts(array(
            'title' => $saved_title,
            'csv_url' => $saved_csv_url,
            'height' => $saved_height,
            'google_maps_key' => $saved_google_maps_key
        ), $atts, 'trivia_finder');
        
        // Store API key for enqueue
        $this->google_maps_api_key = $atts['google_maps_key'];
        
        // Show error if no API key is set
        if (empty($this->google_maps_api_key)) {
            if (current_user_can('manage_options')) {
                return '<div class="notice notice-error"><p><strong>Trivia Finder:</strong> Google Maps API key is not configured. Please set it in <a href="' . admin_url('options-general.php?page=trivia-finder-settings') . '">Settings → Trivia Finder</a>.</p></div>';
            }
            return '<div class="notice notice-error"><p>Map configuration error. Please contact the site administrator.</p></div>';
        }
        
        // Generate unique ID for this instance
        $instance_id = 'trivia-finder-' . uniqid();
        
        ob_start();
        ?>
        <style>
            #<?php echo esc_attr($instance_id); ?> {
                height: <?php echo esc_attr($atts['height']); ?>;
            }
        </style>
        
        <div class="trivia-finder-container" id="<?php echo esc_attr($instance_id); ?>">
            <h1 class="trivia-finder-title"><?php echo esc_html($atts['title']); ?></h1>
            <div class="trivia-filters">
                <div class="trivia-filter-group">
                    <select id="triviaFinderDayFilter">
                        <option value="All">All Days</option>
                    </select>
                </div>
                <div class="trivia-filter-group">
                    <select id="triviaFinderLocationFilter">
                        <option value="All">All Locations</option>
                    </select>
                </div>
            </div>

            <div class="trivia-content-wrapper">
                <div class="trivia-map-container">
                    <div id="triviaFinderMap"></div>
                    <div class="trivia-loading" id="triviaFinderLoadingIndicator">Loading trivia nights...</div>
                </div>

                <div class="trivia-list-container">
                    <div class="trivia-venue-list" id="triviaFinderVenueList"></div>
                </div>
            </div>
        </div>
        
        <script>
            var triviaFinderCsvUrl = <?php echo json_encode($atts['csv_url']); ?>;
        </script>
        <?php
        return ob_get_clean();
    }
}

// Initialize the plugin
Trivia_Night_Finder::get_instance();
