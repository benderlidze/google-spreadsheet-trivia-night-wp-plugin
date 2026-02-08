(function() {
    let allVenues = [];
    let filteredVenues = [];
    let markers = [];
    let map, infoWindow;

    window.triviaFinderInitMap = function() {
        map = new google.maps.Map(document.getElementById('triviaFinderMap'), {
            center: { lat: -37.8136, lng: 144.9631 },
            zoom: 12,
            mapTypeControl: false,
            streetViewControl: false,
            styles: [
                {
                    featureType: "poi",
                    elementType: "labels",
                    stylers: [{ visibility: "off" }]
                }
            ]
        });

        infoWindow = new google.maps.InfoWindow({
            disableAutoPan: false
        });

        d3.csv(triviaFinderCsvUrl)
            .then(data => {
                allVenues = data
                    .filter(d => d.venue && d.address)
                    .map((d, i) => ({
                        id: i,
                        name: d.venue,
                        location: d.location,
                        address: d.address,
                        day: d.day,
                        dayTime: d.day_time,
                        special: d.special || '',
                        website: d.website || '',
                        phone: d.phone || '',
                        lat: +d.latitude,
                        lng: +d.longitude,
                    }));

                populateFilters();
                applyFilters();
                document.getElementById('triviaFinderLoadingIndicator').style.display = 'none';
            })
            .catch(err => {
                console.error('Error loading CSV:', err);
                document.getElementById('triviaFinderLoadingIndicator').innerHTML =
                    '<strong>Error loading data</strong><br>Please refresh the page';
            });
    };

    function populateFilters() {
        const days = [...new Set(allVenues.map(v => v.day))].filter(Boolean).sort();

        const dayFilter = document.getElementById('triviaFinderDayFilter');
        dayFilter.innerHTML = '<option value="All">All Days</option>';
        days.forEach(day => {
            const option = document.createElement('option');
            option.value = day;
            option.textContent = day;
            dayFilter.appendChild(option);
        });

        updateLocationFilter();
    }

    function updateLocationFilter() {
        const selectedDay = document.getElementById('triviaFinderDayFilter').value;

        const relevantVenues = selectedDay === 'All'
            ? allVenues
            : allVenues.filter(v => v.day === selectedDay);

        const locations = [...new Set(relevantVenues.map(v => v.location))].filter(Boolean).sort();

        const locationFilter = document.getElementById('triviaFinderLocationFilter');
        const currentLocation = locationFilter.value;

        locationFilter.innerHTML = '<option value="All">All Locations</option>';
        locations.forEach(location => {
            const option = document.createElement('option');
            option.value = location;
            option.textContent = location;
            locationFilter.appendChild(option);
        });

        if (locations.includes(currentLocation)) {
            locationFilter.value = currentLocation;
        } else {
            locationFilter.value = 'All';
        }
    }

    function applyFilters() {
        const day = document.getElementById('triviaFinderDayFilter').value;
        const loc = document.getElementById('triviaFinderLocationFilter').value;

        filteredVenues = allVenues.filter(v =>
            (day === 'All' || v.day === day) &&
            (loc === 'All' || v.location === loc)
        );

        updateMap();
        updateList();
        fitMapToMarkers();
    }

    function fitMapToMarkers() {
        if (filteredVenues.length === 0) return;

        const bounds = new google.maps.LatLngBounds();
        filteredVenues.forEach(v => {
            if (v.lat && v.lng) {
                bounds.extend({ lat: v.lat, lng: v.lng });
            }
        });

        map.fitBounds(bounds);

        if (filteredVenues.length === 1) {
            map.setZoom(15);
        }
    }

    function createInfoWindowContent(v) {
        return `
            <div class="trivia-custom-info-window">
                <div class="trivia-custom-info-header">
                    <h3 class="trivia-custom-info-title">${v.name} – ${v.location}</h3>
                    <button class="trivia-custom-close-button" onclick="triviaFinderCloseInfoWindow()" aria-label="Close">×</button>
                </div>
                <div class="trivia-custom-info-body">
                    <div class="trivia-info-detail-row">
                        <span class="trivia-info-detail-icon">📍</span>
                        <span class="trivia-info-detail-text">${v.address}</span>
                    </div>
                    <div class="trivia-info-detail-row">
                        <span class="trivia-info-detail-icon">📅</span>
                        <span class="trivia-info-detail-text"><strong>${v.dayTime}</strong></span>
                    </div>
                    ${v.special ? `
                        <div class="trivia-info-detail-row">
                            <span class="trivia-info-detail-icon">🍽️</span>
                            <span class="trivia-info-detail-text">${v.special}</span>
                        </div>
                    ` : ''}
                    ${v.website ? `
                        <div class="trivia-info-detail-row">
                            <span class="trivia-info-detail-icon">🌐</span>
                            <a href="${v.website}" target="_blank" rel="noopener" class="trivia-info-detail-link">Visit Website</a>
                        </div>
                    ` : ''}
                    ${v.phone ? `
                        <div class="trivia-info-detail-row">
                            <span class="trivia-info-detail-icon">📞</span>
                            <span class="trivia-info-detail-text">${v.phone}</span>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }

    window.triviaFinderCloseInfoWindow = function() {
        if (infoWindow) {
            infoWindow.close();
        }
    };

    function updateMap() {
        markers.forEach(m => m.setMap(null));
        markers = [];

        filteredVenues.forEach(v => {
            if (!v.lat || !v.lng) return;

            const marker = new google.maps.Marker({
                position: { lat: v.lat, lng: v.lng },
                map: map,
                title: v.name,
                animation: google.maps.Animation.DROP
            });

            marker.venueId = v.id;

            marker.addListener('click', () => {
                map.panTo(marker.getPosition());
                infoWindow.setContent(createInfoWindowContent(v));
                infoWindow.open(map, marker);
                highlightListItem(v.id);
            });

            markers.push(marker);
        });
    }

    function updateList() {
        const list = document.getElementById('triviaFinderVenueList');

        if (filteredVenues.length === 0) {
            list.innerHTML = '<div class="trivia-empty-state"><h3>🔍 No trivia nights found</h3><p>Try adjusting your filters</p></div>';
            return;
        }

        list.innerHTML = filteredVenues.map(v => `
            <div class="trivia-venue-card" data-venue-id="${v.id}" onclick="triviaFinderHandleListClick(${v.id})">
                <div class="trivia-venue-header">
                    <div class="trivia-venue-name">${v.name} – ${v.location}</div>
                </div>
                <div class="trivia-venue-body">
                    <div class="trivia-venue-detail">
                        <span class="trivia-venue-detail-icon">📍</span>
                        <span class="trivia-venue-detail-text">${v.address}</span>
                    </div>
                    <div class="trivia-venue-detail">
                        <span class="trivia-venue-detail-icon">📅</span>
                        <span class="trivia-venue-detail-text"><strong>${v.dayTime}</strong></span>
                    </div>
                    ${v.special ? `
                        <div class="trivia-venue-detail">
                            <span class="trivia-venue-detail-icon">🍽️</span>
                            <span class="trivia-venue-detail-text">${v.special}</span>
                        </div>
                    ` : ''}
                    ${v.website ? `
                        <div class="trivia-venue-detail">
                            <span class="trivia-venue-detail-icon">🌐</span>
                            <a href="${v.website}" target="_blank" rel="noopener" class="trivia-venue-website" onclick="event.stopPropagation()">
                                Visit Website
                            </a>
                        </div>
                    ` : ''}
                    ${v.phone ? `
                        <div class="trivia-venue-detail">
                            <span class="trivia-venue-detail-icon">📞</span>
                            <span class="trivia-venue-detail-text">${v.phone}</span>
                        </div>
                    ` : ''}
                </div>
            </div>
        `).join('');
    }

    window.triviaFinderHandleListClick = function(venueId) {
        const venue = allVenues.find(v => v.id === venueId);
        const marker = markers.find(m => m.venueId === venueId);

        if (marker && venue) {
            map.panTo(marker.getPosition());
            map.setZoom(15);
            google.maps.event.trigger(marker, 'click');
        }
    };

    function highlightListItem(venueId) {
        document.querySelectorAll('.trivia-venue-card').forEach(c => c.classList.remove('highlighted'));
        const card = document.querySelector(`[data-venue-id="${venueId}"]`);
        if (card) {
            card.classList.add('highlighted');
            card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    // Initialize event listeners when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        const dayFilter = document.getElementById('triviaFinderDayFilter');
        const locationFilter = document.getElementById('triviaFinderLocationFilter');
        
        if (dayFilter) {
            dayFilter.onchange = () => {
                updateLocationFilter();
                applyFilters();
            };
        }
        
        if (locationFilter) {
            locationFilter.onchange = applyFilters;
        }
    });
})();
