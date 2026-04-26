<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

class Trivia_Finder_Elementor_Widget extends Widget_Base {

    public function get_name() {
        return 'trivia_finder';
    }

    public function get_title() {
        return esc_html__('Trivia Night Finder', 'trivia-finder-elementor');
    }

    public function get_icon() {
        return 'eicon-google-maps';
    }

    public function get_categories() {
        return ['trivia-finder'];
    }

    public function get_keywords() {
        return ['trivia', 'map', 'venues', 'elementor', 'finder'];
    }

    public function get_style_depends() {
        return ['trivia-finder-elementor'];
    }

    public function get_script_depends() {
        return ['d3', 'trivia-finder-elementor'];
    }

    protected function register_controls() {

        $this->start_controls_section(
            'content_section',
            [
                'label' => esc_html__('Content', 'trivia-finder-elementor'),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'csv_url',
            [
                'label'         => esc_html__('CSV URL', 'trivia-finder-elementor'),
                'type'          => Controls_Manager::URL,
                'placeholder'   => esc_html__('https://your-csv-url.com/data.csv', 'trivia-finder-elementor'),
                'description'   => esc_html__('Leave empty to use the plugin default CSV URL.', 'trivia-finder-elementor'),
                'show_external' => false,
            ]
        );

        $this->add_control(
            'map_height',
            [
                'label'      => esc_html__('Container Height', 'trivia-finder-elementor'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'vh'],
                'range'      => [
                    'px' => [
                        'min'  => 300,
                        'max'  => 1200,
                        'step' => 10,
                    ],
                    'vh' => [
                        'min'  => 30,
                        'max'  => 100,
                        'step' => 1,
                    ],
                ],
                'default'    => [
                    'unit' => 'vh',
                    'size' => 90,
                ],
                'selectors'  => [
                    '{{WRAPPER}} .trivia-finder-container' => 'height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'mobile_layout_section',
            [
                'label' => esc_html__('Mobile Layout', 'trivia-finder-elementor'),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_responsive_control(
            'map_container_height',
            [
                'label'      => esc_html__('Map Height (%)', 'trivia-finder-elementor'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['%', 'px', 'vh'],
                'range'      => [
                    '%' => [
                        'min'  => 10,
                        'max'  => 90,
                        'step' => 1,
                    ],
                    'px' => [
                        'min'  => 100,
                        'max'  => 800,
                        'step' => 10,
                    ],
                    'vh' => [
                        'min'  => 10,
                        'max'  => 100,
                        'step' => 1,
                    ],
                ],
                'selectors'  => [
                    '{{WRAPPER}} .trivia-map-container' => 'height: {{SIZE}}{{UNIT}}; flex: none; min-height: 0;',
                    '{{WRAPPER}} .trivia-list-container' => 'flex: 1; min-height: 0;',
                ],
                'description' => esc_html__('Set the height of the map relative to the container on mobile/tablet. If empty, the global plugin setting or default 50% will be used.', 'trivia-finder-elementor'),
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'filter_style_section',
            [
                'label' => esc_html__('Filters', 'trivia-finder-elementor'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'filter_background',
            [
                'label'     => esc_html__('Background Color', 'trivia-finder-elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .trivia-filter-group select' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'filter_border_color',
            [
                'label'     => esc_html__('Border Color', 'trivia-finder-elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .trivia-filter-group select' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'filter_typography',
                'selector' => '{{WRAPPER}} .trivia-filter-group select',
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'card_style_section',
            [
                'label' => esc_html__('Venue Cards', 'trivia-finder-elementor'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'card_header_bg',
            [
                'label'     => esc_html__('Header Background', 'trivia-finder-elementor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .trivia-venue-header' => 'background: {{VALUE}}; background-image: none;',
                    '{{WRAPPER}} .trivia-custom-info-header' => 'background: {{VALUE}}; background-image: none;',
                ],
            ]
        );

        $this->add_control(
            'card_border_radius',
            [
                'label'      => esc_html__('Border Radius', 'trivia-finder-elementor'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => [
                    'px' => [
                        'min'  => 0,
                        'max'  => 30,
                        'step' => 1,
                    ],
                ],
                'selectors'  => [
                    '{{WRAPPER}} .trivia-venue-card' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings        = $this->get_settings_for_display();
        $google_api_key  = trim((string) get_option('trivia_finder_google_maps_key', ''));
        $default_csv_url = trim((string) get_option('trivia_finder_default_csv_url', ''));
        $csv_url         = !empty($settings['csv_url']['url']) ? $settings['csv_url']['url'] : $default_csv_url;
        $wrapper_id      = 'trivia-finder-' . esc_attr($this->get_id());
        $is_editor       = \Elementor\Plugin::$instance->editor->is_edit_mode();
        
        // Handle global default for mobile map height if widget control is empty
        $global_mobile_height = get_option('trivia_finder_default_mobile_map_height', 50);
        $has_custom_mobile_height = !empty($settings['map_container_height_mobile']['size']) || !empty($settings['map_container_height_tablet']['size']);

        if (!$has_custom_mobile_height && !empty($global_mobile_height)) {
            echo '<style>
                @media (max-width: 767px) {
                    #' . esc_attr($wrapper_id) . ' .trivia-map-container { height: ' . esc_attr($global_mobile_height) . '% !important; flex: none !important; min-height: 0 !important; overflow: hidden !important; }
                    #' . esc_attr($wrapper_id) . ' .trivia-map-container .triviaFinderMap { height: 100% !important; min-height: 0 !important; }
                    #' . esc_attr($wrapper_id) . ' .trivia-list-container { flex: 1 !important; min-height: 0 !important; }
                }
            </style>';
        }

        if (empty($google_api_key) && $is_editor) {
            echo '<div class="elementor-alert elementor-alert-warning">';
            echo esc_html__('Google Maps API key is not configured. Set it in Settings > Trivia Finder.', 'trivia-finder-elementor');
            echo '</div>';
        }

        if (empty($csv_url) && $is_editor) {
            echo '<div class="elementor-alert elementor-alert-warning">';
            echo esc_html__('CSV URL is not configured. Add it in the widget or in Settings > Trivia Finder.', 'trivia-finder-elementor');
            echo '</div>';
        }
        ?>
        <div
            id="<?php echo esc_attr($wrapper_id); ?>"
            class="trivia-finder-root"
            data-instance-id="<?php echo esc_attr($wrapper_id); ?>"
            data-csv-url="<?php echo esc_url($csv_url); ?>"
        >
            <div class="trivia-finder-container">
                <div class="trivia-filters">
                    <div class="trivia-filter-group">
                        <select class="triviaFinderDayFilter" aria-label="<?php esc_attr_e('Filter by day', 'trivia-finder-elementor'); ?>">
                            <option value="All"><?php esc_html_e('All Days', 'trivia-finder-elementor'); ?></option>
                        </select>
                    </div>

                    <div class="trivia-filter-group">
                        <select class="triviaFinderLocationFilter" aria-label="<?php esc_attr_e('Filter by location', 'trivia-finder-elementor'); ?>">
                            <option value="All"><?php esc_html_e('All Locations', 'trivia-finder-elementor'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="trivia-content-wrapper">
                    <div class="trivia-map-container">
                        <div class="triviaFinderMap" aria-label="<?php esc_attr_e('Trivia venue map', 'trivia-finder-elementor'); ?>"></div>
                        <div class="triviaFinderLoadingIndicator trivia-loading">
                            <?php esc_html_e('Loading trivia nights...', 'trivia-finder-elementor'); ?>
                        </div>
                    </div>

                    <div class="trivia-list-container">
                        <h2 class="trivia-venues-title"><?php esc_html_e('List of Venues', 'trivia-finder-elementor'); ?></h2>
                        <div class="triviaFinderVenueList trivia-venue-list"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
