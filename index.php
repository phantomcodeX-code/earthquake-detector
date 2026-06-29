<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

// Get current settings
$minMag     = Database::getSetting('min_magnitude', '2.5');
$alertMag   = Database::getSetting('alert_magnitude', '4.5');
$interval   = Database::getSetting('fetch_interval', '60');
$phFocus    = Database::getSetting('philippines_focus', '1');
$phMinMag   = Database::getSetting('ph_min_mag', '2.0');
$alertSound = Database::getSetting('alert_sound', '1');
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SeismoWatch — Real-Time Earthquake Detector</title>
  <meta name="description" content="Real-time earthquake monitoring for the Philippines and worldwide.">

  <!-- Leaflet Map -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

  <!-- App CSS -->
  <link rel="stylesheet" href="assets/css/app.css">

  <!-- Inject settings for JS -->
  <script>
    window.SW_CONFIG = {
      alertMag:    <?= (float)$alertMag ?>,
      phMinMag:    <?= (float)$phMinMag ?>,
      interval:    <?= (int)$interval * 1000 ?>,
      phFocus:     <?= $phFocus === '1' ? 'true' : 'false' ?>,
      soundEnabled:<?= $alertSound === '1' ? 'true' : 'false' ?>,
    };
  </script>
</head>

<body>

<!-- ══════════════════════════════════════════
     TSUNAMI BANNER
     ══════════════════════════════════════════ -->
<div id="tsunamiBanner" class="tsunami-banner"></div>

<!-- ══════════════════════════════════════════
     SIDEBAR
     ══════════════════════════════════════════ -->
<nav class="sidebar">
  <div class="sidebar-logo">
    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
      <path d="M2 12h2l2-6 3 12 2-9 2 6 2-3h5"/>
    </svg>
  </div>

  <div class="nav-item active" title="Dashboard">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <rect x="3" y="3" width="7" height="7" rx="1"/>
      <rect x="14" y="3" width="7" height="7" rx="1"/>
      <rect x="3" y="14" width="7" height="7" rx="1"/>
      <rect x="14" y="14" width="7" height="7" rx="1"/>
    </svg>
    <span class="nav-tooltip">Dashboard</span>
  </div>

  <div class="sidebar-bottom">
    <div class="live-dot" id="liveDot" title="Live Feed"></div>
  </div>
</nav>

<!-- ══════════════════════════════════════════
     APP WRAPPER
     ══════════════════════════════════════════ -->
