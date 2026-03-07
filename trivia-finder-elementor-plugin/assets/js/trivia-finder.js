(function () {
    "use strict";

    const dayOrder = {
        monday: 0,
        tuesday: 1,
        wednesday: 2,
        thursday: 3,
        friday: 4,
        saturday: 5,
        sunday: 6
    };

    const instancesById = {};
    let googleMapsPromise = null;

    function escapeHtml(text) {
        const div = document.createElement("div");
        div.textContent = text == null ? "" : String(text);
        return div.innerHTML;
    }

    function getDefaults() {
        return window.TriviaFinderDefaults || {};
    }

    function getDefaultCsvUrl() {
        return String(getDefaults().csvUrl || "").trim();
    }

    function getGoogleMapsApiKey() {
        return String(getDefaults().googleMapsApiKey || "").trim();
    }

    function getDefaultCenter() {
        const defaults = getDefaults();
        const center = defaults.center || {};

        return {
            lat: Number(center.lat) || -37.8136,
            lng: Number(center.lng) || 144.9631
        };
    }

    function getDefaultZoom() {
        return Number(getDefaults().zoom) || 12;
    }

    function normalizeWebsiteUrl(url) {
        const value = String(url || "").trim();
        if (!value) {
            return "";
        }

        if (/^https?:\/\//i.test(value)) {
            return value;
        }

        return "https://" + value.replace(/^\/+/, "");
    }

    function ensureGoogleMaps() {
        if (window.google && window.google.maps) {
            return Promise.resolve();
        }

        if (googleMapsPromise) {
            return googleMapsPromise;
        }

        const apiKey = getGoogleMapsApiKey();

        if (!apiKey) {
            return Promise.reject(new Error("Google Maps API key is not configured."));
        }

        googleMapsPromise = new Promise(function (resolve, reject) {
            window.triviaFinderGoogleMapsReady = function () {
                resolve();
                try {
                    delete window.triviaFinderGoogleMapsReady;
                } catch (e) {
                    window.triviaFinderGoogleMapsReady = undefined;
                }
            };

            const script = document.createElement("script");
            script.src =
                "https://maps.googleapis.com/maps/api/js?key=" +
                encodeURIComponent(apiKey) +
                "&callback=triviaFinderGoogleMapsReady";
            script.async = true;
            script.defer = true;
            script.setAttribute("data-trivia-google-maps", "1");

            script.onerror = function () {
                reject(new Error("Google Maps failed to load."));
            };

            document.head.appendChild(script);
        });

        return googleMapsPromise;
    }

    class TriviaFinderInstance {
        constructor(root) {
            this.root = root;
            this.instanceId = root.getAttribute("data-instance-id") || ("trivia-finder-" + Math.random().toString(36).slice(2));
            this.allVenues = [];
            this.filteredVenues = [];
            this.markers = [];
            this.map = null;
            this.infoWindow = null;

            this.daySelect = root.querySelector(".triviaFinderDayFilter");
            this.locSelect = root.querySelector(".triviaFinderLocationFilter");
            this.mapEl = root.querySelector(".triviaFinderMap");
            this.listEl = root.querySelector(".triviaFinderVenueList");
            this.loadingEl = root.querySelector(".triviaFinderLoadingIndicator");

            this.csvUrl = root.getAttribute("data-csv-url") || getDefaultCsvUrl();

            instancesById[this.instanceId] = this;

            this.bindEvents();
        }

        bindEvents() {
            if (this.daySelect) {
                this.daySelect.addEventListener("change", () => {
                    this.updateLocationFilter();
                    this.applyFilters();
                });
            }

            if (this.locSelect) {
                this.locSelect.addEventListener("change", () => {
                    this.applyFilters();
                });
            }
        }

        init() {
            if (!this.csvUrl) {
                this.setLoading(
                    true,
                    "<strong>CSV URL is not configured</strong><br>Please set it in the widget or plugin settings."
                );
                return;
            }

            ensureGoogleMaps()
                .then(() => {
                    this.initMap();
                })
                .catch((error) => {
                    this.setLoading(
                        true,
                        "<strong>Map failed to load</strong><br>" + escapeHtml(error.message)
                    );
                });
        }

        setLoading(isLoading, message) {
            if (!this.loadingEl) {
                return;
            }

            if (message) {
                this.loadingEl.innerHTML = message;
            }

            this.loadingEl.style.display = isLoading ? "block" : "none";
        }

        initMap() {
            if (!this.mapEl || !window.google || !google.maps) {
                this.setLoading(true, "<strong>Map container not found</strong>");
                return;
            }

            const center = getDefaultCenter();
            const zoom = getDefaultZoom();

            this.map = new google.maps.Map(this.mapEl, {
                center: center,
                zoom: zoom,
                mapTypeControl: false,
                streetViewControl: false,
                gestureHandling: "greedy",
                styles: [
                    {
                        featureType: "poi",
                        elementType: "labels",
                        stylers: [{ visibility: "off" }]
                    }
                ]
            });

            this.infoWindow = new google.maps.InfoWindow({
                disableAutoPan: false
            });

            this.map.addListener("click", () => {
                this.infoWindow.close();
            });

            this.loadCsv();
        }

        loadCsv() {
            this.setLoading(true, "Loading trivia nights...");

            d3.csv(this.csvUrl)
                .then((data) => {
                    this.allVenues = data
                        .filter((row) => String(row.venue || "").trim() && String(row.address || "").trim())
                        .map((row, index) => ({
                            id: index,
                            name: String(row.venue || "").trim(),
                            location: String(row.location || "").trim(),
                            address: String(row.address || "").trim(),
                            day: String(row.day || "").trim(),
                            dayTime: String(row.day_time || row.day || "").trim(),
                            special: String(row.special || "").trim(),
                            website: String(row.website || "").trim(),
                            phone: String(row.phone || "").trim(),
                            lat: Number(row.latitude),
                            lng: Number(row.longitude)
                        }));

                    this.populateFilters();
                    this.applyFilters();
                    this.setLoading(false);
                })
                .catch((error) => {
                    console.error("Trivia Finder CSV load error:", error);
                    this.setLoading(
                        true,
                        "<strong>Error loading data</strong><br>Please check the CSV URL and refresh the page."
                    );
                });
        }

        populateFilters() {
            if (!this.daySelect) {
                return;
            }

            const days = [...new Set(this.allVenues.map((venue) => venue.day).filter(Boolean))];

            days.sort((a, b) => {
                const aValue = dayOrder[a.toLowerCase()] ?? 999;
                const bValue = dayOrder[b.toLowerCase()] ?? 999;

                if (aValue !== bValue) {
                    return aValue - bValue;
                }

                return a.localeCompare(b);
            });

            this.daySelect.innerHTML = '<option value="All">All Days</option>';

            days.forEach((day) => {
                const option = document.createElement("option");
                option.value = day;
                option.textContent = day;
                this.daySelect.appendChild(option);
            });

            this.updateLocationFilter();
        }

        updateLocationFilter() {
            if (!this.locSelect) {
                return;
            }

            const selectedDay = this.daySelect ? this.daySelect.value : "All";
            const relevantVenues = selectedDay === "All"
                ? this.allVenues
                : this.allVenues.filter((venue) => venue.day === selectedDay);

            const locations = [...new Set(relevantVenues.map((venue) => venue.location).filter(Boolean))].sort();
            const currentValue = this.locSelect.value || "All";

            this.locSelect.innerHTML = '<option value="All">All Locations</option>';

            locations.forEach((location) => {
                const option = document.createElement("option");
                option.value = location;
                option.textContent = location;
                this.locSelect.appendChild(option);
            });

            this.locSelect.value = locations.includes(currentValue) ? currentValue : "All";
        }

        applyFilters() {
            const selectedDay = this.daySelect ? this.daySelect.value : "All";
            const selectedLocation = this.locSelect ? this.locSelect.value : "All";

            this.filteredVenues = this.allVenues.filter((venue) => {
                const dayMatch = selectedDay === "All" || venue.day === selectedDay;
                const locationMatch = selectedLocation === "All" || venue.location === selectedLocation;
                return dayMatch && locationMatch;
            });

            this.updateMap();
            this.updateList();
            this.fitMapToMarkers();
        }

        hasValidCoords(venue) {
            return Number.isFinite(venue.lat) && Number.isFinite(venue.lng);
        }

        fitMapToMarkers() {
            if (!this.map) {
                return;
            }

            const validVenues = this.filteredVenues.filter((venue) => this.hasValidCoords(venue));

            if (!validVenues.length) {
                this.map.setCenter(getDefaultCenter());
                this.map.setZoom(getDefaultZoom());
                return;
            }

            const bounds = new google.maps.LatLngBounds();

            validVenues.forEach((venue) => {
                bounds.extend({ lat: venue.lat, lng: venue.lng });
            });

            this.map.fitBounds(bounds);

            if (validVenues.length === 1) {
                google.maps.event.addListenerOnce(this.map, "bounds_changed", () => {
                    this.map.setZoom(15);
                });
            }
        }

        createInfoWindowContent(venue) {
            const websiteUrl = normalizeWebsiteUrl(venue.website);

            return `
                <div class="trivia-custom-info-window">
                    <div class="trivia-custom-info-header">
                        <h3 class="trivia-custom-info-title">${escapeHtml(venue.name)}</h3>
                        <button
                            type="button"
                            class="trivia-custom-close-button"
                            onclick="window.TriviaFinderCloseInfoWindow && window.TriviaFinderCloseInfoWindow('${escapeHtml(this.instanceId)}')"
                            aria-label="Close"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="9" height="9" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                <line x1="1" y1="1" x2="11" y2="11"></line>
                                <line x1="11" y1="1" x2="1" y2="11"></line>
                            </svg>
                        </button>
                    </div>
                    <div class="trivia-custom-info-body">
                        <div class="trivia-info-detail-row">
                            <span class="trivia-info-detail-icon">📍</span>
                            <span class="trivia-info-detail-text">${escapeHtml(venue.address)}</span>
                        </div>

                        <div class="trivia-info-detail-row">
                            <span class="trivia-info-detail-icon">📅</span>
                            <span class="trivia-info-detail-text"><strong>${escapeHtml(venue.dayTime)}</strong></span>
                        </div>

                        ${venue.special ? `
                            <div class="trivia-info-detail-row">
                                <span class="trivia-info-detail-icon">🍽️</span>
                                <span class="trivia-info-detail-text">${escapeHtml(venue.special)}</span>
                            </div>
                        ` : ""}

                        ${websiteUrl ? `
                            <div class="trivia-info-detail-row">
                                <span class="trivia-info-detail-icon">🌐</span>
                                <a href="${escapeHtml(websiteUrl)}" target="_blank" rel="noopener noreferrer" class="trivia-info-detail-link">Visit Website</a>
                            </div>
                        ` : ""}

                        ${venue.phone ? `
                            <div class="trivia-info-detail-row">
                                <span class="trivia-info-detail-icon">📞</span>
                                <span class="trivia-info-detail-text">${escapeHtml(venue.phone)}</span>
                            </div>
                        ` : ""}
                    </div>
                </div>
            `;
        }

        updateMap() {
            if (!this.map) {
                return;
            }

            this.markers.forEach((marker) => marker.setMap(null));
            this.markers = [];

            this.filteredVenues.forEach((venue) => {
                if (!this.hasValidCoords(venue)) {
                    return;
                }

                const marker = new google.maps.Marker({
                    position: { lat: venue.lat, lng: venue.lng },
                    map: this.map,
                    title: venue.name,
                    animation: google.maps.Animation.DROP
                });

                marker.venueId = venue.id;

                marker.addListener("click", () => {
                    this.map.panTo(marker.getPosition());
                    this.infoWindow.setContent(this.createInfoWindowContent(venue));
                    this.infoWindow.open(this.map, marker);
                    this.highlightListItem(venue.id);
                });

                this.markers.push(marker);
            });
        }

        renderVenueCard(venue) {
            const websiteUrl = normalizeWebsiteUrl(venue.website);

            return `
                <div class="trivia-venue-card" data-venue-id="${venue.id}">
                    <div class="trivia-venue-header">
                        <div class="trivia-venue-name">${escapeHtml(venue.name)}</div>
                    </div>

                    <div class="trivia-venue-body">
                        <div class="trivia-venue-detail">
                            <span class="trivia-venue-detail-icon">📍</span>
                            <span class="trivia-venue-detail-text">${escapeHtml(venue.address)}</span>
                        </div>

                        <div class="trivia-venue-detail">
                            <span class="trivia-venue-detail-icon">📅</span>
                            <span class="trivia-venue-detail-text"><strong>${escapeHtml(venue.dayTime)}</strong></span>
                        </div>

                        ${venue.special ? `
                            <div class="trivia-venue-detail">
                                <span class="trivia-venue-detail-icon">🍽️</span>
                                <span class="trivia-venue-detail-text">${escapeHtml(venue.special)}</span>
                            </div>
                        ` : ""}

                        ${websiteUrl ? `
                            <div class="trivia-venue-detail">
                                <span class="trivia-venue-detail-icon">🌐</span>
                                <a href="${escapeHtml(websiteUrl)}" target="_blank" rel="noopener noreferrer" class="trivia-venue-website">Visit Website</a>
                            </div>
                        ` : ""}

                        ${venue.phone ? `
                            <div class="trivia-venue-detail">
                                <span class="trivia-venue-detail-icon">📞</span>
                                <span class="trivia-venue-detail-text">${escapeHtml(venue.phone)}</span>
                            </div>
                        ` : ""}
                    </div>
                </div>
            `;
        }

        updateList() {
            if (!this.listEl) {
                return;
            }

            if (!this.filteredVenues.length) {
                this.listEl.innerHTML = `
                    <div class="trivia-empty-state">
                        <h3>🔍 No trivia nights found</h3>
                        <p>Try adjusting your filters</p>
                    </div>
                `;
                return;
            }

            this.listEl.innerHTML = this.filteredVenues.map((venue) => this.renderVenueCard(venue)).join("");

            this.listEl.querySelectorAll(".trivia-venue-card").forEach((card) => {
                card.addEventListener("click", () => {
                    this.handleListClick(Number(card.getAttribute("data-venue-id")));
                });
            });

            this.listEl.querySelectorAll(".trivia-venue-website").forEach((link) => {
                link.addEventListener("click", (event) => {
                    event.stopPropagation();
                });
            });
        }

        handleListClick(venueId) {
            const venue = this.allVenues.find((item) => item.id === venueId);
            const marker = this.markers.find((item) => item.venueId === venueId);

            if (!venue || !marker) {
                return;
            }

            this.map.panTo(marker.getPosition());
            this.map.setZoom(15);
            google.maps.event.trigger(marker, "click");
        }

        highlightListItem(venueId) {
            if (!this.listEl) {
                return;
            }

            this.listEl.querySelectorAll(".trivia-venue-card").forEach((card) => {
                card.classList.remove("highlighted");
            });

            const card = this.listEl.querySelector(`.trivia-venue-card[data-venue-id="${venueId}"]`);

            if (!card) {
                return;
            }

            card.classList.add("highlighted");

            const cardTop = card.getBoundingClientRect().top;
            const listTop = this.listEl.getBoundingClientRect().top;
            const scrollTarget = this.listEl.scrollTop + (cardTop - listTop);

            this.listEl.scrollTo({
                top: scrollTarget,
                behavior: "smooth"
            });
        }
    }

    function initTriviaFinder(scope) {
        let roots = [];

        if (scope && scope.classList && scope.classList.contains("trivia-finder-root")) {
            roots = [scope];
        } else if (scope && scope.querySelectorAll) {
            roots = Array.from(scope.querySelectorAll(".trivia-finder-root"));
        } else {
            roots = Array.from(document.querySelectorAll(".trivia-finder-root"));
        }

        roots.forEach((root) => {
            if (root.getAttribute("data-initialized") === "1") {
                return;
            }

            root.setAttribute("data-initialized", "1");

            const instance = new TriviaFinderInstance(root);
            instance.init();
        });
    }

    window.TriviaFinderCloseInfoWindow = function (instanceId) {
        const instance = instancesById[instanceId];
        if (instance && instance.infoWindow) {
            instance.infoWindow.close();
        }
    };

    document.addEventListener("DOMContentLoaded", function () {
        initTriviaFinder(document);
    });

    if (window.jQuery) {
        jQuery(window).on("elementor/frontend/init", function () {
            if (window.elementorFrontend && elementorFrontend.hooks) {
                elementorFrontend.hooks.addAction("frontend/element_ready/trivia_finder.default", function ($scope) {
                    initTriviaFinder($scope[0]);
                });
            }
        });
    }
})();
