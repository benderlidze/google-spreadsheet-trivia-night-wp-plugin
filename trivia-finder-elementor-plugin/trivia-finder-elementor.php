<?php
/**
 * Plugin Name: Trivia Night Finder for Elementor
 * Description: Elementor widget for displaying trivia nights from a Google Sheets CSV on a Google Map with filters.
 * Version: 1.6.0
 * Author: Your Name
 * Text Domain: trivia-finder-elementor
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Trivia_Finder_Elementor_Plugin {

    const VERSION = '1.5.0';
    const MINIMUM_ELEMENTOR_VERSION = '3.0.0';
    const MINIMUM_PHP_VERSION = '7.4';

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function init() {
        if (!did_action('elementor/loaded')) {
            add_action('admin_notices', [$this, 'admin_notice_missing_elementor']);
            return;
        }

        if (version_compare(ELEMENTOR_VERSION, self::MINIMUM_ELEMENTOR_VERSION, '<')) {
            add_action('admin_notices', [$this, 'admin_notice_minimum_elementor_version']);
            return;
        }

        if (version_compare(PHP_VERSION, self::MINIMUM_PHP_VERSION, '<')) {
            add_action('admin_notices', [$this, 'admin_notice_minimum_php_version']);
            return;
        }

        add_action('elementor/widgets/register', [$this, 'register_widgets']);
        add_action('elementor/frontend/after_register_scripts', [$this, 'register_frontend_scripts']);
        add_action('elementor/frontend/after_register_styles', [$this, 'register_frontend_styles']);
        add_action('elementor/elements/categories_registered', [$this, 'add_widget_category']);
    }

    public function register_widgets($widgets_manager) {
        require_once __DIR__ . '/widgets/trivia-finder-widget.php';
        $widgets_manager->register(new \Trivia_Finder_Elementor_Widget());
    }

    public function register_frontend_scripts() {
        wp_register_script(
            'd3',
            'https://d3js.org/d3.v7.min.js',
            [],
            '7.9.0',
            true
        );

        wp_register_script(
            'trivia-finder-elementor',
            plugins_url('assets/js/trivia-finder.js', __FILE__),
            ['d3'],
            self::VERSION,
            true
        );

        wp_localize_script('trivia-finder-elementor', 'TriviaFinderDefaults', [
            'googleMapsApiKey' => trim((string) get_option('trivia_finder_google_maps_key', '')),
            'csvUrl'           => esc_url_raw((string) get_option('trivia_finder_default_csv_url', '')),
            'center'           => [
                'lat' => (float) get_option('trivia_finder_default_center_lat', -37.8136),
                'lng' => (float) get_option('trivia_finder_default_center_lng', 144.9631),
            ],
            'zoom'             => (int) get_option('trivia_finder_default_zoom', 12),
        ]);
    }

    public function register_frontend_styles() {
        wp_register_style(
            'trivia-finder-elementor',
            plugins_url('assets/css/trivia-finder.css', __FILE__),
            [],
            self::VERSION
        );
    }

    public function add_widget_category($elements_manager) {
        $elements_manager->add_category(
            'trivia-finder',
            [
                'title' => esc_html__('Trivia Finder', 'trivia-finder-elementor'),
                'icon'  => 'fa fa-map-marker-alt',
            ]
        );
    }

    public function admin_notice_missing_elementor() {
        $message = sprintf(
            esc_html__('"%1$s" requires "%2$s" to be installed and activated.', 'trivia-finder-elementor'),
            esc_html__('Trivia Night Finder', 'trivia-finder-elementor'),
            esc_html__('Elementor', 'trivia-finder-elementor')
        );

        printf('<div class="notice notice-warning is-dismissible"><p>%s</p></div>', esc_html($message));
    }

    public function admin_notice_minimum_elementor_version() {
        $message = sprintf(
            esc_html__('"%1$s" requires "%2$s" version %3$s or greater.', 'trivia-finder-elementor'),
            esc_html__('Trivia Night Finder', 'trivia-finder-elementor'),
            esc_html__('Elementor', 'trivia-finder-elementor'),
            self::MINIMUM_ELEMENTOR_VERSION
        );

        printf('<div class="notice notice-warning is-dismissible"><p>%s</p></div>', esc_html($message));
    }

    public function admin_notice_minimum_php_version() {
        $message = sprintf(
            esc_html__('"%1$s" requires "%2$s" version %3$s or greater.', 'trivia-finder-elementor'),
            esc_html__('Trivia Night Finder', 'trivia-finder-elementor'),
            esc_html__('PHP', 'trivia-finder-elementor'),
            self::MINIMUM_PHP_VERSION
        );

        printf('<div class="notice notice-warning is-dismissible"><p>%s</p></div>', esc_html($message));
    }

    public function add_settings_page() {
        add_options_page(
            esc_html__('Trivia Finder Settings', 'trivia-finder-elementor'),
            esc_html__('Trivia Finder', 'trivia-finder-elementor'),
            'manage_options',
            'trivia-finder-elementor-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting(
            'trivia_finder_settings_group',
            'trivia_finder_google_maps_key',
            [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            ]
        );

        register_setting(
            'trivia_finder_settings_group',
            'trivia_finder_default_csv_url',
            [
                'type'              => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default'           => '',
            ]
        );

        register_setting(
            'trivia_finder_settings_group',
            'trivia_finder_default_center_lat',
            [
                'type'              => 'number',
                'sanitize_callback' => [$this, 'sanitize_float'],
                'default'           => -37.8136,
            ]
        );

        register_setting(
            'trivia_finder_settings_group',
            'trivia_finder_default_center_lng',
            [
                'type'              => 'number',
                'sanitize_callback' => [$this, 'sanitize_float'],
                'default'           => 144.9631,
            ]
        );

        register_setting(
            'trivia_finder_settings_group',
            'trivia_finder_default_zoom',
            [
                'type'              => 'integer',
                'sanitize_callback' => [$this, 'sanitize_zoom'],
                'default'           => 12,
            ]
        );

        register_setting(
            'trivia_finder_settings_group',
            'trivia_finder_default_mobile_map_height',
            [
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'default'           => 50,
            ]
        );

        add_settings_section(
            'trivia_finder_main_section',
            esc_html__('General Settings', 'trivia-finder-elementor'),
            [$this, 'settings_section_callback'],
            'trivia-finder-elementor-settings'
        );

        add_settings_field(
            'trivia_finder_google_maps_key',
            esc_html__('Google Maps API Key', 'trivia-finder-elementor'),
            [$this, 'google_maps_key_callback'],
            'trivia-finder-elementor-settings',
            'trivia_finder_main_section'
        );

        add_settings_field(
            'trivia_finder_default_csv_url',
            esc_html__('Default CSV URL', 'trivia-finder-elementor'),
            [$this, 'default_csv_url_callback'],
            'trivia-finder-elementor-settings',
            'trivia_finder_main_section'
        );

        add_settings_field(
            'trivia_finder_default_center_lat',
            esc_html__('Default Center Latitude', 'trivia-finder-elementor'),
            [$this, 'default_center_lat_callback'],
            'trivia-finder-elementor-settings',
            'trivia_finder_main_section'
        );

        add_settings_field(
            'trivia_finder_default_center_lng',
            esc_html__('Default Center Longitude', 'trivia-finder-elementor'),
            [$this, 'default_center_lng_callback'],
            'trivia-finder-elementor-settings',
            'trivia_finder_main_section'
        );

        add_settings_field(
            'trivia_finder_default_zoom',
            esc_html__('Default Zoom', 'trivia-finder-elementor'),
            [$this, 'default_zoom_callback'],
            'trivia-finder-elementor-settings',
            'trivia_finder_main_section'
        );

        add_settings_field(
            'trivia_finder_default_mobile_map_height',
            esc_html__('Default Mobile Map Height (%)', 'trivia-finder-elementor'),
            [$this, 'default_mobile_map_height_callback'],
            'trivia-finder-elementor-settings',
            'trivia_finder_main_section'
        );
    }

    public function sanitize_float($value) {
        return is_numeric($value) ? (float) $value : 0;
    }

    public function sanitize_zoom($value) {
        $zoom = absint($value);
        if ($zoom < 1) {
            $zoom = 1;
        }
        if ($zoom > 20) {
            $zoom = 20;
        }
        return $zoom;
    }

    public function settings_section_callback() {
        echo '<p>' . esc_html__('Configure the default Google Maps and CSV settings used by the Trivia Finder Elementor widget.', 'trivia-finder-elementor') . '</p>';
    }

    public function google_maps_key_callback() {
        $value = get_option('trivia_finder_google_maps_key', '');
        ?>
        <input
            type="text"
            name="trivia_finder_google_maps_key"
            value="<?php echo esc_attr($value); ?>"
            class="regular-text"
            autocomplete="off"
        />
        <p class="description">
            <?php esc_html_e('Enter your Google Maps JavaScript API key.', 'trivia-finder-elementor'); ?>
        </p>
        <?php
    }

    public function default_csv_url_callback() {
        $value = get_option('trivia_finder_default_csv_url', '');
        ?>
        <input
            type="url"
            name="trivia_finder_default_csv_url"
            value="<?php echo esc_attr($value); ?>"
            class="regular-text"
            placeholder="https://docs.google.com/spreadsheets/d/e/your-sheet/pub?output=csv"
        />
        <p class="description">
            <?php esc_html_e('Default CSV URL for your trivia data source.', 'trivia-finder-elementor'); ?>
        </p>
        <?php
    }

    public function default_center_lat_callback() {
        $value = get_option('trivia_finder_default_center_lat', -37.8136);
        ?>
        <input
            type="number"
            step="0.000001"
            name="trivia_finder_default_center_lat"
            value="<?php echo esc_attr($value); ?>"
        />
        <?php
    }

    public function default_center_lng_callback() {
        $value = get_option('trivia_finder_default_center_lng', 144.9631);
        ?>
        <input
            type="number"
            step="0.000001"
            name="trivia_finder_default_center_lng"
            value="<?php echo esc_attr($value); ?>"
        />
        <?php
    }

    public function default_zoom_callback() {
        $value = get_option('trivia_finder_default_zoom', 12);
        ?>
        <input
            type="number"
            min="1"
            max="20"
            name="trivia_finder_default_zoom"
            value="<?php echo esc_attr($value); ?>"
        />
        <?php
    }

    public function default_mobile_map_height_callback() {
        $value = get_option('trivia_finder_default_mobile_map_height', 50);
        ?>
        <input
            type="number"
            min="10"
            max="90"
            name="trivia_finder_default_mobile_map_height"
            value="<?php echo esc_attr($value); ?>"
        />
        <p class="description">
            <?php esc_html_e('The percentage of container height the map should take on mobile devices (e.g. 50).', 'trivia-finder-elementor'); ?>
        </p>
        <?php
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'trivia_finder_messages',
                'trivia_finder_message',
                esc_html__('Settings saved.', 'trivia-finder-elementor'),
                'updated'
            );
        }

        settings_errors('trivia_finder_messages');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Trivia Finder Settings', 'trivia-finder-elementor'); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('trivia_finder_settings_group');
                do_settings_sections('trivia-finder-elementor-settings');
                submit_button();
                ?>
            </form>

            <hr>

            <p>
                <?php esc_html_e('The widget is available in Elementor under the "Trivia Finder" category.', 'trivia-finder-elementor'); ?>
            </p>
        </div>
        <?php
    }
}

Trivia_Finder_Elementor_Plugin::instance();
