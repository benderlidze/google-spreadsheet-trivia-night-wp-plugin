<?php
/**
 * Plugin Name: Trivia Night Finder - Elementor Widget
 * Description: A native Elementor widget for map-based trivia night finder
 * Version: 2.0.1
 * Author: Your Name
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Elementor tested up to: 3.25.0
 * Elementor Pro tested up to: 3.25.0
 */

if (!defined('ABSPATH')) exit;

final class Trivia_Finder_Elementor {

    const VERSION = '2.0.1';
    const MINIMUM_ELEMENTOR_VERSION = '3.0.0';
    const MINIMUM_PHP_VERSION = '7.4';

    private static $_instance = null;

    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'settings_init']);
    }

    public function init() {
        // Check if Elementor is installed and activated
        if (!did_action('elementor/loaded')) {
            add_action('admin_notices', [$this, 'admin_notice_missing_elementor']);
            return;
        }

        // Check for required Elementor version
        if (!version_compare(ELEMENTOR_VERSION, self::MINIMUM_ELEMENTOR_VERSION, '>=')) {
            add_action('admin_notices', [$this, 'admin_notice_minimum_elementor_version']);
            return;
        }

        // Check for required PHP version
        if (version_compare(PHP_VERSION, self::MINIMUM_PHP_VERSION, '<')) {
            add_action('admin_notices', [$this, 'admin_notice_minimum_php_version']);
            return;
        }

        // Register widget
        add_action('elementor/widgets/register', [$this, 'register_widgets']);

        // Register widget scripts
        add_action('elementor/frontend/after_register_scripts', [$this, 'register_frontend_scripts']);
        add_action('elementor/frontend/after_register_styles', [$this, 'register_frontend_styles']);

        // Register widget category
        add_action('elementor/elements/categories_registered', [$this, 'add_widget_category']);
    }

    public function admin_notice_missing_elementor() {
        $message = sprintf(
            esc_html__('"%1$s" requires "%2$s" to be installed and activated.', 'trivia-finder-elementor'),
            '<strong>' . esc_html__('Trivia Night Finder', 'trivia-finder-elementor') . '</strong>',
            '<strong>' . esc_html__('Elementor', 'trivia-finder-elementor') . '</strong>'
        );
        printf('<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message);
    }

    public function admin_notice_minimum_elementor_version() {
        $message = sprintf(
            esc_html__('"%1$s" requires "%2$s" version %3$s or greater.', 'trivia-finder-elementor'),
            '<strong>' . esc_html__('Trivia Night Finder', 'trivia-finder-elementor') . '</strong>',
            '<strong>' . esc_html__('Elementor', 'trivia-finder-elementor') . '</strong>',
            self::MINIMUM_ELEMENTOR_VERSION
        );
        printf('<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message);
    }

    public function admin_notice_minimum_php_version() {
        $message = sprintf(
            esc_html__('"%1$s" requires "%2$s" version %3$s or greater.', 'trivia-finder-elementor'),
            '<strong>' . esc_html__('Trivia Night Finder', 'trivia-finder-elementor') . '</strong>',
            '<strong>' . esc_html__('PHP', 'trivia-finder-elementor') . '</strong>',
            self::MINIMUM_PHP_VERSION
        );
        printf('<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message);
    }

    public function add_widget_category($elements_manager) {
        $elements_manager->add_category(
            'trivia-finder',
            [
                'title' => esc_html__('Trivia Finder', 'trivia-finder-elementor'),
                'icon' => 'fa fa-map-marker',
            ]
        );
    }

    public function register_widgets($widgets_manager) {
        require_once(__DIR__ . '/widgets/trivia-finder-widget.php');
        $widgets_manager->register(new \Elementor\Trivia_Finder_Widget());
    }

    public function register_frontend_scripts() {
        // D3.js for CSV
        wp_register_script(
            'd3-csv',
            'https://cdn.jsdelivr.net/npm/d3@7',
            [],
            '7.0.0',
            true
        );

        // Custom JavaScript
        wp_register_script(
            'trivia-finder-script',
            plugins_url('/assets/js/trivia-finder.js', __FILE__),
            ['d3-csv'],
            self::VERSION,
            true
        );

        // Pass defaults to JS
        $default_csv = get_option(
            'trivia_finder_default_csv_url',
            'https://docs.google.com/spreadsheets/d/e/2PACX-1vT-Q4OkIVSk-6mCopFiKKluz8VkSQQ1HSHxw4dIitH7V-sFjovlk14MaLKmU3de68Lki8I60d8FxpMa/pub?output=csv'
        );

        wp_localize_script('trivia-finder-script', 'TriviaFinderDefaults', [
            'csvUrl' => $default_csv,
            'center' => ['lat' => -37.8136, 'lng' => 144.9631],
            'zoom' => 12,
        ]);
    }

    public function register_frontend_styles() {
        wp_register_style(
            'trivia-finder-styles',
            plugins_url('/assets/css/trivia-finder.css', __FILE__),
            [],
            self::VERSION
        );
    }

    // Admin settings page
    public function add_admin_menu() {
        add_options_page(
            'Trivia Finder Settings',
            'Trivia Finder',
            'manage_options',
            'trivia_finder_elementor',
            [$this, 'render_settings_page']
        );
    }

    public function settings_init() {
        register_setting('trivia_finder_elementor', 'trivia_finder_google_maps_key');
        register_setting('trivia_finder_elementor', 'trivia_finder_default_csv_url');

        add_settings_section(
            'trivia_finder_section',
            'Trivia Finder Configuration',
            [$this, 'settings_section_callback'],
            'trivia_finder_elementor'
        );

        add_settings_field(
            'trivia_finder_google_maps_key',
            'Google Maps API Key',
            [$this, 'google_maps_key_callback'],
            'trivia_finder_elementor',
            'trivia_finder_section'
        );

        add_settings_field(
            'trivia_finder_default_csv_url',
            'Default CSV URL',
            [$this, 'default_csv_url_callback'],
            'trivia_finder_elementor',
            'trivia_finder_section'
        );
    }

    public function settings_section_callback() {
        echo 'Configure default settings for the Trivia Finder Elementor widget.';
    }

    public function google_maps_key_callback() {
        $value = get_option('trivia_finder_google_maps_key', '');
        echo '<input type="text" name="trivia_finder_google_maps_key" value="' . esc_attr($value) . '" style="width:400px;" />';
        echo '<p class="description">Your Google Maps API key. <a href="https://console.cloud.google.com/google/maps-apis/" target="_blank">Get one here</a>.</p>';
    }

    public function default_csv_url_callback() {
        $value = get_option('trivia_finder_default_csv_url', '');
        echo '<input type="text" name="trivia_finder_default_csv_url" value="' . esc_attr($value) . '" style="width:400px;" />';
        echo '<p class="description">Default URL for your CSV data source.</p>';
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) return;

        if (isset($_GET['settings-updated'])) {
            add_settings_error('trivia_finder_messages', 'trivia_finder_message', 'Settings Saved', 'updated');
        }

        settings_errors('trivia_finder_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('trivia_finder_elementor');
                do_settings_sections('trivia_finder_elementor');
                submit_button('Save Settings');
                ?>
            </form>
            <hr>
            <h2>Usage</h2>
            <p>The Trivia Finder widget is now available in Elementor under the "Trivia Finder" category.</p>
            <ol>
                <li>Edit any page with Elementor</li>
                <li>Search for "Trivia Finder" in the widget panel</li>
                <li>Drag and drop the widget onto your page</li>
                <li>Configure the settings in the left panel</li>
            </ol>
        </div>
        <?php
    }
}

Trivia_Finder_Elementor::instance();
