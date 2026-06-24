<?php
?>

<div class="gradient-header">
    <h2>Map Kendaraan</h2>
    <p>Menampilkan lokasi terakhir kendaraan.</p>
</div>

<div class="content">
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="timelineVehicle" class="form-label">Kendaraan</label>
                    <select id="timelineVehicle" class="form-control">
                        <option value="">Pilih kendaraan</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="timelineDate" class="form-label">Tanggal</label>
                    <input type="date" id="timelineDate" class="form-control">
                </div>
                <div class="col-md-4">
                    <button id="btnShowTimeline" class="btn btn-primary">Lihat Lini Masa Harian</button>
                    <button id="btnClearTimeline" class="btn btn-outline-secondary">Reset Lini Masa</button>
                </div>
            </div>
            <div class="row g-3 align-items-center mt-2">
                <div class="col-md-4">
                    <button id="btnPlayTimeline" class="btn btn-success" disabled>Play</button>
                    <button id="btnPauseTimeline" class="btn btn-warning" disabled>Pause</button>
                    <button id="btnStopTimeline" class="btn btn-danger" disabled>Stop</button>
                </div>
                <div class="col-md-8">
                    <div id="playbackInfo" class="timeline-playback-info text-muted small">Animasi belum dimulai.</div>
                </div>
            </div>
            <div id="timelineStatus" class="mt-3 text-muted small"></div>
            <div id="timelineSummary" class="mt-2"></div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div id="map" style="height:600px; width:100%;"></div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-body">
            <h5 class="mb-3">Detail Lini Masa Harian</h5>
            <div id="timelineList" class="timeline-list text-muted">Pilih kendaraan dan tanggal, lalu klik "Lihat Lini Masa Harian".</div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const map = L.map('map').setView([-7.8, 110.4], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const markers = {};
    const latestByVehicle = {};
    const timelineLayer = L.layerGroup().addTo(map);
    let timelinePolyline = null;
    let hasAutoFitted = false;

    const timelineVehicle = document.getElementById('timelineVehicle');
    const timelineDate = document.getElementById('timelineDate');
    const btnShowTimeline = document.getElementById('btnShowTimeline');
    const btnClearTimeline = document.getElementById('btnClearTimeline');
    const btnPlayTimeline = document.getElementById('btnPlayTimeline');
    const btnPauseTimeline = document.getElementById('btnPauseTimeline');
    const btnStopTimeline = document.getElementById('btnStopTimeline');
    const timelineStatus = document.getElementById('timelineStatus');
    const timelineSummary = document.getElementById('timelineSummary');
    const timelineList = document.getElementById('timelineList');
    const playbackInfo = document.getElementById('playbackInfo');

    timelineDate.value = new Date().toISOString().slice(0, 10);

    let timelinePoints = [];
    let timelineLatLngs = [];
    let playbackTimer = null;
    let playbackIndex = 0;
    let playbackRunning = false;
    let playbackDistanceMeters = 0;
    let playbackSegmentMeters = 0;
    let playbackMarker = null;
    let playbackTrail = null;

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function buildPopupContent(position) {
        const title = position.display_label || position.device_name || position.no_polisi || position.no_reg || 'Lokasi Kendaraan';
        const user = position.user_label || position.user_name || '-';
        const locator = position.locator || '-';
        const deviceUid = position.device_uid || '-';
        const deviceId = position.device_id || '-';
        const updated = position.updated_at || position.device_time || '-';
        return `
            <div>
                <strong>${escapeHtml(title)}</strong><br>
                <div>Pengguna: ${escapeHtml(user)}</div>
                <div>Locator: ${escapeHtml(locator)}</div>
                <div>Device UID: ${escapeHtml(deviceUid)}</div>
                <div>Device ID: ${escapeHtml(deviceId)}</div>
                <small>Updated: ${escapeHtml(updated)}</small>
            </div>
        `;
    }

    function toVehicleLabel(position) {
        return position.no_polisi || position.no_reg || position.display_label || position.device_name || ('Device ' + (position.device_id || '-'));
    }

    function haversineMeters(lat1, lon1, lat2, lon2) {
        const earthRadius = 6371000;
        const toRad = (deg) => (deg * Math.PI) / 180;
        const dLat = toRad(lat2 - lat1);
        const dLon = toRad(lon2 - lon1);
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
            + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2))
            * Math.sin(dLon / 2) * Math.sin(dLon / 2);
        return 2 * earthRadius * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function formatDistance(meters) {
        const value = Number.isFinite(meters) ? meters : 0;
        return value >= 1000 ? `${(value / 1000).toFixed(2)} km` : `${value.toFixed(0)} m`;
    }

    function setPlaybackButtons(enabled, running) {
        btnPlayTimeline.disabled = !enabled || running;
        btnPauseTimeline.disabled = !enabled || !running;
        btnStopTimeline.disabled = !enabled;
    }

    function updatePlaybackInfo() {
        const total = timelineLatLngs.length;
        if (!total) {
            playbackInfo.textContent = 'Animasi belum dimulai.';
            return;
        }

        const currentPoint = timelinePoints[Math.max(0, Math.min(playbackIndex, timelinePoints.length - 1))];
        const currentTime = currentPoint?.device_time || currentPoint?.server_time || '-';
        playbackInfo.innerHTML = `
            Titik ${Math.min(playbackIndex + 1, total)} / ${total} | 
            Jarak tempuh: <strong>${escapeHtml(formatDistance(playbackDistanceMeters))}</strong> | 
            Jarak segmen terakhir: <strong>${escapeHtml(formatDistance(playbackSegmentMeters))}</strong> | 
            Waktu: <strong>${escapeHtml(currentTime)}</strong>
        `;
    }

    function stopPlayback(keepTrail = false) {
        if (playbackTimer) {
            clearInterval(playbackTimer);
            playbackTimer = null;
        }
        playbackRunning = false;
        playbackIndex = 0;
        playbackDistanceMeters = 0;
        playbackSegmentMeters = 0;
        if (playbackMarker) {
            timelineLayer.removeLayer(playbackMarker);
            playbackMarker = null;
        }
        if (playbackTrail && !keepTrail) {
            timelineLayer.removeLayer(playbackTrail);
            playbackTrail = null;
        }
        setPlaybackButtons(timelineLatLngs.length > 0, false);
        updatePlaybackInfo();
    }

    function pausePlayback() {
        if (playbackTimer) {
            clearInterval(playbackTimer);
            playbackTimer = null;
        }
        playbackRunning = false;
        setPlaybackButtons(timelineLatLngs.length > 0, false);
        timelineStatus.textContent = 'Animasi dijeda.';
    }

    function stepPlayback() {
        if (!timelineLatLngs.length) {
            stopPlayback();
            return;
        }

        if (playbackIndex >= timelineLatLngs.length) {
            pausePlayback();
            timelineStatus.textContent = 'Animasi selesai.';
            return;
        }

        const currentLatLng = timelineLatLngs[playbackIndex];
        const currentPoint = timelinePoints[playbackIndex];

        if (!playbackTrail) {
            playbackTrail = L.polyline([], {
                color: '#2a9d8f',
                weight: 5,
                opacity: 0.85,
                dashArray: '6 8',
            }).addTo(timelineLayer);
        }

        if (!playbackMarker) {
            playbackMarker = L.circleMarker(currentLatLng, {
                radius: 8,
                color: '#111827',
                weight: 2,
                fillColor: '#f59e0b',
                fillOpacity: 1,
            }).addTo(timelineLayer);
        } else {
            playbackMarker.setLatLng(currentLatLng);
        }

        playbackTrail.addLatLng(currentLatLng);

        if (playbackIndex > 0) {
            const prev = timelineLatLngs[playbackIndex - 1];
            const segment = haversineMeters(prev[0], prev[1], currentLatLng[0], currentLatLng[1]);
            playbackSegmentMeters = segment;
            playbackDistanceMeters += segment;
        } else {
            playbackSegmentMeters = 0;
        }

        playbackMarker.bindPopup(`
            <div>
                <strong>${escapeHtml(currentPoint.device_time || currentPoint.server_time || '-')}</strong><br>
                <div>Jarak tempuh: ${escapeHtml(formatDistance(playbackDistanceMeters))}</div>
                <div>Jarak segmen: ${escapeHtml(formatDistance(playbackSegmentMeters))}</div>
                <div>Speed: ${escapeHtml((Number(currentPoint.speed) || 0).toFixed(1))} km/h</div>
            </div>
        `);

        if (playbackIndex === 0) {
            playbackMarker.openPopup();
        }

        playbackIndex += 1;
        updatePlaybackInfo();
        setPlaybackButtons(timelineLatLngs.length > 0, true);
    }

    function startPlayback() {
        if (!timelineLatLngs.length) {
            timelineStatus.textContent = 'Tidak ada data timeline untuk diputar.';
            return;
        }

        if (playbackRunning) {
            return;
        }

        if (!playbackTrail) {
            playbackTrail = L.polyline([], {
                color: '#2a9d8f',
                weight: 5,
                opacity: 0.85,
                dashArray: '6 8',
            }).addTo(timelineLayer);
        }

        playbackRunning = true;
        timelineStatus.textContent = 'Animasi berjalan...';
        setPlaybackButtons(timelineLatLngs.length > 0, true);

        if (playbackIndex === 0) {
            stepPlayback();
        }

        playbackTimer = setInterval(() => {
            stepPlayback();
            if (playbackIndex >= timelineLatLngs.length) {
                stopPlayback(true);
                timelineStatus.textContent = 'Animasi selesai.';
            }
        }, 1200);
    }

    function refreshVehicleOptions(data) {
        const selected = timelineVehicle.value;
        const options = ['<option value="">Pilih kendaraan</option>'];
        const seen = new Set();

        data.forEach((p) => {
            if (!p.vehicle_id) return;
            if (seen.has(String(p.vehicle_id))) return;
            seen.add(String(p.vehicle_id));

            latestByVehicle[String(p.vehicle_id)] = p;
            const label = toVehicleLabel(p);
            options.push(`<option value="${escapeHtml(String(p.vehicle_id))}">${escapeHtml(label)}</option>`);
        });

        timelineVehicle.innerHTML = options.join('');
        if (selected && seen.has(selected)) {
            timelineVehicle.value = selected;
        }
    }

    function renderTimelineList(points) {
        if (!Array.isArray(points) || points.length === 0) {
            timelineList.innerHTML = '<div class="text-muted">Tidak ada titik perjalanan pada tanggal ini.</div>';
            return;
        }

        const rows = points.slice(0, 120).map((pt, idx) => {
            const timeText = pt.device_time || pt.server_time || '-';
            const speed = Number.isFinite(Number(pt.speed)) ? Number(pt.speed).toFixed(1) : '0.0';
            const lat = Number(pt.lat).toFixed(6);
            const lon = Number(pt.lon).toFixed(6);
            return `
                <div class="timeline-row">
                    <div class="timeline-index">${idx + 1}</div>
                    <div>
                        <div><strong>${escapeHtml(timeText)}</strong></div>
                        <div class="text-muted">Lat/Lon: ${escapeHtml(lat)}, ${escapeHtml(lon)} | Speed: ${escapeHtml(speed)} km/h</div>
                    </div>
                </div>
            `;
        });

        const moreText = points.length > 120
            ? `<div class="text-muted small mt-2">Menampilkan 120 dari ${points.length} titik.</div>`
            : '';

        timelineList.innerHTML = `<div class="timeline-rows">${rows.join('')}</div>${moreText}`;
    }

    function clearTimelineOverlay() {
        stopPlayback();
        timelineLayer.clearLayers();
        timelinePolyline = null;
        timelinePoints = [];
        timelineLatLngs = [];
        timelineSummary.innerHTML = '';
        timelineStatus.textContent = 'Lini masa dibersihkan.';
        timelineList.innerHTML = '<div class="text-muted">Pilih kendaraan dan tanggal, lalu klik "Lihat Lini Masa Harian".</div>';
        timelineSummary.innerHTML = '';
        playbackInfo.textContent = 'Animasi belum dimulai.';
    }

    async function showDailyTimeline() {
        const vehicleId = timelineVehicle.value;
        const dateValue = timelineDate.value;
        if (!vehicleId) {
            timelineStatus.textContent = 'Pilih kendaraan terlebih dahulu.';
            return;
        }
        if (!dateValue) {
            timelineStatus.textContent = 'Pilih tanggal terlebih dahulu.';
            return;
        }

        timelineStatus.textContent = 'Mengambil lini masa harian...';
        timelineSummary.innerHTML = '';
        timelineList.innerHTML = '<div class="text-muted">Memuat data...</div>';

        try {
            const qs = new URLSearchParams({ vehicle_id: vehicleId, date: dateValue });
            const res = await fetch('ajax/traccar_daily_timeline.php?' + qs.toString(), { credentials: 'same-origin' });
            if (!res.ok) throw new Error('Gagal memuat endpoint timeline');

            const payload = await res.json();
            if (!payload.success) {
                throw new Error(payload.message || 'Gagal memuat timeline');
            }

            const points = Array.isArray(payload.points) ? payload.points : [];
            const latLngs = points
                .map((pt) => [Number(pt.lat), Number(pt.lon)])
                .filter((xy) => Number.isFinite(xy[0]) && Number.isFinite(xy[1]));

            timelinePoints = points;
            timelineLatLngs = latLngs;
            stopPlayback(true);

            timelineLayer.clearLayers();
            timelinePolyline = null;

            if (latLngs.length > 0) {
                timelinePolyline = L.polyline(latLngs, {
                    color: '#e63946',
                    weight: 4,
                    opacity: 0.9,
                }).addTo(timelineLayer);

                const startMarker = L.circleMarker(latLngs[0], {
                    radius: 7,
                    color: '#1d3557',
                    fillColor: '#1d3557',
                    fillOpacity: 1,
                }).addTo(timelineLayer).bindPopup('Titik awal perjalanan');

                const endMarker = L.circleMarker(latLngs[latLngs.length - 1], {
                    radius: 7,
                    color: '#2a9d8f',
                    fillColor: '#2a9d8f',
                    fillOpacity: 1,
                }).addTo(timelineLayer).bindPopup('Titik akhir perjalanan');

                const step = Math.max(1, Math.floor(latLngs.length / 40));
                for (let i = 0; i < latLngs.length; i += step) {
                    const point = points[i];
                    L.circleMarker(latLngs[i], {
                        radius: 3,
                        color: '#457b9d',
                        fillColor: '#457b9d',
                        fillOpacity: 0.8,
                        weight: 1,
                    }).addTo(timelineLayer).bindPopup(`
                        <div>
                            <strong>${escapeHtml(point.device_time || point.server_time || '-')}</strong><br>
                            Speed: ${escapeHtml((Number(point.speed) || 0).toFixed(1))} km/h
                        </div>
                    `);
                }

                map.fitBounds(timelinePolyline.getBounds(), { padding: [24, 24], maxZoom: 16 });
                startMarker.bringToFront();
                endMarker.bringToFront();
            }

            const vehicleLabel = payload.vehicle?.label || 'Kendaraan';
            const summary = payload.summary || {};
            timelineSummary.innerHTML = `
                <span class="badge badge-primary timeline-badge">${escapeHtml(vehicleLabel)}</span>
                <span class="badge badge-secondary timeline-badge">Tanggal: ${escapeHtml(payload.date || dateValue)}</span>
                <span class="badge badge-info timeline-badge">Titik: ${escapeHtml(String(summary.point_count ?? 0))}</span>
                <span class="badge badge-success timeline-badge">Jarak: ${escapeHtml(String(summary.distance_km ?? 0))} km</span>
            `;

            timelineStatus.textContent = points.length > 0
                ? 'Lini masa harian berhasil ditampilkan.'
                : 'Tidak ada pergerakan pada tanggal tersebut.';

            renderTimelineList(points);
            setPlaybackButtons(timelineLatLngs.length > 0, false);
            updatePlaybackInfo();
        } catch (err) {
            console.error('showDailyTimeline error', err);
            timelineStatus.textContent = 'Gagal memuat lini masa: ' + (err?.message || 'unknown error');
            timelineSummary.innerHTML = '';
            timelineList.innerHTML = '<div class="text-danger">Tidak dapat memuat data lini masa.</div>';
            stopPlayback();
        }
    }

    async function fetchPositions(){
        try {
            const url = 'ajax/traccar_positions.php?live=1&max_age=4&_ts=' + Date.now();
            const res = await fetch(url, {credentials: 'same-origin', cache: 'no-store'});
            if (!res.ok) throw new Error('Network response not ok');
            const data = await res.json();
            const bounds = [];
            refreshVehicleOptions(Array.isArray(data) ? data : []);

            data.forEach(p => {
                const id = p.device_id || p.device_uid || p.id;
                const lat = parseFloat(p.latitude);
                const lon = parseFloat(p.longitude);
                if (!Number.isFinite(lat) || !Number.isFinite(lon)) return;
                const popupContent = buildPopupContent(p);
                bounds.push([lat, lon]);

                if (!markers[id]) {
                    const mk = L.marker([lat, lon]).addTo(map).bindPopup(popupContent);
                    markers[id] = mk;
                } else {
                    markers[id].setLatLng([lat, lon]);
                    markers[id].getPopup().setContent(popupContent);
                }
            });

            if (!hasAutoFitted && bounds.length > 0) {
                map.fitBounds(bounds, { padding: [30, 30], maxZoom: 16 });
                hasAutoFitted = true;
            }
        } catch (e) {
            console.error('fetchPositions error', e);
        }
    }

    btnShowTimeline.addEventListener('click', function () {
        showDailyTimeline();
    });

    btnClearTimeline.addEventListener('click', function () {
        clearTimelineOverlay();
    });

    btnPlayTimeline.addEventListener('click', function () {
        startPlayback();
    });

    btnPauseTimeline.addEventListener('click', function () {
        pausePlayback();
    });

    btnStopTimeline.addEventListener('click', function () {
        stopPlayback(true);
        timelineStatus.textContent = 'Animasi dihentikan.';
    });

    fetchPositions();
    setInterval(fetchPositions, 5000);
});
</script>