<div class="app-wrapper">

  <!-- Top Bar -->
  <header class="topbar">
    <div class="topbar-brand">
      <h1>SeismoWatch</h1>
      <span>v1.0</span>
    </div>
    <div id="statusBadge" class="status-badge">◌ LOADING</div>
    <div class="topbar-time" id="topbarTime">—</div>
    <button class="topbar-btn" id="refreshBtn" onclick="SW.fetchUpdates()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round"
          d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
      </svg>
      Refresh
    </button>
  </header>

  <!-- Dashboard Grid -->
  <main class="dashboard">

    <!-- ── Stat Cards ── -->
    <div class="stats-row">
      <div class="stat-card alert">
        <div class="stat-label">Last 24 Hours</div>
        <div class="stat-value red" id="stat24h">—</div>
        <div class="stat-sub">total events</div>
      </div>
      <div class="stat-card info">
        <div class="stat-label">Last Hour</div>
        <div class="stat-value blue" id="stat1h">—</div>
        <div class="stat-sub">recent events</div>
      </div>
      <div class="stat-card warn">
        <div class="stat-label">Max Magnitude (24h)</div>
        <div class="stat-value amber" id="statMaxMag">—</div>
        <div class="stat-sub">richter scale</div>
      </div>
      <div class="stat-card ok">
        <div class="stat-label">Tsunami Alerts (7d)</div>
        <div class="stat-value green" id="statTsunami">—</div>
        <div class="stat-sub">warnings issued</div>
      </div>
      <div class="stat-card ph">
        <div class="stat-label">🇵🇭 PH Events (24h)</div>
        <div class="stat-value cyan" id="statPH">—</div>
        <div class="stat-sub">philippine region</div>
      </div>
    </div>

    <!-- ── Waveform Header ── -->
    <div class="waveform-header">
      <div style="min-width:120px">
        <div style="font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px">Seismic Activity</div>
        <div style="font-size:12px;font-family:var(--font-mono);color:var(--text-secondary)" id="waveformLabel">Live Waveform</div>
      </div>
      <div class="waveform-canvas-wrap">
        <canvas id="waveformCanvas"></canvas>
      </div>
      <div class="waveform-info">
        <div class="label">Latest Event</div>
        <div class="latest-mag" id="latestMag">—</div>
        <div class="latest-place" id="latestPlace">Loading data...</div>
        <div class="latest-time" id="latestTime">—</div>
      </div>
    </div>

    <!-- ── Earthquake List Panel ── -->
    <div class="eq-panel">
      <div class="panel-header">
        <div class="panel-title">Earthquake Feed</div>
        <span class="panel-badge" id="eqCount">0</span>
        <div class="spinner" id="listSpinner" style="display:none"></div>
      </div>
      <div class="panel-filter">
        <button class="filter-btn active" data-filter="all">All</button>
        <button class="filter-btn" data-filter="1h">Last Hour</button>
        <button class="filter-btn" data-filter="ph">🇵🇭 Philippines</button>
        <button class="filter-btn" data-filter="major">M5.0+</button>
        <button class="filter-btn" data-filter="tsunami">Tsunami</button>
      </div>
      <div class="eq-list" id="eqList">
        <div class="eq-empty">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round"
              d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
          </svg>
          <p>Fetching earthquake data...</p>
        </div>
      </div>
    </div>

    <!-- ── Right Column ── -->
    <div class="right-col">

      <!-- Map -->
      <div class="map-panel">
        <div class="panel-header">
          <div class="panel-title">Seismic Map</div>
          <span class="panel-badge">Live</span>
        </div>
        <div id="earthquakeMap"></div>
        <div class="map-toolbar" id="mapToolbar">
          <button class="map-toolbar-btn" id="btnLayers">⊞ Layers ▾</button>
          <button class="map-toolbar-btn" id="btnResetView">⌖ Reset</button>
          <button class="map-toolbar-btn" id="btnFullscreen">⛶ Fullscreen</button>
          <div class="map-toolbar-sep"></div>
          <select class="map-toolbar-select" id="mapTimeFilter">
            <option value="1">Last 1 hour</option>
            <option value="6">Last 6 hours</option>
            <option value="24" selected>Last 24 hours</option>
            <option value="168">Last 7 days</option>
          </select>
        </div>

        <!-- Layers dropdown (hidden by default) -->
        <div class="map-layers-dropdown" id="mapLayersDropdown" style="display:none">
          <label><input type="checkbox" id="layerMarkers" checked> Earthquake Markers</label>
          <label><input type="checkbox" id="layerPHBox" checked> Philippines Region</label>
          <label><input type="checkbox" id="layerFaultLines"> Fault Lines</label>
          <label><input type="checkbox" id="layerDepthRings"> Depth Rings</label>
        </div>

        <div class="map-legend">
          <div class="legend-item">
            <div class="legend-dot legend-extreme"></div> Extreme
          </div>
          <div class="legend-item">
            <div class="legend-dot legend-strong"></div> Strong
          </div>
          <div class="legend-item">
            <div class="legend-dot legend-light"></div> Light
          </div>
          <div class="legend-item">
            <div class="legend-dot legend-micro"></div> Micro
          </div>
          <div class="legend-item">
            <div class="legend-dot legend-ph"></div> PH Region
          </div>
        </div>
      </div>

      <!-- Activity Feed -->
      <div class="activity-panel">
        <div class="panel-header">
          <div class="panel-title">Alert Activity</div>
          <span class="panel-badge">Log</span>
        </div>
        <div class="activity-list" id="activityList">
          <div class="eq-empty"><p style="font-size:12px">No alerts yet — monitoring live...</p></div>
        </div>
      </div>

    </div><!-- /right-col -->

  </main>

</div><!-- /app-wrapper -->

<!-- ══════════════════════════════════════════
     NOTIFICATION CONTAINER
     ══════════════════════════════════════════ -->
<div id="notifContainer"></div>

<!-- ══════════════════════════════════════════
     EARTHQUAKE DETAIL MODAL
     ══════════════════════════════════════════ -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal">
    <div class="modal-top">
      <div>
        <div style="font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--text-muted);margin-bottom:6px">MAGNITUDE</div>
        <div class="modal-mag" id="modalMag">—</div>
      </div>
      <div class="modal-info">
        <div class="modal-type" id="modalType">EARTHQUAKE</div>
        <h3 id="modalPlace">Loading...</h3>
        <div style="font-family:var(--font-mono);font-size:12px;color:var(--text-muted)" id="modalTime">—</div>
      </div>
    </div>
    <div class="modal-body">
      <div class="detail-row">
        <div class="detail-label">Latitude</div>
        <div class="detail-value" id="modalLat">—</div>
      </div>
      <div class="detail-row">
        <div class="detail-label">Longitude</div>
        <div class="detail-value" id="modalLon">—</div>
      </div>
      <div class="detail-row">
        <div class="detail-label">Depth</div>
        <div class="detail-value" id="modalDepth">—</div>
      </div>
      <div class="detail-row">
        <div class="detail-label">Severity</div>
        <div class="detail-value" id="modalSeverity">—</div>
      </div>
      <div class="detail-row">
        <div class="detail-label">Felt Reports</div>
        <div class="detail-value" id="modalFelt">—</div>
      </div>
      <div class="detail-row">
        <div class="detail-label">Tsunami Warning</div>
        <div class="detail-value" id="modalTsunami">—</div>
      </div>
    </div>
    <div class="modal-footer">
      <a href="#" target="_blank" class="btn-primary" id="modalUsgsLink">View on USGS →</a>
      <button class="btn-secondary" onclick="SW.closeModal()">Close</button>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════
     SETTINGS DRAWER
     ══════════════════════════════════════════ -->
