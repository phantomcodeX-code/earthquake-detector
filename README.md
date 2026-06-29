<div align="center">

# 🌋 SeismoWatch

### Real-Time Earthquake Monitoring Dashboard

**A professional, full-stack earthquake detection system with live USGS data feeds,
advance pop-up alerts, Philippines-focused monitoring, and a dark portfolio-inspired UI.**

---

![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Leaflet](https://img.shields.io/badge/Leaflet.js-1.9-199900?style=for-the-badge&logo=leaflet&logoColor=white)
![Vanilla JS](https://img.shields.io/badge/JavaScript-ES6%2B-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![License](https://img.shields.io/badge/License-MIT-red?style=for-the-badge)
![Status](https://img.shields.io/badge/Status-Active-brightgreen?style=for-the-badge)

<br/>

> 🇵🇭 Built with love for the Philippines — one of the most seismically active countries in the world.

</div>

---

## 📸 Overview

SeismoWatch is a **real-time earthquake monitoring web application** that pulls live data from the **USGS Earthquake Hazards Program GeoJSON feeds** and presents it through a sleek, dark portfolio-inspired dashboard. It detects new earthquake events, triggers instant pop-up notifications with sound alerts, and provides focused monitoring for the Philippine region with configurable magnitude thresholds.

---

## ✨ Features

### 🔴 Core Monitoring
- **⚡ Real-time USGS feed polling** — Auto-fetches from multiple GeoJSON endpoints every 60 seconds (fully configurable)
- **🌏 Dual-feed coverage** — Combines `all_hour.geojson` + `2.5_day.geojson` for maximum detection coverage
- **🇵🇭 Philippines-focused mode** — Lower magnitude threshold (M2.0+) for the Philippine region vs global default (M2.5+)
- **🌊 Tsunami warning system** — Full-screen animated red banner issued immediately for tsunami-linked events
- **🔁 Background cron fetcher** — CLI script for server-side polling even without browser open

### 🔔 Alert & Notification System
- **📲 Advance pop-up notifications** — Animated toast cards slide in from the right for every significant event
- **🔊 Web Audio API sound alerts** — Distinct tones per severity level (sawtooth alarm → soft ping)
- **🚨 Severity classification** — 6-tier system from Micro to Extreme with color-coded visual cues
- **⏱️ Auto-dismiss timers** — Normal alerts dismiss after 8s, extreme after 12s, tsunami stays for 20s
- **🗂️ Notification stacking** — Up to 5 concurrent alerts displayed simultaneously

### 🗺️ Visual Dashboard
- **📡 Live seismic waveform** — Animated canvas that realistically spikes on new earthquake detection
- **🗺️ Interactive Leaflet map** — Dark/light tile layers with color-coded circle markers scaled by magnitude
- **📊 5 live stat cards** — Last 24h count, last hour, max magnitude, tsunami alerts, PH region events
- **📋 Earthquake feed list** — Scrollable, filterable list with mag badge, depth, severity tag, and timestamp
- **🔍 Detail modal** — Depth, coordinates, felt reports, alert level, direct USGS link
- **📝 Activity log** — Real-time feed of detected alerts with color dots

### 🎨 UI & Theming
- **🌙 Dark / ☀️ Light mode** — Full CSS variable-based theme swap, persisted to localStorage
- **⚙️ Phoenix-style floating CUSTOMIZE tab** — Fixed to the right edge with spinning gear + pulse ring animation
- **📱 Responsive design** — Works on desktop, tablet, and mobile
- **🎨 Portfolio-inspired aesthetic** — Space Grotesk + Inter + JetBrains Mono typography stack

---

## 🛠️ Tech Stack

| Layer | Technology |
|-------|-----------|
| **Backend** | PHP 7.4+ (OOP, PDO) |
| **Database** | MySQL 5.7+ / MariaDB 10.3+ |
| **Frontend** | Vanilla JavaScript (ES6+) |
| **Mapping** | Leaflet.js 1.9.4 |
| **Fonts** | Google Fonts (Space Grotesk, Inter, JetBrains Mono) |
| **Tiles** | CartoDB Dark/Light Matter |
| **Audio** | Web Audio API |
| **Data Source** | USGS Earthquake Hazards Program GeoJSON |

---

## 📁 Project Structure

```
seismowatch/
│
├── 📄 index.php                  ← Main dashboard entry point
├── ⚙️  config.php                 ← DB credentials & app constants
├── 🗄️  database.sql               ← MySQL schema + default settings
│
├── 📂 api/
│   └── earthquakes.php           ← JSON REST API endpoint
│                                    Actions: fetch | list | pending | stats | save_settings
│
├── 📂 includes/
│   ├── db.php                    ← PDO singleton connection class
│   └── fetcher.php               ← USGS GeoJSON fetcher + PH region detector
│
├── 📂 assets/
│   ├── css/
│   │   └── app.css               ← Full stylesheet (dark/light CSS variable system)
│   └── js/
│       └── app.js                ← Frontend engine (polling, map, notifications, waveform)
│
└── 📂 cron/
    └── fetch.php                 ← CLI background fetcher script
```

---

## 🚀 Installation

### Prerequisites

Before you begin, ensure you have:

- ✅ PHP **7.4 or higher** with extensions: `pdo`, `pdo_mysql`, `openssl`
- ✅ MySQL **5.7+** or MariaDB **10.3+**
- ✅ Apache or Nginx web server
- ✅ Internet access (to reach USGS GeoJSON API)

---

### Step 1 — Clone the Repository

```bash
git clone https://github.com/yourusername/seismowatch.git
cd seismowatch
```

---

### Step 2 — Create & Import the Database

**Via terminal:**
```bash
mysql -u root -p -e "CREATE DATABASE earthquake_detector CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p earthquake_detector < database.sql
```

**Via phpMyAdmin:**
1. Create a new database named `earthquake_detector`
2. Go to **Import** → select `database.sql` → click **Go**

---

### Step 3 — Configure the Application

Edit `config.php` with your database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');       // ← your MySQL username
define('DB_PASS', 'your_db_password');   // ← your MySQL password
define('DB_NAME', 'earthquake_detector');
```

---

### Step 4 — Deploy to Web Server

**XAMPP / WAMP (local):**
```
Copy project folder → htdocs/seismowatch/
Access at: http://localhost/seismowatch/
```

**Linux VPS (Apache):**
```bash
sudo cp -r seismowatch/ /var/www/html/
sudo chown -R www-data:www-data /var/www/html/seismowatch/
```

---

### Step 5 — Set Script Permissions

```bash
chmod 755 cron/fetch.php
```

---

### Step 6 — (Recommended) Setup Cron Job

For background data fetching even when no browser is open:

```bash
crontab -e
```

Add this line:
```cron
*/1 * * * * php /var/www/html/seismowatch/cron/fetch.php >> /var/log/seismowatch.log 2>&1
```

> 💡 This ensures the database is always up-to-date. Without cron, data fetches only when a user has the dashboard open.

---

### ✅ You're Live!

Open your browser and navigate to your deployment URL. You should see the **SeismoWatch** dashboard loading with live earthquake data within seconds.

---

## 🌐 How It Works

```
┌─────────────────────────────────────────────────────────┐
│                    BROWSER (Every 60s)                  │
│         api/earthquakes.php?action=fetch                │
└──────────────────────┬──────────────────────────────────┘
                       │
                       ▼
            ┌─────────────────────┐
            │   USGSFetcher::     │
            │     fetchAll()      │
            └──────┬──────┬───────┘
                   │      │
          ┌────────▼─┐  ┌─▼──────────────┐
          │all_hour  │  │ 2.5_day.geojson│
          │.geojson  │  │  (M2.5+ / day) │
          └────────┬─┘  └─┬──────────────┘
                   │      │
                   └──┬───┘
                      ▼
           ┌──────────────────────┐
           │  MySQL: earthquakes  │
           │       table          │
           └──────────┬───────────┘
                      │
          ┌───────────▼────────────┐
          │  Alert Logic Checks:   │
          │  • magnitude >= 4.5?   │
          │  • Philippines region? │
          │  • tsunami flag = 1?   │
          └───────────┬────────────┘
                      │
                      ▼
          ┌───────────────────────────────┐
          │  Browser receives alerts[]    │
          │  ├─ Toast notification popup  │
          │  ├─ Web Audio sound alert     │
          │  ├─ Waveform canvas spike     │
          │  └─ Activity log entry        │
          └───────────────────────────────┘
```

---

## 🔌 API Reference

The JSON API is accessible at `api/earthquakes.php`:

| Action | Method | Description |
|--------|--------|-------------|
| `?action=fetch` | GET | Triggers USGS fetch, returns new alert events |
| `?action=list` | GET | Returns paginated earthquake list (supports `hours`, `limit` params) |
| `?action=pending` | GET | Returns unnotified events above alert threshold |
| `?action=stats` | GET | Returns dashboard stat counters |
| `?action=save_settings` | POST | Saves configuration to DB (JSON body) |

**Example Response — `?action=stats`:**
```json
{
  "status": "ok",
  "stats": {
    "last_24h": 42,
    "last_1h": 5,
    "max_mag_24h": 5.8,
    "tsunami_alerts": 0,
    "ph_24h": 3,
    "latest": { "magnitude": "3.2", "place": "12km NE of Davao, Philippines", ... }
  }
}
```

---

## ⚙️ Configuration Reference

Accessible via the **floating CUSTOMIZE tab** on the right edge of the dashboard:

| Setting | Default | Description |
|---------|---------|-------------|
| 🔔 Alert Popup Magnitude | `4.5` | Minimum magnitude to trigger a popup notification globally |
| 🇵🇭 PH Region Magnitude | `2.0` | Lower threshold for Philippine region — more sensitive local monitoring |
| ⏱️ Fetch Interval | `60s` | How often the browser polls the USGS API (30–3600 seconds) |
| 🔊 Alert Sound | `On` | Enable/disable Web Audio API tones on alert |
| 🗺️ Philippines Focus | `On` | Enables the lower PH threshold and cyan region highlight on map |
| 🌙 Dark Mode | `On` | Toggle between dark and light UI themes |

> Settings are saved to both **MySQL** (server-side persistence) and **localStorage** (instant load on next visit).

---

## 🔔 Severity Scale

| Magnitude | Level | Badge Color | Sound Type | Auto-dismiss |
|-----------|-------|-------------|------------|--------------|
| **7.0+** | 🔴 Extreme | Red | Sawtooth alarm (repeating) | 12 seconds |
| **6.0 – 6.9** | 🟠 Severe | Orange | Double beep | 10 seconds |
| **5.0 – 5.9** | 🟡 Strong | Amber | Single beep | 8 seconds |
| **4.0 – 4.9** | 🟡 Moderate | Yellow | Soft tone | 8 seconds |
| **3.0 – 3.9** | 🟢 Light | Green | Ping | 8 seconds |
| **< 3.0** | 🔵 Micro | Blue | Silent | — |

> 🌊 **Tsunami events** override all thresholds — always trigger alerts with a **20-second persistent banner** regardless of magnitude.

---

## 🗺️ Philippines Region Monitoring

SeismoWatch includes a dedicated bounding box for enhanced Philippine monitoring:

```
┌─────────────────────────────┐
│  Latitude:   4.0°N – 21.5°N │
│  Longitude: 116.0°E – 127.0°E│
└─────────────────────────────┘
```

Events within this box:
- Use the lower `ph_min_mag` threshold (default M2.0)
- Are tagged with a 🇵🇭 **PH** badge in the feed list
- Are highlighted with a **cyan border** on the earthquake row
- Are shown inside a **cyan dashed bounding box** on the Leaflet map
- Can be filtered using the **Philippines tab** in the feed

---

## 🗄️ Database Schema

```sql
┌─────────────────────────────────────────────────────┐
│                  earthquakes                        │
├──────────────────┬──────────────┬───────────────────┤
│ id               │ INT PK AI    │                   │
│ usgs_id          │ VARCHAR(64)  │ UNIQUE             │
│ magnitude        │ DECIMAL(4,2) │                   │
│ place            │ VARCHAR(255) │                   │
│ latitude         │ DECIMAL(10,6)│                   │
│ longitude        │ DECIMAL(10,6)│                   │
│ depth_km         │ DECIMAL(8,2) │                   │
│ event_time       │ DATETIME     │ INDEX              │
│ alert_level      │ VARCHAR(20)  │ green/yellow/red   │
│ tsunami          │ TINYINT(1)   │ 0 or 1             │
│ felt_reports     │ INT          │                   │
│ notified         │ TINYINT(1)   │ INDEX              │
│ created_at       │ DATETIME     │                   │
└──────────────────┴──────────────┴───────────────────┘

┌─────────────────────────────────────────────────────┐
│               notification_log                      │
├──────────────────┬──────────────┬───────────────────┤
│ id               │ INT PK AI    │                   │
│ earthquake_id    │ INT FK       │ → earthquakes.id  │
│ notif_type       │ ENUM         │ popup/email/sound  │
│ sent_at          │ DATETIME     │                   │
└──────────────────┴──────────────┴───────────────────┘

┌─────────────────────────────────────────────────────┐
│                   settings                          │
├──────────────────┬──────────────┬───────────────────┤
│ setting_key      │ VARCHAR(64)  │ PK                 │
│ setting_value    │ VARCHAR(255) │                   │
│ description      │ VARCHAR(255) │                   │
│ updated_at       │ DATETIME     │ auto-updated       │
└──────────────────┴──────────────┴───────────────────┘
```

---

## 🐞 Troubleshooting

| Problem | Cause | Solution |
|---------|-------|----------|
| ❌ Blank page | PHP errors | Check `php.ini` error reporting; ensure PDO_MySQL is enabled |
| ❌ No data loading | DB connection failed | Verify `config.php` credentials; check MySQL is running |
| ❌ USGS data not fetching | SSL / allow_url_fopen | Enable `allow_url_fopen=On` in `php.ini` |
| ❌ Map not showing | CDN blocked | Check browser console; ensure Leaflet CDN is accessible |
| ❌ No sound on alert | AudioContext policy | Click anywhere on the page first — browsers require user gesture |
| ❌ Notifications not firing | Cron not running | Run `cron/fetch.php` manually; check cron logs |
| ⚠️ Stale data | Low fetch interval | Lower the interval in Settings; verify internet connectivity |

---

## 🤝 Contributing

Contributions are welcome! Here's how to get started:

1. **Fork** the repository
2. **Create** a feature branch: `git checkout -b feature/your-feature-name`
3. **Commit** your changes: `git commit -m "feat: add your feature description"`
4. **Push** to your branch: `git push origin feature/your-feature-name`
5. **Open** a Pull Request

### 💡 Ideas for Contributions
- 📧 Email notification integration (PHPMailer)
- 📱 PWA / push notification support
- 📈 Historical data charts (Chart.js)
- 🌐 Multi-language support (Filipino / English)
- 🏔️ PHIVOLCS API integration for local PH data
- 🧪 Unit tests (PHPUnit)

---

## 📜 License

```
MIT License

Copyright (c) 2025 SeismoWatch Contributors

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software.
```

---

## 📡 Data Attribution

Earthquake data is sourced from the **U.S. Geological Survey (USGS) Earthquake Hazards Program**.

> 🔗 [https://earthquake.usgs.gov/earthquakes/feed/v1.0/geojson.php](https://earthquake.usgs.gov/earthquakes/feed/v1.0/geojson.php)

Data is public domain and updated every 1–5 minutes on USGS servers.

---

## 👨‍💻 Author

<div align="center">

Built with ❤️ and ☕ in the **Philippines** 🇵🇭

*"Monitoring the ground beneath our feet — so we're never caught off guard."*

---

⭐ **If this project helped you, please give it a star!** ⭐

</div>
