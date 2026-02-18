<?php
namespace Elementor;

if (!defined('ABSPATH')) exit;

class Trivia_Finder_Widget extends Widget_Base {

    public function get_name() {
        return 'trivia-finder';
    }

    public function get_title() {
        return esc_html__('Trivia Night Finder', 'trivia-finder-elementor');
    }

    public function get_icon() {
        return 'eicon-map-pin';
    }

    public function get_categories() {
        return ['trivia-finder'];
    }

    public function get_keywords() {
        return ['map', 'trivia', 'finder', 'location', 'venue'];
    }

    public function get_script_depends() {
        $google_api_key = get_option('trivia_finder_google_maps_key', '');

        if (!empty($google_api_key)) {
            // FIXED: Added &loading=async parameter for better performance
            wp_register_script(
                'google-maps-trivia',
                'https://maps.googleapis.com/maps/api/js?key=' . esc_attr($google_api_key) . '&loading=async&callback=triviaFinderInitMap',
                ['trivia-finder-script'],
                null,
                true
            );
            return ['trivia-finder-script', 'google-maps-trivia'];
        }

        return ['trivia-finder-script'];
    }

    public function get_style_depends() {
        return ['trivia-finder-styles'];
    }

    protected function register_controls() {

        // Content Section
        $this->start_controls_section(
            'content_section',
            [
                'label' => esc_html__('Content', 'trivia-finder-elementor'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'title',
            [
                'label' => esc_html__('Title', 'trivia-finder-elementor'),
                'type' => Controls_Manager::TEXT,
                'default' => esc_html__('Find A Trivia Night', 'trivia-finder-elementor'),
                'placeholder' => esc_html__('Enter title', 'trivia-finder-elementor'),
                'label_block' => true,
            ]
        );

        $this->add_control(
            'csv_url',
            [
                'label' => esc_html__('CSV URL', 'trivia-finder-elementor'),
                'type' => Controls_Manager::URL,
                'placeholder' => esc_html__('https://your-csv-url.com/data.csv', 'trivia-finder-elementor'),
                'description' => esc_html__('Leave empty to use default from settings', 'trivia-finder-elementor'),
                'show_external' => false,
            ]
        );

        $this->add_control(
            'map_height',
            [
                'label' => esc_html__('Container Height', 'trivia-finder-elementor'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', 'vh'],
                'range' => [
                    'px' => [
                        'min' => 300,
                        'max' => 1200,
                        'step' => 10,
                    ],
                    'vh' => [
                        'min' => 30,
                        'max' => 100,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'unit' => 'vh',
                    'size' => 90,
                ],
                'selectors' => [
                    '{{WRAPPER}} .trivia-finder-container' => 'height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section - Title
        $this->start_controls_section(
            'title_style_section',
            [
                'label' => esc_html__('Title', 'trivia-finder-elementor'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => esc_html__('Color', 'trivia-finder-elementor'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .trivia-finder-title' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .trivia-finder-title',
            ]
        );

        $this->add_responsive_control(
            'title_padding',
            [
                'label' => esc_html__('Padding', 'trivia-finder-elementor'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .trivia-finder-title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section - Filters
        $this->start_controls_section(
            'filter_style_section',
            [
                'label' => esc_html__('Filters', 'trivia-finder-elementor'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'filter_background',
            [
                'label' => esc_html__('Background Color', 'trivia-finder-elementor'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .trivia-filter-group select' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'filter_border_color',
            [
                'label' => esc_html__('Border Color', 'trivia-finder-elementor'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .trivia-filter-group select' => 'border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'filter_typography',
                'selector' => '{{WRAPPER}} .trivia-filter-group select',
            ]
        );

        $this->end_controls_section();

        // Style Section - Cards
        $this->start_controls_section(
            'card_style_section',
            [
                'label' => esc_html__('Venue Cards', 'trivia-finder-elementor'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'card_header_bg',
            [
                'label' => esc_html__('Header Background', 'trivia-finder-elementor'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .trivia-venue-header' => 'background: {{VALUE}}; background-image: none;',
                ],
            ]
        );

        $this->add_control(
            'card_border_radius',
            [
                'label' => esc_html__('Border Radius', 'trivia-finder-elementor'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 30,
                        'step' => 1,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .trivia-venue-card' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        $google_api_key = get_option('trivia_finder_google_maps_key', '');

        if (empty($google_api_key)) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div style="padding:20px;background:#fff3cd;border:2px solid #ffc107;border-radius:8px;text-align:center;">
                    <strong>⚠️ Trivia Finder:</strong> Google Maps API key is not configured.<br>
                    Go to <strong>Settings → Trivia Finder</strong> to set your API key.
                </div>';
            }
            return;
        }

        $csv_url = !empty($settings['csv_url']['url']) ? $settings['csv_url']['url'] : '';
        $title = $settings['title'];
        ?>
        <div
            class="trivia-finder-container"
            data-csv-url="<?php echo esc_attr($csv_url); ?>"
        >
            <h2 class="trivia-finder-title"><?php echo esc_html($title); ?></h2>

            <div class="trivia-filters">
                <div class="trivia-filter-group">
                    <select class="triviaFinderDayFilter">
                        <option value="All">All Days</option>
                    </select>
                </div>

                <div class="trivia-filter-group">
                    <select class="triviaFinderLocationFilter">
                        <option value="All">All Locations</option>
                    </select>
                </div>
            </div>

            <div class="trivia-content-wrapper">
                <div class="trivia-map-container">
                    <div class="triviaFinderMap"></div>
                    <div class="triviaFinderLoadingIndicator trivia-loading">Loading trivia nights...</div>
                </div>

                <div class="trivia-list-container">
                    <div class="triviaFinderVenueList trivia-venue-list"></div>
                </div>
            </div>
        </div>
        <?php
    }

    protected function content_template() {
        ?>
        <#
        var csvUrl = settings.csv_url.url || '';
        var title = settings.title;
        #>
        <div class="trivia-finder-container" data-csv-url="{{{ csvUrl }}}">
            <h2 class="trivia-finder-title">{{{ title }}}</h2>

            <div class="trivia-filters">
                <div class="trivia-filter-group">
                    <select class="triviaFinderDayFilter">
                        <option value="All">All Days</option>
                    </select>
                </div>

                <div class="trivia-filter-group">
                    <select class="triviaFinderLocationFilter">
                        <option value="All">All Locations</option>
                    </select>
                </div>
            </div>

            <div class="trivia-content-wrapper">
                <div class="trivia-map-container">
                    <div class="triviaFinderMap"></div>
                    <div class="triviaFinderLoadingIndicator trivia-loading">
                        Loading trivia nights... (Preview mode)
                    </div>
                </div>

                <div class="trivia-list-container">
                    <div class="triviaFinderVenueList trivia-venue-list"></div>
                </div>
            </div>
        </div>
        <?php
    }
}