<div class="settings-overlay" id="settingsOverlay">
  <div class="settings-drawer">
    <div class="settings-header">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
           style="width:18px;height:18px;color:var(--text-muted)">
        <path stroke-linecap="round" stroke-linejoin="round"
          d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
      </svg>
      <h2>Monitor Settings</h2>
      <button class="close-btn" onclick="SW.closeSettings()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             style="width:16px;height:16px">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>
    <div class="settings-body">

      <div class="settings-section">
        <div class="settings-section-title">Alert Thresholds</div>
        <div class="form-group">
          <label class="form-label" for="setAlertMag">
            Alert Popup — Minimum Magnitude
          </label>
          <input class="form-input" type="number" id="setAlertMag"
                 value="<?= htmlspecialchars($alertMag) ?>"
                 min="0" max="10" step="0.1">
        </div>
        <div class="form-group">
          <label class="form-label" for="setPhMinMag">
            Philippines Region — Minimum Magnitude
          </label>
          <input class="form-input" type="number" id="setPhMinMag"
                 value="<?= htmlspecialchars($phMinMag) ?>"
                 min="0" max="10" step="0.1">
        </div>
      </div>

      <div class="settings-section">
        <div class="settings-section-title">Data & Polling</div>
        <div class="form-group">
          <label class="form-label" for="setInterval">
            Fetch Interval (seconds)
          </label>
          <input class="form-input" type="number" id="setInterval"
                 value="<?= htmlspecialchars($interval) ?>"
                 min="30" max="3600" step="30">
        </div>
      </div>

      <div class="settings-section">
        <div class="settings-section-title">Features</div>
        <div class="toggle-row">
          <span class="toggle-label">Alert Sound</span>
          <div class="toggle <?= $alertSound === '1' ? 'on' : '' ?>" id="toggleSound"></div>
        </div>
        <div class="toggle-row">
          <span class="toggle-label">Philippines Focus Mode</span>
          <div class="toggle <?= $phFocus === '1' ? 'on' : '' ?>" id="togglePH"></div>
        </div>
        <div class="toggle-row">
          <div style="display:flex;align-items:center;gap:8px">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 style="width:15px;height:15px;color:var(--text-muted)">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/>
            </svg>
            <span class="toggle-label">Dark Mode</span>
          </div>
          <div class="toggle on" id="toggleDarkMode"></div>
        </div>
      </div>

      <div class="settings-section">
        <div class="settings-section-title">Data Source</div>
        <div style="font-size:12px;color:var(--text-muted);line-height:1.7">
          <div>Source: <span style="color:var(--text-secondary)">USGS Earthquake Hazards Program</span></div>
          <div>Feed: <span style="color:var(--cyan);font-family:var(--font-mono);font-size:11px">GeoJSON Real-Time</span></div>
          <div style="margin-top:8px;padding:10px;background:var(--bg-card);border-radius:8px;border:1px solid var(--border)">
            Auto-fetches every <?= htmlspecialchars($interval) ?>s from USGS hourly + daily feeds.
            Philippines region uses lower magnitude threshold for more sensitive local monitoring.
          </div>
        </div>
      </div>

    </div>
    <div class="settings-footer">
      <button class="save-btn" onclick="SW.saveSettings()">Save Settings</button>
    </div>
  </div>
</div>

<!-- Global Toast -->
<div class="toast" id="globalToast">Settings saved</div>

<!-- App JS -->
<script src="assets/js/app.js"></script>

<!-- ══════════════════════════════════════════
     FLOATING CUSTOMIZE TAB (Phoenix-style)
     Fixed to the right edge, vertically centered
     ══════════════════════════════════════════ -->
<div class="customize-fab" id="customizeFab" onclick="SW.openSettings()">
  <div class="customize-fab-track">
    <div class="customize-fab-gear">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
        <path stroke-linecap="round" stroke-linejoin="round"
          d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
      </svg>
    </div>
    <span class="customize-fab-label">CUSTOMIZE</span>
  </div>
</div>

</body>
</html>
