<?php
/**
 * Plugin Name: Trivia Night Finder
 * Plugin URI: https://yoursite.com/trivia-night-finder
 * Description: Find trivia nights with an interactive map and filterable list
 * Version: 1.0.2
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
        add_shortcode('trivia_finder', array($this, 'render_shortcode'));
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
                '1.0.2'
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
                '1.0.2',
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
        $atts = shortcode_atts(array(
            'title' => 'Find A Trivia Night',
            'csv_url' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vT-Q4OkIVSk-6mCopFiKKluz8VkSQQ1HSHxw4dIitH7V-sFjovlk14MaLKmU3de68Lki8I60d8FxpMa/pub?output=csv',
            'height' => '100vh',
            'google_maps_key' => 'AIzaSyCKAHTz4KvsPHmiQpK-5ew5eq17VO7bOxM'
        ), $atts, 'trivia_finder');
        
        // Store API key for enqueue
        $this->google_maps_api_key = $atts['google_maps_key'];
        
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
