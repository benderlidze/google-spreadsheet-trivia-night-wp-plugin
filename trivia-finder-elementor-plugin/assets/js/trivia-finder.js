(function () {
  const dayOrder = {
    monday: 0, tuesday: 1, wednesday: 2, thursday: 3, friday: 4, saturday: 5, sunday: 6
  };

  const instances = [];
  let googleReady = false;
  let initAttempts = 0;
  const MAX_INIT_ATTEMPTS = 50;

  function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text == null ? "" : String(text);
    return div.innerHTML;
  }

  function getDefaultCsvUrl() {
    return (window.TriviaFinderDefaults && window.TriviaFinderDefaults.csvUrl) ? window.TriviaFinderDefaults.csvUrl : "";
  }

  function getDefaultCenter() {
    if (window.TriviaFinderDefaults && window.TriviaFinderDefaults.center) {
      return {
        lat: Number(window.TriviaFinderDefaults.center.lat) || -37.8136,
        lng: Number(window.TriviaFinderDefaults.center.lng) || 144.9631
      };
    }
    return { lat: -37.8136, lng: 144.9631 };
  }

  function getDefaultZoom() {
    if (window.TriviaFinderDefaults && window.TriviaFinderDefaults.zoom) {
      return Number(window.TriviaFinderDefaults.zoom) || 12;
    }
    return 12;
  }

  class TriviaFinderInstance {
    constructor(root) {
      this.root = root;
      this.allVenues = [];
      this.filteredVenues = [];
      this.markers = [];

      this.daySelect = root.querySelector(".triviaFinderDayFilter");
      this.locSelect = root.querySelector(".triviaFinderLocationFilter");
      this.mapEl = root.querySelector(".triviaFinderMap");
      this.listEl = root.querySelector(".triviaFinderVenueList");
      this.loadingEl = root.querySelector(".triviaFinderLoadingIndicator");

      this.map = null;
      this.infoWindow = null;

      this.csvUrl = root.getAttribute("data-csv-url") || getDefaultCsvUrl();
    }

    setLoading(isLoading, message) {
      if (!this.loadingEl) return;

      // FIXED: Use class manipulation AND display property
      if (isLoading) {
        this.loadingEl.classList.add("trivia-loading");
        this.loadingEl.style.display = "block";
        if (message) this.loadingEl.innerHTML = message;
      } else {
        this.loadingEl.classList.remove("trivia-loading");
        this.loadingEl.style.display = "none"; // FORCE HIDE
      }
    }

    initMap() {
      if (!this.mapEl) {
        console.warn("Trivia Finder: Map element not found");
        return;
      }

      console.log("Trivia Finder: Initializing map");

      try {
        const center = getDefaultCenter();
        const zoom = getDefaultZoom();

        this.map = new google.maps.Map(this.mapEl, {
          center: center,
          zoom: zoom,
          mapTypeControl: false,
          streetViewControl: false,
          styles: [{ featureType: "poi", elementType: "labels", stylers: [{ visibility: "off" }] }],
        });

        this.infoWindow = new google.maps.InfoWindow({ disableAutoPan: false });

        this.loadCsv();
      } catch (error) {
        console.error("Trivia Finder: Error initializing map", error);
        this.setLoading(true, "<div><strong>Error</strong><br>" + error.message + "</div>");
      }
    }

    loadCsv() {
      if (!this.csvUrl) {
        this.setLoading(true, "<div><strong>Error</strong><br>CSV URL missing</div>");
        return;
      }

      this.setLoading(true, "Loading trivia nights...");

      if (typeof d3 === 'undefined' || typeof d3.csv === 'undefined') {
        this.setLoading(true, "<div><strong>Error</strong><br>D3.js not loaded</div>");
        return;
      }

      d3.csv(this.csvUrl)
        .then((data) => {
          this.allVenues = data
            .filter((d) => d.venue && d.address)
            .map((d, i) => ({
              id: i,
              name: d.venue,
              location: d.location,
              address: d.address,
              day: d.day,
              dayTime: d.day_time,
              special: d.special || "",
              website: d.website || "",
              phone: d.phone || "",
              lat: +d.latitude,
              lng: +d.longitude,
            }));

          console.log("Trivia Finder: Loaded " + this.allVenues.length + " venues");

          this.setupFilters();
          this.applyFilters();

          // FIXED: Explicitly hide loading indicator after everything is done
          this.setLoading(false);

        })
        .catch((err) => {
          console.error("Trivia Finder: CSV error", err);
          this.setLoading(true, "<div><strong>Error</strong><br>Failed to load CSV</div>");
        });
    }

    setupFilters() {
      if (!this.daySelect || !this.locSelect) return;

      const days = [...new Set(this.allVenues.map((v) => v.day))].filter(Boolean);
      days.sort((a, b) => (dayOrder[a.toLowerCase()] ?? 99) - (dayOrder[b.toLowerCase()] ?? 99));

      this.daySelect.innerHTML = '<option value="All">All Days</option>' +
        days.map((d) => `<option value="${escapeHtml(d)}">${escapeHtml(d)}</option>`).join("");

      this.daySelect.onchange = () => {
        this.updateLocationFilter();
        this.applyFilters();
      };

      this.locSelect.onchange = () => this.applyFilters();

      this.updateLocationFilter();
    }

    updateLocationFilter() {
      if (!this.locSelect || !this.daySelect) return;

      const selectedDay = this.daySelect.value;
      const relevant = selectedDay === "All" ? this.allVenues : this.allVenues.filter((v) => v.day === selectedDay);
      const locations = [...new Set(relevant.map((v) => v.location))].filter(Boolean).sort();

      const current = this.locSelect.value;
      this.locSelect.innerHTML = '<option value="All">All Locations</option>' +
        locations.map((l) => `<option value="${escapeHtml(l)}">${escapeHtml(l)}</option>`).join("");

      this.locSelect.value = locations.includes(current) ? current : "All";
    }

    applyFilters() {
      const day = this.daySelect ? this.daySelect.value : "All";
      const loc = this.locSelect ? this.locSelect.value : "All";

      this.filteredVenues = this.allVenues.filter((v) =>
        (day === "All" || v.day === day) &&
        (loc === "All" || v.location === loc)
      );

      this.updateMap();
      this.updateList();
      this.fitBounds();
    }

    clearMarkers() {
      this.markers.forEach((m) => m.setMap(null));
      this.markers = [];
    }

    updateMap() {
      if (!this.map) return;

      this.clearMarkers();

      this.filteredVenues.forEach((v) => {
        if (!v.lat || !v.lng) return;

        const marker = new google.maps.Marker({
          position: { lat: v.lat, lng: v.lng },
          map: this.map,
          title: v.name,
          animation: google.maps.Animation.DROP,
        });

        marker.addListener("click", () => {
          this.infoWindow.setContent(this.infoHtml(v));
          this.infoWindow.open(this.map, marker);
          this.highlightCard(v.id);
        });

        this.markers.push(marker);
      });
    }

    fitBounds() {
      if (!this.map || this.filteredVenues.length === 0) return;

      const bounds = new google.maps.LatLngBounds();
      this.filteredVenues.forEach((v) => {
        if (v.lat && v.lng) bounds.extend({ lat: v.lat, lng: v.lng });
      });

      this.map.fitBounds(bounds);

      if (this.filteredVenues.length === 1) {
        google.maps.event.addListenerOnce(this.map, "bounds_changed", () => {
          this.map.setZoom(Math.min(15, this.map.getZoom()));
        });
      }
    }

    infoHtml(v) {
      return `
        <div class="trivia-custom-info-window">
          <div class="trivia-custom-info-header">
            <h3 class="trivia-custom-info-title">${escapeHtml(v.name)}</h3>
          </div>
          <div class="trivia-custom-info-body">
            ${v.address ? `<div class="trivia-info-detail-row"><span class="trivia-info-detail-icon">📍</span><span class="trivia-info-detail-text">${escapeHtml(v.address)}</span></div>` : ""}
            ${v.day ? `<div class="trivia-info-detail-row"><span class="trivia-info-detail-icon">📅</span><span class="trivia-info-detail-text"><strong>${escapeHtml(v.day)}</strong>${v.dayTime ? " at " + escapeHtml(v.dayTime) : ""}</span></div>` : ""}
            ${v.special ? `<div class="trivia-info-detail-row"><span class="trivia-info-detail-icon">⭐</span><span class="trivia-info-detail-text">${escapeHtml(v.special)}</span></div>` : ""}
            ${v.phone ? `<div class="trivia-info-detail-row"><span class="trivia-info-detail-icon">📞</span><span class="trivia-info-detail-text">${escapeHtml(v.phone)}</span></div>` : ""}
            ${v.website ? `<div class="trivia-info-detail-row"><span class="trivia-info-detail-icon">🔗</span><span class="trivia-info-detail-text"><a href="${escapeHtml(v.website)}" target="_blank" class="trivia-info-detail-link" rel="noopener">Visit Website</a></span></div>` : ""}
          </div>
        </div>
      `;
    }

    updateList() {
      if (!this.listEl) return;

      if (this.filteredVenues.length === 0) {
        this.listEl.innerHTML = '<div class="trivia-empty-state"><h3>No venues found</h3><p>Try adjusting filters</p></div>';
        return;
      }

      this.listEl.innerHTML = this.filteredVenues.map((v) => `
        <div class="trivia-venue-card" data-venue-id="${v.id}">
          <div class="trivia-venue-header">
            <h3 class="trivia-venue-name">${escapeHtml(v.name)}</h3>
          </div>
          <div class="trivia-venue-body">
            ${v.address ? `<div class="trivia-venue-detail"><span class="trivia-venue-detail-icon">📍</span><span class="trivia-venue-detail-text">${escapeHtml(v.address)}</span></div>` : ""}
            ${v.day ? `<div class="trivia-venue-detail"><span class="trivia-venue-detail-icon">📅</span><span class="trivia-venue-detail-text"><strong>${escapeHtml(v.day)}</strong>${v.dayTime ? " at " + escapeHtml(v.dayTime) : ""}</span></div>` : ""}
            ${v.special ? `<div class="trivia-venue-detail"><span class="trivia-venue-detail-icon">⭐</span><span class="trivia-venue-detail-text">${escapeHtml(v.special)}</span></div>` : ""}
            ${v.phone ? `<div class="trivia-venue-detail"><span class="trivia-venue-detail-icon">📞</span><span class="trivia-venue-detail-text">${escapeHtml(v.phone)}</span></div>` : ""}
            ${v.website ? `<div class="trivia-venue-detail"><span class="trivia-venue-detail-icon">🔗</span><a class="trivia-venue-website" href="${escapeHtml(v.website)}" target="_blank" rel="noopener">Visit Website</a></div>` : ""}
          </div>
        </div>
      `).join("");

      this.listEl.querySelectorAll(".trivia-venue-card").forEach((card) => {
        card.addEventListener("click", () => {
          const id = Number(card.getAttribute("data-venue-id"));
          const venue = this.filteredVenues.find((x) => x.id === id);
          if (!venue || !venue.lat || !venue.lng || !this.map) return;

          this.map.panTo({ lat: venue.lat, lng: venue.lng });
          this.map.setZoom(15);

          const marker = this.markers.find((m) => {
            const p = m.getPosition();
            return p && p.lat() === venue.lat && p.lng() === venue.lng;
          });
          if (marker) google.maps.event.trigger(marker, "click");
        });
      });
    }

    highlightCard(id) {
      if (!this.listEl) return;
      this.listEl.querySelectorAll(".trivia-venue-card").forEach((c) => c.classList.remove("highlighted"));
      const card = this.listEl.querySelector(`.trivia-venue-card[data-venue-id="${id}"]`);
      if (card) {
        card.classList.add("highlighted");
        card.scrollIntoView({ behavior: "smooth", block: "nearest" });
      }
    }
  }

  function discoverInstances() {
    document.querySelectorAll(".trivia-finder-container").forEach((root) => {
      if (root.__triviaFinderBound) return;
      root.__triviaFinderBound = true;
      instances.push(new TriviaFinderInstance(root));
      console.log("Trivia Finder: Instance discovered");
    });
  }

  function initAllIfReady() {
    if (!googleReady || typeof google === 'undefined' || !google.maps) return;

    instances.forEach((inst) => {
      if (!inst.map && inst.mapEl) inst.initMap();
    });
  }

  window.triviaFinderInitMap = function () {
    console.log("Trivia Finder: Google Maps callback fired");
    googleReady = true;
    discoverInstances();
    initAllIfReady();
    if (instances.length === 0) retryDiscovery();
  };

  function retryDiscovery() {
    if (++initAttempts >= MAX_INIT_ATTEMPTS) return;
    discoverInstances();
    if (instances.length > 0) {
      console.log("Trivia Finder: Found on retry #" + initAttempts);
      initAllIfReady();
    } else {
      setTimeout(retryDiscovery, 100);
    }
  }

  function boot() {
    console.log("Trivia Finder: Boot");
    discoverInstances();
    if (window.google && window.google.maps) {
      googleReady = true;
      initAllIfReady();
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }

  window.addEventListener("load", function () {
    if (instances.length === 0) boot();
  });

})();