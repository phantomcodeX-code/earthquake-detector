<?php
// =============================================
// includes/fetcher.php — USGS Data Fetcher
// =============================================

require_once __DIR__ . '/db.php';

class USGSFetcher {

    /**
     * Fetch data from USGS GeoJSON feed and store new events.
     * Returns array of newly inserted earthquake records.
     */
    public static function fetchAndStore(string $feed = USGS_GEOJSON_ALL_HOUR): array {
        $json = self::fetchURL($feed);
        if (!$json) return [];

        $data = json_decode($json, true);
        if (!isset($data['features'])) return [];

        $db = Database::connect();
        $newEvents = [];

        foreach ($data['features'] as $feature) {
            $props = $feature['properties'];
            $geo   = $feature['geometry']['coordinates']; // [lon, lat, depth]

            $usgsId    = $feature['id'];
            $mag       = isset($props['mag'])   ? (float)$props['mag']   : 0;
            $place     = isset($props['place'])  ? $props['place']        : 'Unknown';
            $lon       = isset($geo[0])          ? (float)$geo[0]         : 0;
            $lat       = isset($geo[1])          ? (float)$geo[1]         : 0;
            $depth     = isset($geo[2])          ? (float)$geo[2]         : 0;
            $time      = isset($props['time'])   ? (int)$props['time']    : 0;
            $type      = isset($props['type'])   ? $props['type']         : 'earthquake';
            $alert     = isset($props['alert'])  ? $props['alert']        : null;
            $tsunami   = isset($props['tsunami']) ? (int)$props['tsunami'] : 0;
            $felt      = isset($props['felt'])   ? (int)$props['felt']    : 0;
            $url       = isset($props['url'])    ? $props['url']          : null;

            $eventDt = date('Y-m-d H:i:s', (int)($time / 1000));

            // Skip if already exists
            $check = $db->prepare('SELECT id, notified FROM earthquakes WHERE usgs_id = ?');
            $check->execute([$usgsId]);
            $existing = $check->fetch();

            if ($existing) {
                // Update felt count / alert level if changed
                $upd = $db->prepare(
                    'UPDATE earthquakes SET felt_reports=?, alert_level=?, updated_at=NOW() WHERE usgs_id=?'
                );
                $upd->execute([$felt, $alert, $usgsId]);
                continue;
            }

            // Insert new record
            $ins = $db->prepare(
                'INSERT INTO earthquakes
                 (usgs_id, magnitude, place, latitude, longitude, depth_km,
                  event_time, event_type, alert_level, tsunami, felt_reports, url, notified)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,0)'
            );
            $ins->execute([
                $usgsId, $mag, $place, $lat, $lon, $depth,
                $eventDt, $type, $alert, $tsunami, $felt, $url
            ]);
            $newId = $db->lastInsertId();

            $newEvents[] = [
                'id'         => $newId,
                'usgs_id'    => $usgsId,
                'magnitude'  => $mag,
                'place'      => $place,
                'latitude'   => $lat,
                'longitude'  => $lon,
                'depth_km'   => $depth,
                'event_time' => $eventDt,
                'alert_level'=> $alert,
                'tsunami'    => $tsunami,
                'felt_reports'=> $felt,
                'url'        => $url,
                'notified'   => 0,
            ];
        }

        // Prune old records beyond max_records
        $max = (int)Database::getSetting('max_records', '100');
        $db->exec(
            "DELETE FROM earthquakes WHERE id NOT IN
             (SELECT id FROM (SELECT id FROM earthquakes ORDER BY event_time DESC LIMIT {$max}) t)"
        );

        return $newEvents;
    }

    /**
     * Fetch multiple feeds simultaneously for better coverage.
     */
    public static function fetchAll(): array {
        $minMag    = (float)Database::getSetting('min_magnitude', '2.5');
        $phFocus   = (int)Database::getSetting('philippines_focus', '1');
        $phMinMag  = (float)Database::getSetting('ph_min_mag', '2.0');

        // Always fetch last hour (all) + 2.5+ for past day
        $newAll = array_merge(
            self::fetchAndStore(USGS_GEOJSON_ALL_HOUR),
            self::fetchAndStore(USGS_GEOJSON_25_DAY)
        );

        // Deduplicate by usgs_id
        $seen = [];
        $events = [];
        foreach ($newAll as $ev) {
            if (!isset($seen[$ev['usgs_id']])) {
                $seen[$ev['usgs_id']] = true;
                $events[] = $ev;
            }
        }

        // Apply magnitude filter unless PH-focus overrides
        return array_filter($events, function($ev) use ($minMag, $phFocus, $phMinMag) {
            if ($phFocus && self::isPhilippines($ev['latitude'], $ev['longitude'])) {
                return $ev['magnitude'] >= $phMinMag;
            }
            return $ev['magnitude'] >= $minMag;
        });
    }

    /**
     * Check if coordinates are within Philippine bounds.
     */
    public static function isPhilippines(float $lat, float $lon): bool {
        return $lat >= PH_LAT_MIN && $lat <= PH_LAT_MAX
            && $lon >= PH_LON_MIN && $lon <= PH_LON_MAX;
    }

    private static function fetchURL(string $url): ?string {
        $ctx = stream_context_create([
            'http' => [
                'timeout'       => 15,
                'method'        => 'GET',
                'ignore_errors' => true,
                'header'        => "User-Agent: SeismoWatch/1.0\r\n",
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ]
        ]);
        $result = @file_get_contents($url, false, $ctx);
        return $result !== false ? $result : null;
    }
}
