<?php
session_start();
require_once 'config/database.php';
require_once 'location_services.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nearby Events - EventHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        #map {
            height: 400px;
            width: 100%;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .event-card {
            transition: transform 0.2s;
        }
        .event-card:hover {
            transform: translateY(-5px);
        }
        .distance-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Nearby Events</h5>
                        <div class="mb-3">
                            <label for="radius" class="form-label">Search Radius (km)</label>
                            <input type="range" class="form-range" id="radius" min="1" max="50" value="10">
                            <div class="text-center" id="radiusValue">10 km</div>
                        </div>
                        <div id="map"></div>
                        <div id="eventsList" class="row"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Your Location</h5>
                        <div id="locationInfo" class="mb-3">
                            <p class="text-muted">Getting your location...</p>
                        </div>
                        <button id="updateLocation" class="btn btn-primary w-100">
                            <i class="fas fa-location-arrow"></i> Update Location
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    let map;
    let markers = [];
    let userMarker;
    let currentLat;
    let currentLng;
    let currentRadius = 10;

    // Initialize map
    function initMap() {
        try {
            map = L.map('map').setView([0, 0], 12);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            // Try to get user's location
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        currentLat = position.coords.latitude;
                        currentLng = position.coords.longitude;
                        updateLocationInfo(currentLat, currentLng);
                        updateMap();
                    },
                    (error) => {
                        console.error('Error getting location:', error);
                        document.getElementById('locationInfo').innerHTML = 
                            '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Unable to get your location. Please enable location services.</div>';
                    }
                );
            } else {
                document.getElementById('locationInfo').innerHTML = 
                    '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Geolocation is not supported by your browser.</div>';
            }
        } catch (error) {
            console.error('Error initializing map:', error);
            document.getElementById('map').innerHTML = `
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Unable to load the map. Please check your internet connection and try again.
                </div>
            `;
        }
    }

    // Update location information
    function updateLocationInfo(lat, lng) {
        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('locationInfo').innerHTML = `
                    <p><strong>Address:</strong> ${data.display_name}</p>
                    <p><strong>Coordinates:</strong> ${lat.toFixed(6)}, ${lng.toFixed(6)}</p>
                `;
            })
            .catch(error => {
                console.error('Error getting address:', error);
                document.getElementById('locationInfo').innerHTML = `
                    <p><strong>Coordinates:</strong> ${lat.toFixed(6)}, ${lng.toFixed(6)}</p>
                `;
            });
    }

    // Update map and markers
    function updateMap() {
        // Clear existing markers
        markers.forEach(marker => marker.remove());
        markers = [];

        // Update user marker
        if (userMarker) {
            userMarker.remove();
        }
        userMarker = L.marker([currentLat, currentLng], {
            icon: L.divIcon({
                className: 'user-location-marker',
                html: '<div style="background-color: #4285F4; width: 12px; height: 12px; border-radius: 50%; border: 2px solid white;"></div>',
                iconSize: [12, 12]
            })
        }).addTo(map);

        // Center map on user
        map.setView([currentLat, currentLng]);

        // Get nearby events
        const formData = new FormData();
        formData.append('get_nearby_events', '1');
        formData.append('lat', currentLat);
        formData.append('lng', currentLng);
        formData.append('radius', currentRadius);

        fetch('location_services.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            const events = data.events || [];
            const debug = data.debug || {};
            
            // Remove debug information display
            const debugInfo = document.getElementById('debugInfo');
            if (debugInfo) {
                debugInfo.remove();
            }
            
            // Add event markers
            events.forEach(event => {
                if (event.latitude && event.longitude) {
                    const marker = L.marker([event.latitude, event.longitude]).addTo(map);
                    
                    marker.bindPopup(`
                        <div>
                            <h5>${event.title}</h5>
                            <p>${event.location}</p>
                            <a href="event_details.php?id=${event.id}" class="btn btn-sm btn-primary">View Details</a>
                        </div>
                    `);
                    
                    markers.push(marker);
                }
            });

            // Update events list
            const eventsList = document.getElementById('eventsList');
            eventsList.innerHTML = '';

            if (events.length === 0) {
                eventsList.innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> No events found within ${currentRadius} km of your location.
                            ${debug.events_with_coords === 0 ? ' No events in the database have coordinates. Please add coordinates to events first.' : ''}
                        </div>
                    </div>
                `;
                return;
            }

            // Log events to console for debugging
            console.log('Events found:', events);
            
            events.forEach(event => {
                console.log('Processing event:', event);
                const card = document.createElement('div');
                card.className = 'col-md-6 mb-4';
                
                const distanceText = event.distance === 999999 ? 
                    '<span class="badge bg-secondary">Distance unknown</span>' : 
                    `<span class="badge bg-info distance-badge">${event.distance.toFixed(1)} km away</span>`;
                
                card.innerHTML = `
                    <div class="card event-card h-100">
                        ${distanceText}
                        ${event.image_path ? `
                            <img src="${event.image_path}" class="card-img-top" alt="${event.title}" 
                                 style="height: 200px; object-fit: cover;">
                        ` : ''}
                        <div class="card-body">
                            <h5 class="card-title">${event.title}</h5>
                            <p class="card-text">
                                <small class="text-muted">
                                    <i class="fas fa-calendar"></i> ${new Date(event.date).toLocaleDateString()}<br>
                                    <i class="fas fa-map-marker-alt"></i> ${event.location}<br>
                                    <i class="fas fa-tag"></i> ${event.category_name || 'Uncategorized'}
                                </small>
                            </p>
                            ${event.avg_rating ? `
                                <div class="text-warning mb-2">
                                    ${Array(5).fill().map((_, i) => 
                                        `<i class="fas fa-star${i < Math.round(event.avg_rating) ? '' : '-o'}"></i>`
                                    ).join('')}
                                    <small class="text-muted">(${parseFloat(event.avg_rating).toFixed(1)})</small>
                                </div>
                            ` : ''}
                            <a href="event_details.php?id=${event.id}" class="btn btn-primary">View Details</a>
                        </div>
                    </div>
                `;
                eventsList.appendChild(card);
            });
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('eventsList').innerHTML = `
                <div class="col-12">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> Error loading events: ${error.message}
                    </div>
                </div>
            `;
        });
    }

    // Event listeners
    document.getElementById('radius').addEventListener('input', function() {
        currentRadius = this.value;
        document.getElementById('radiusValue').textContent = `${currentRadius} km`;
        updateMap();
    });

    document.getElementById('updateLocation').addEventListener('click', function() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    currentLat = position.coords.latitude;
                    currentLng = position.coords.longitude;
                    updateLocationInfo(currentLat, currentLng);
                    updateMap();
                },
                (error) => {
                    console.error('Error getting location:', error);
                    alert('Unable to get your location. Please enable location services.');
                }
            );
        }
    });

    // Initialize map when page loads
    window.onload = initMap;
    </script>
</body>
</html> 