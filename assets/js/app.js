/* =============================================
   SeismoWatch — Main JavaScript
   Real-time polling · Notifications · Waveform
   ============================================= */

'use strict';

const SW = {
  // ── Config ─────────────────────────────────
  pollInterval: 60000,   // ms, updated from settings
  alertMag:     4.5,
  phMinMag:     2.0,
  soundEnabled: true,
  phFocus:      true,
  currentFilter: 'all',
  earthquakes:  [],
  fetchTimer:   null,
  mapMarkers:   [],
  leafletMap:   null,
  lastFetchTime: null,
  isOnline:     true,
  selectedEq:   null,

  // ── Severity helper ─────────────────────────
  severity(mag) {
    if (mag >= 7.0) return 'extreme';
    if (mag >= 6.0) return 'severe';
    if (mag >= 5.0) return 'strong';
    if (mag >= 4.0) return 'moderate';
    if (mag >= 3.0) return 'light';
    return 'micro';
  },

  severityLabel(mag) {
    const s = this.severity(mag);
    return s.charAt(0).toUpperCase() + s.slice(1);
  },

  // ── Time formatting ─────────────────────────
  timeAgo(dateStr) {
    const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
    if (diff < 60) return diff + 's ago';
    if (diff < 3600) return Math.floor(diff/60) + 'm ago';
    if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
    return Math.floor(diff/86400) + 'd ago';
  },

  formatDate(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleString('en-PH', {
      month:'short', day:'numeric',
      hour:'2-digit', minute:'2-digit', hour12:true
    });
  },

  // ── Color helpers ────────────────────────────
  magColor(mag) {
    const sev = this.severity(mag);
    const colors = {
      extreme: '#FF2D20', severe: '#FF6B35',
      strong: '#F59E0B', moderate: '#EAB308',
      light: '#22C55E', micro: '#3B82F6'
    };
    return colors[sev] || '#3B82F6';
  },

  // ─────────────────────────────────────────────
  // INIT
  // ─────────────────────────────────────────────
  init() {
    this.initClock();
    this.initWaveform();
    this.initMap();
    this.initSettings();
    this.initSoundEngine();
    this.bindUI();
    this.loadInitialData();
    this.startPolling();
  },

  // ── Clock ────────────────────────────────────
  initClock() {
    const el = document.getElementById('topbarTime');
    const tick = () => {
      const now = new Date();
      el.textContent = now.toLocaleString('en-PH', {
        weekday:'short', month:'short', day:'numeric',
        hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:true
      }) + ' PHT';
    };
    tick();
    setInterval(tick, 1000);
  },

  // ─────────────────────────────────────────────
  // SEISMIC WAVEFORM CANVAS
  // ─────────────────────────────────────────────
  wavePhase: 0,
  waveAmplitude: 4,
  waveAnimId: null,

  initWaveform() {
    const canvas = document.getElementById('waveformCanvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');

    const resize = () => {
      canvas.width  = canvas.offsetWidth;
      canvas.height = 60;
    };
    resize();
    window.addEventListener('resize', resize);

    const draw = () => {
      const w = canvas.width, h = canvas.height;
      ctx.clearRect(0, 0, w, h);

      // Background grid lines
      ctx.strokeStyle = 'rgba(30,42,66,0.8)';
      ctx.lineWidth = 1;
      for (let y = 0; y <= h; y += 15) {
        ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(w, y); ctx.stroke();
      }

      // Main waveform
      const grad = ctx.createLinearGradient(0, 0, w, 0);
      grad.addColorStop(0, 'rgba(255,45,32,0)');
      grad.addColorStop(0.3, 'rgba(255,45,32,0.6)');
      grad.addColorStop(1, 'rgba(255,45,32,1)');

      ctx.strokeStyle = grad;
      ctx.lineWidth = 1.5;
      ctx.shadowColor = 'rgba(255,45,32,0.5)';
      ctx.shadowBlur = 6;
      ctx.beginPath();

      for (let x = 0; x < w; x++) {
        const t = x / w;
        // Compound wave: base + harmonics + noise envelope
        const base = Math.sin(t * 40 + this.wavePhase) * this.waveAmplitude;
        const h2   = Math.sin(t * 80 + this.wavePhase * 1.3) * (this.waveAmplitude * 0.4);
        const h3   = Math.sin(t * 12 + this.wavePhase * 0.7) * (this.waveAmplitude * 1.2);
        const env  = Math.sin(t * Math.PI) * 1.2;
        const y    = h / 2 + (base + h2 + h3) * env;
        x === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
      }
      ctx.stroke();
      ctx.shadowBlur = 0;

      // Scan line
      const scanX = (this.wavePhase * 12) % w;
      ctx.strokeStyle = 'rgba(59,130,246,0.4)';
      ctx.lineWidth = 1;
      ctx.setLineDash([2, 4]);
      ctx.beginPath(); ctx.moveTo(scanX, 0); ctx.lineTo(scanX, h); ctx.stroke();
      ctx.setLineDash([]);

      this.wavePhase += 0.04;
      this.waveAnimId = requestAnimationFrame(draw);
    };
    draw();
  },

  spikeWaveform(magnitude) {
    // Temporarily boost amplitude on new earthquake
    const original = this.waveAmplitude;
    const target = Math.min(original + magnitude * 3, 28);
    this.waveAmplitude = target;
    let steps = 0;
    const decay = setInterval(() => {
      this.waveAmplitude = Math.max(original, this.waveAmplitude - 0.4);
      steps++;
      if (steps > 40) clearInterval(decay);
    }, 80);
  },

  // ─────────────────────────────────────────────
  // LEAFLET MAP
  // ─────────────────────────────────────────────
  initMap() {
    if (!window.L) return;
    this.leafletMap = L.map('earthquakeMap', {
      center: [12, 122],
      zoom: 5,
      zoomControl: true,
      attributionControl: false,
    });

    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
      maxZoom: 18,
    }).addTo(this.leafletMap);

    // PH bounding box
    const phBounds = [[4.0, 116.0],[21.5, 127.0]];
    L.rectangle(phBounds, {
      color: '#06B6D4', weight: 1,
      fillColor: '#06B6D4', fillOpacity: 0.03,
      dashArray: '4,4'
    }).addTo(this.leafletMap);
  },

  updateMap(earthquakes) {
    if (!this.leafletMap) return;
    // Clear old markers
    this.mapMarkers.forEach(m => this.leafletMap.removeLayer(m));
    this.mapMarkers = [];

    earthquakes.forEach(eq => {
      const color  = this.magColor(parseFloat(eq.magnitude));
      const radius = Math.max(4, parseFloat(eq.magnitude) * 3);
      const marker = L.circleMarker([eq.latitude, eq.longitude], {
        radius,
        fillColor: color,
        color: color,
        weight: 1,
        opacity: 0.9,
        fillOpacity: 0.35,
      });
      marker.bindPopup(
        `<div style="font-family:Inter,sans-serif;font-size:12px;color:#E8EDF5;background:#151C2E;padding:4px 2px">
           <strong style="font-size:15px;color:${color}">M${parseFloat(eq.magnitude).toFixed(1)}</strong>
           <br>${eq.place}
           <br><span style="color:#8A99B8">${this.formatDate(eq.event_time)}</span>
           <br>Depth: ${parseFloat(eq.depth_km).toFixed(1)} km
         </div>`,
        { className: 'map-popup' }
      );
      marker.addTo(this.leafletMap);
      this.mapMarkers.push(marker);
    });
  },

  // ─────────────────────────────────────────────
  // SOUND ENGINE
  // ─────────────────────────────────────────────
  audioCtx: null,

  initSoundEngine() {
    document.addEventListener('click', () => {
      if (!this.audioCtx) {
        this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
      }
    }, { once: true });
  },

  playAlert(severity) {
    if (!this.soundEnabled || !this.audioCtx) return;
    const ctx = this.audioCtx;
    const now = ctx.currentTime;

    const freqs = {
      extreme:  [880, 660, 880, 660],
      severe:   [660, 440],
      strong:   [528, 440],
      moderate: [440],
      light:    [330],
      micro:    [220],
    };
    const durations = { extreme: 0.15, severe: 0.2, strong: 0.25, moderate: 0.3, light: 0.3, micro: 0.2 };
    const seq = freqs[severity] || [440];
    const dur = durations[severity] || 0.25;

    seq.forEach((freq, i) => {
      const osc  = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.connect(gain); gain.connect(ctx.destination);
      osc.type = severity === 'extreme' ? 'sawtooth' : 'sine';
      osc.frequency.setValueAtTime(freq, now + i * dur);
      gain.gain.setValueAtTime(0.25, now + i * dur);
      gain.gain.exponentialRampToValueAtTime(0.001, now + i * dur + dur - 0.01);
      osc.start(now + i * dur);
      osc.stop(now + i * dur + dur);
    });
  },

  // ─────────────────────────────────────────────
  // DATA LOADING & POLLING
  // ─────────────────────────────────────────────
  async loadInitialData() {
    this.setStatus('loading');
    try {
      const res  = await fetch('api/earthquakes.php?action=list&hours=24&limit=50');
      const data = await res.json();
      if (data.status === 'ok') {
        this.earthquakes = data.data;
        this.renderList();
        this.updateMap(this.earthquakes);
        this.updateStats();
      }
      this.setStatus('online');
    } catch(e) {
      this.setStatus('offline');
      console.error('Initial load failed:', e);
    }
  },

  async fetchUpdates() {
    this.setFetchIndicator(true);
    try {
      // 1. Trigger server-side USGS fetch
      const res  = await fetch('api/earthquakes.php?action=fetch');
      const data = await res.json();

      // 2. Show notifications for new alerts
      if (data.alerts && data.alerts.length > 0) {
        data.alerts.forEach(eq => {
          this.showNotification(eq);
          this.spikeWaveform(parseFloat(eq.magnitude));
          this.playAlert(this.severity(parseFloat(eq.magnitude)));
          this.addActivity(eq, 'new');
        });
      }

      // 3. Re-fetch list
      const listRes  = await fetch('api/earthquakes.php?action=list&hours=24&limit=50');
      const listData = await listRes.json();
      if (listData.status === 'ok') {
        const oldIds = new Set(this.earthquakes.map(e => e.usgs_id));
        const newOnes = listData.data.filter(e => !oldIds.has(e.usgs_id));
        this.earthquakes = listData.data;
        this.renderList(newOnes.map(e => e.usgs_id));
        this.updateMap(this.earthquakes);
        this.updateStats();
      }

      this.lastFetchTime = new Date();
      this.setStatus('online');

      // Also check pending (for any missed on page load)
      this.checkPending();

    } catch(e) {
      this.setStatus('offline');
    }
    this.setFetchIndicator(false);
  },

  async checkPending() {
    try {
      const res  = await fetch('api/earthquakes.php?action=pending');
      const data = await res.json();
      if (data.alerts && data.alerts.length > 0) {
        data.alerts.forEach(eq => {
          this.showNotification(eq);
          this.playAlert(this.severity(parseFloat(eq.magnitude)));
        });
      }
    } catch(e) { /* silent */ }
  },

  startPolling() {
    if (this.fetchTimer) clearInterval(this.fetchTimer);
    this.fetchTimer = setInterval(() => this.fetchUpdates(), this.pollInterval);
    // Also check pending every 30s
    setInterval(() => this.checkPending(), 30000);
  },

  // ─────────────────────────────────────────────
  // STATS
  // ─────────────────────────────────────────────
  async updateStats() {
    try {
      const res  = await fetch('api/earthquakes.php?action=stats');
      const data = await res.json();
      if (data.status !== 'ok') return;
      const s = data.stats;

      document.getElementById('stat24h').textContent      = s.last_24h ?? '--';
      document.getElementById('stat1h').textContent       = s.last_1h  ?? '--';
      document.getElementById('statMaxMag').textContent   = s.max_mag_24h ? parseFloat(s.max_mag_24h).toFixed(1) : '--';
      document.getElementById('statTsunami').textContent  = s.tsunami_alerts ?? '--';
      document.getElementById('statPH').textContent       = s.ph_24h ?? '--';

      if (s.latest) {
        document.getElementById('latestMag').textContent   = parseFloat(s.latest.magnitude).toFixed(1);
        document.getElementById('latestPlace').textContent = s.latest.place;
        document.getElementById('latestTime').textContent  = this.timeAgo(s.latest.event_time);
        document.getElementById('latestMag').style.color   = this.magColor(parseFloat(s.latest.magnitude));

        // Apply severity class to stat card
        const maxMagCard = document.getElementById('statMaxMag');
        const sev = this.severity(parseFloat(s.max_mag_24h));
        maxMagCard.className = 'stat-value';
        if (['extreme','severe'].includes(sev)) maxMagCard.classList.add('red');
        else if (sev === 'strong') maxMagCard.classList.add('amber');
        else if (sev === 'moderate') maxMagCard.classList.add('cyan');
        else maxMagCard.classList.add('green');
      }
    } catch(e) { /* silent */ }
  },

  // ─────────────────────────────────────────────
  // LIST RENDER
  // ─────────────────────────────────────────────
  renderList(newIds = []) {
    const container = document.getElementById('eqList');
    const countEl   = document.getElementById('eqCount');
    const filter     = this.currentFilter;

    let filtered = this.earthquakes;
    if (filter === 'ph')      filtered = filtered.filter(e => parseInt(e.is_ph));
    if (filter === 'major')   filtered = filtered.filter(e => parseFloat(e.magnitude) >= 5.0);
    if (filter === 'tsunami') filtered = filtered.filter(e => parseInt(e.tsunami));
    if (filter === '1h') {
      const cutoff = new Date(Date.now() - 3600000);
      filtered = filtered.filter(e => new Date(e.event_time) >= cutoff);
    }

    countEl.textContent = filtered.length;

    if (!filtered.length) {
      container.innerHTML = `
        <div class="eq-empty">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round"
              d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
          </svg>
          <p>No earthquakes match this filter</p>
        </div>`;
      return;
    }

    container.innerHTML = filtered.map(eq => {
      const mag     = parseFloat(eq.magnitude);
      const sev     = this.severity(mag);
      const isPH    = parseInt(eq.is_ph);
      const isTsun  = parseInt(eq.tsunami);
      const isNew   = newIds.includes(eq.usgs_id);

      return `
        <div class="eq-item ${isPH ? 'ph-event' : ''} ${isNew ? 'new-flash' : ''}"
             onclick="SW.openDetail('${eq.usgs_id}')">
          <div class="mag-badge ${sev}">${mag.toFixed(1)}</div>
          <div class="eq-info">
            <div class="eq-place">${this.escHtml(eq.place)}</div>
            <div class="eq-meta">
              <span class="eq-tag">${eq.depth_km ? parseFloat(eq.depth_km).toFixed(0) + 'km' : 'n/a'}</span>
              <span class="eq-tag">${this.severityLabel(mag)}</span>
              ${isPH   ? '<span class="eq-tag ph">🇵🇭 PH</span>' : ''}
              ${isTsun ? '<span class="eq-tag tsunami">⚠ TSUNAMI</span>' : ''}
            </div>
          </div>
          <div class="eq-time">
            <span class="ago">${this.timeAgo(eq.event_time)}</span>
            <span class="full">${this.formatDate(eq.event_time)}</span>
          </div>
        </div>`;
    }).join('');
  },

  // ─────────────────────────────────────────────
  // NOTIFICATIONS
  // ─────────────────────────────────────────────
  showNotification(eq) {
    const mag     = parseFloat(eq.magnitude);
    const sev     = this.severity(mag);
    const isPH    = eq.is_ph == 1 || eq.is_ph === true;
    const isTsun  = eq.tsunami == 1;
    const container = document.getElementById('notifContainer');

    if (isTsun) this.showTsunamiBanner(eq);

    const card = document.createElement('div');
    card.className = 'notif-card';
    card.innerHTML = `
      <div class="notif-stripe ${sev}"></div>
      <div class="notif-body">
        <div class="notif-icon ${sev}">M${mag.toFixed(1)}</div>
        <div class="notif-content">
          <div class="notif-title">
            ${sev === 'extreme' ? '🚨 ' : sev === 'severe' ? '⚠️ ' : ''}
            ${this.severityLabel(mag)} Earthquake Detected
          </div>
          <div class="notif-place">${this.escHtml(eq.place)}</div>
          <div class="notif-meta">
            <span class="notif-tag">Depth: ${eq.depth_km ? parseFloat(eq.depth_km).toFixed(1)+'km' : 'n/a'}</span>
            ${isPH   ? '<span class="notif-tag ph">🇵🇭 Philippines</span>' : ''}
            ${isTsun ? '<span class="notif-tag wave">⚠ TSUNAMI WARNING</span>' : ''}
          </div>
        </div>
        <button class="notif-close" onclick="this.closest('.notif-card').remove()">×</button>
      </div>
      <div class="notif-progress"><div class="notif-progress-bar"></div></div>`;

    container.prepend(card);
    requestAnimationFrame(() => requestAnimationFrame(() => card.classList.add('show')));

    // Auto-dismiss
    const timeout = isTsun ? 20000 : (sev === 'extreme' ? 12000 : 8000);
    setTimeout(() => {
      card.classList.replace('show', 'hide');
      setTimeout(() => card.remove(), 400);
    }, timeout);

    // Cap container at 5 notifications
    while (container.children.length > 5) {
      container.lastChild.remove();
    }
  },

  showTsunamiBanner(eq) {
    const banner = document.getElementById('tsunamiBanner');
    banner.innerHTML = `
      🌊 TSUNAMI WARNING ISSUED — ${this.escHtml(eq.place)} — M${parseFloat(eq.magnitude).toFixed(1)}
      — Move away from coastlines immediately!
      <button onclick="document.getElementById('tsunamiBanner').classList.remove('show')"
        style="margin-left:auto;background:rgba(255,255,255,0.2);border:none;color:white;
               padding:4px 10px;border-radius:4px;cursor:pointer;font-size:12px">Dismiss</button>`;
    banner.classList.add('show');
  },

  // ─────────────────────────────────────────────
  // DETAIL MODAL
  // ─────────────────────────────────────────────
  openDetail(usgsId) {
    const eq = this.earthquakes.find(e => e.usgs_id === usgsId);
    if (!eq) return;
    this.selectedEq = eq;
    const mag = parseFloat(eq.magnitude);
    const sev = this.severity(mag);
    const color = this.magColor(mag);

    document.getElementById('modalMag').textContent  = mag.toFixed(1);
    document.getElementById('modalMag').style.color  = color;
    document.getElementById('modalPlace').textContent = eq.place;
    document.getElementById('modalType').textContent  = (eq.event_type || 'earthquake').toUpperCase();
    document.getElementById('modalTime').textContent  = this.formatDate(eq.event_time);
    document.getElementById('modalLat').textContent   = parseFloat(eq.latitude).toFixed(4) + '°';
    document.getElementById('modalLon').textContent   = parseFloat(eq.longitude).toFixed(4) + '°';
    document.getElementById('modalDepth').textContent = parseFloat(eq.depth_km).toFixed(1) + ' km';
    document.getElementById('modalSeverity').textContent = this.severityLabel(mag);
    document.getElementById('modalSeverity').style.color  = color;
    document.getElementById('modalFelt').textContent  = eq.felt_reports || '0';
    document.getElementById('modalTsunami').textContent = eq.tsunami == 1 ? '⚠️ WARNING ISSUED' : 'None';
    document.getElementById('modalTsunami').style.color = eq.tsunami == 1 ? '#FF2D20' : '#8A99B8';
    document.getElementById('modalUsgsLink').href     = eq.url || '#';
    document.getElementById('modalUsgsLink').style.display = eq.url ? '' : 'none';

    document.getElementById('modalOverlay').classList.add('open');
  },

  closeModal() {
    document.getElementById('modalOverlay').classList.remove('open');
  },

  // ─────────────────────────────────────────────
  // ACTIVITY LOG
  // ─────────────────────────────────────────────
  activityItems: [],

  addActivity(eq, type) {
    const mag   = parseFloat(eq.magnitude);
    const sev   = this.severity(mag);
    const color = this.magColor(mag);
    const colors = { extreme:'#FF2D20', severe:'#FF6B35', strong:'#F59E0B', moderate:'#EAB308', light:'#22C55E', micro:'#3B82F6' };
    const item = {
      color: colors[sev],
      text: `<strong>M${mag.toFixed(1)} ${this.severityLabel(mag)}</strong> — ${eq.place}`,
      ts: this.timeAgo(eq.event_time || new Date().toISOString())
    };
    this.activityItems.unshift(item);
    if (this.activityItems.length > 20) this.activityItems.pop();
    this.renderActivity();
  },

  renderActivity() {
    const el = document.getElementById('activityList');
    if (!el) return;
    if (!this.activityItems.length) {
      el.innerHTML = '<div class="eq-empty"><p style="font-size:12px">No recent activity</p></div>';
      return;
    }
    el.innerHTML = this.activityItems.map(a => `
      <div class="activity-item">
        <div class="activity-dot" style="background:${a.color}"></div>
        <div class="activity-text">${a.text}</div>
        <div class="activity-ts">${a.ts}</div>
      </div>`).join('');
  },

  // ─────────────────────────────────────────────
  // SETTINGS
  // ─────────────────────────────────────────────
  initSettings() {
    // Load from server / localStorage
    const saved = localStorage.getItem('sw_settings');
    if (saved) {
      try {
        const s = JSON.parse(saved);
        this.alertMag     = parseFloat(s.alert_magnitude || 4.5);
        this.phMinMag     = parseFloat(s.ph_min_mag || 2.0);
        this.soundEnabled = s.alert_sound != '0';
        this.phFocus      = s.philippines_focus != '0';
        this.pollInterval = (parseInt(s.fetch_interval) || 60) * 1000;
      } catch(e) {}
    }
    this.syncSettingsUI();
  },

  syncSettingsUI() {
    const set = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };
    set('setAlertMag',   this.alertMag);
    set('setPhMinMag',   this.phMinMag);
    set('setInterval',   this.pollInterval / 1000);
    const soundTgl = document.getElementById('toggleSound');
    const phTgl    = document.getElementById('togglePH');
    if (soundTgl) soundTgl.classList.toggle('on', this.soundEnabled);
    if (phTgl)    phTgl.classList.toggle('on', this.phFocus);
  },

  openSettings() {
    document.getElementById('settingsOverlay').classList.add('open');
  },

  closeSettings() {
    document.getElementById('settingsOverlay').classList.remove('open');
  },

  async saveSettings() {
    const alertMag  = parseFloat(document.getElementById('setAlertMag').value)  || 4.5;
    const phMinMag  = parseFloat(document.getElementById('setPhMinMag').value)   || 2.0;
    const interval  = parseInt(document.getElementById('setInterval').value)    || 60;
    const sound     = document.getElementById('toggleSound').classList.contains('on') ? '1' : '0';
    const ph        = document.getElementById('togglePH').classList.contains('on') ? '1' : '0';

    const payload = {
      alert_magnitude:    alertMag,
      ph_min_mag:         phMinMag,
      fetch_interval:     interval,
      alert_sound:        sound,
      philippines_focus:  ph,
    };

    localStorage.setItem('sw_settings', JSON.stringify(payload));

    try {
      await fetch('api/earthquakes.php?action=save_settings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
    } catch(e) { /* local save still works */ }

    this.alertMag     = alertMag;
    this.phMinMag     = phMinMag;
    this.soundEnabled = sound === '1';
    this.phFocus      = ph === '1';
    this.pollInterval = interval * 1000;
    this.startPolling();
    this.closeSettings();
    this.showToast('Settings saved successfully');
  },

  // ─────────────────────────────────────────────
  // UI HELPERS
  // ─────────────────────────────────────────────
  setStatus(state) {
    const badge = document.getElementById('statusBadge');
    const dot   = document.getElementById('liveDot');
    this.isOnline = state === 'online';
    badge.textContent = state === 'online' ? '● LIVE' : state === 'loading' ? '◌ LOADING' : '✕ OFFLINE';
    badge.className   = 'status-badge' + (state === 'offline' ? ' offline' : '');
    if (dot) dot.style.background = state === 'online' ? 'var(--green)' : state === 'loading' ? 'var(--amber)' : 'var(--red)';
  },

  setFetchIndicator(loading) {
    const btn = document.getElementById('refreshBtn');
    if (!btn) return;
    btn.innerHTML = loading
      ? `<span class="spinner"></span> Fetching...`
      : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
           <path stroke-linecap="round" stroke-linejoin="round"
             d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
         </svg> Refresh`;
  },

  showToast(msg) {
    const t = document.getElementById('globalToast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
  },

  escHtml(str) {
    return (str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  },

  // ─────────────────────────────────────────────
  // EVENT BINDINGS
  // ─────────────────────────────────────────────
  bindUI() {
    // Filter buttons
    document.querySelectorAll('.filter-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        this.currentFilter = btn.dataset.filter;
        this.renderList();
      });
    });

    // Toggle buttons in settings (exclude dark mode toggle — it has its own handler)
    document.querySelectorAll('.toggle:not(#toggleDarkMode)').forEach(tgl => {
      tgl.addEventListener('click', () => tgl.classList.toggle('on'));
    });

    // Dark mode toggle — dedicated handler
    const darkTgl = document.getElementById('toggleDarkMode');
    if (darkTgl) {
      darkTgl.addEventListener('click', () => SW.toggleTheme());
    }

    // Close modal on backdrop click
    document.getElementById('modalOverlay').addEventListener('click', (e) => {
      if (e.target === e.currentTarget) this.closeModal();
    });

    // Close settings on backdrop click
    document.getElementById('settingsOverlay').addEventListener('click', (e) => {
      if (e.target.classList.contains('settings-overlay')) this.closeSettings();
    });

    // Refresh time display every minute
    setInterval(() => {
      if (this.earthquakes.length) this.renderList();
    }, 60000);

    // Request notification permission
    if ('Notification' in window && Notification.permission === 'default') {
      setTimeout(() => Notification.requestPermission(), 3000);
    }
  },

  // ── Browser Notification ──────────────────────
  sendBrowserNotif(eq) {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    const mag = parseFloat(eq.magnitude);
    new Notification(`M${mag.toFixed(1)} Earthquake — SeismoWatch`, {
      body: eq.place,
      icon: 'assets/img/icon.png',
      tag: eq.usgs_id,
    });
  },

  // ─────────────────────────────────────────────
  // THEME TOGGLE (Dark / Light) — single source of truth
  // ─────────────────────────────────────────────

  // Called once on page load to restore saved preference
  applyTheme() {
    const saved = localStorage.getItem('sw_theme') || 'dark';
    this._setTheme(saved === 'light');
  },

  // Called by any toggle button / sidebar icon
  toggleTheme() {
    const isNowLight = !document.body.classList.contains('light-mode');
    this._setTheme(isNowLight);
    this.showToast(isNowLight ? '☀️ Light mode enabled' : '🌙 Dark mode enabled');
  },

  // Internal: apply theme state everywhere consistently
  _setTheme(isLight) {
    // 1. Body class
    document.body.classList.toggle('light-mode', isLight);

    // 2. Persist
    localStorage.setItem('sw_theme', isLight ? 'light' : 'dark');

    // 3. Settings drawer dark mode toggle pill — sync its ON/OFF state
    const darkTgl = document.getElementById('toggleDarkMode');
    if (darkTgl) darkTgl.classList.toggle('on', !isLight);

    // 4. Map tile layer
    this._updateMapTiles(isLight);
  },

  _updateMapTiles(isLight) {
    if (!this.leafletMap || !window.L) return;

    // Remove all tile layers safely
    const toRemove = [];
    this.leafletMap.eachLayer(layer => { if (layer._url) toRemove.push(layer); });
    toRemove.forEach(l => this.leafletMap.removeLayer(l));

    // Add correct tile layer
    const url = isLight
      ? 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png'
      : 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png';
    L.tileLayer(url, { maxZoom: 18 }).addTo(this.leafletMap);

    // Re-add PH bounding box
    L.rectangle([[4.0, 116.0],[21.5, 127.0]], {
      color: '#06B6D4', weight: 1,
      fillColor: '#06B6D4', fillOpacity: 0.03,
      dashArray: '4,4'
    }).addTo(this.leafletMap);

    // Redraw markers
    this.updateMap(this.earthquakes);
  }
};

// ── Bootstrap ──────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  SW.applyTheme(); // Apply before init to avoid flash
  SW.init();
});