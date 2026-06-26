<?php
// =============================================
// api/earthquakes.php — JSON API Endpoint
// =============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/fetcher.php';

$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {

        // ── Fetch from USGS + return new events for notification ──
        case 'fetch':
            $newEvents = USGSFetcher::fetchAll();
            $alertMag  = (float)Database::getSetting('alert_magnitude', '4.5');
            $phMinMag  = (float)Database::getSetting('ph_min_mag', '2.0');

            $alerts = [];
            foreach ($newEvents as $ev) {
                $isPH    = USGSFetcher::isPhilippines($ev['latitude'], $ev['longitude']);
                $isAlert = $ev['magnitude'] >= $alertMag
                           || ($isPH && $ev['magnitude'] >= $phMinMag)
                           || $ev['tsunami'] == 1;

                if ($isAlert) {
                    $alerts[] = array_merge($ev, ['is_ph' => $isPH]);
                    // Mark as notified
                    $db = Database::connect();
                    $db->prepare('UPDATE earthquakes SET notified=1 WHERE id=?')
                       ->execute([$ev['id']]);
                    // Log it
                    $db->prepare(
                        'INSERT INTO notification_log (earthquake_id, notif_type) VALUES (?,?)'
                    )->execute([$ev['id'], 'popup']);
                }
            }

            echo json_encode([
                'status'      => 'ok',
                'new_count'   => count($newEvents),
                'alerts'      => $alerts,
                'fetched_at'  => date('Y-m-d H:i:s'),
            ]);
            break;

        // ── Get recent earthquakes list ──
        case 'list':
            $db      = Database::connect();
            $minMag  = (float)Database::getSetting('min_magnitude', '2.5');
            $limit   = min((int)($_GET['limit'] ?? 50), 100);
            $hours   = (int)($_GET['hours'] ?? 24);

            $stmt = $db->prepare(
                'SELECT * FROM earthquakes
                 WHERE event_time >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                   AND magnitude >= ?
                 ORDER BY event_time DESC
                 LIMIT ?'
            );
            $stmt->execute([$hours, $minMag, $limit]);
            $rows = $stmt->fetchAll();

            // Annotate PH events
            foreach ($rows as &$r) {
                $r['is_ph'] = USGSFetcher::isPhilippines((float)$r['latitude'], (float)$r['longitude']) ? 1 : 0;
                $r['severity'] = self_severity($r['magnitude']);
            }

            echo json_encode([
                'status' => 'ok',
                'count'  => count($rows),
                'data'   => $rows,
            ]);
            break;

        // ── Get pending (unnotified) alerts ──
        case 'pending':
            $db       = Database::connect();
            $alertMag = (float)Database::getSetting('alert_magnitude', '4.5');
            $phMinMag = (float)Database::getSetting('ph_min_mag', '2.0');

            $stmt = $db->prepare(
                'SELECT * FROM earthquakes
                 WHERE notified = 0
                   AND (magnitude >= ? OR tsunami = 1)
                 ORDER BY event_time DESC LIMIT 10'
            );
            $stmt->execute([$alertMag]);
            $rows = $stmt->fetchAll();

            // Check PH lower threshold
            $phStmt = $db->prepare(
                'SELECT * FROM earthquakes
                 WHERE notified = 0
                   AND latitude BETWEEN ? AND ?
                   AND longitude BETWEEN ? AND ?
                   AND magnitude >= ?
                 ORDER BY event_time DESC LIMIT 10'
            );
            $phStmt->execute([PH_LAT_MIN, PH_LAT_MAX, PH_LON_MIN, PH_LON_MAX, $phMinMag]);
            $phRows = $phStmt->fetchAll();

            $all = array_unique(array_merge($rows, $phRows), SORT_REGULAR);

            // Mark all as notified
            if (!empty($all)) {
                $ids = implode(',', array_column($all, 'id'));
                $db->exec("UPDATE earthquakes SET notified=1 WHERE id IN ({$ids})");
            }

            foreach ($all as &$r) {
                $r['is_ph']    = USGSFetcher::isPhilippines((float)$r['latitude'], (float)$r['longitude']) ? 1 : 0;
                $r['severity'] = self_severity($r['magnitude']);
            }

            echo json_encode([
                'status' => 'ok',
                'alerts' => array_values($all),
            ]);
            break;

        // ── Stats for dashboard ──
        case 'stats':
            $db = Database::connect();
            $stats = [];

            $r = $db->query('SELECT COUNT(*) c FROM earthquakes WHERE event_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)')->fetch();
            $stats['last_24h'] = (int)$r['c'];

            $r = $db->query('SELECT COUNT(*) c FROM earthquakes WHERE event_time >= DATE_SUB(NOW(), INTERVAL 1 HOUR)')->fetch();
            $stats['last_1h'] = (int)$r['c'];

            $r = $db->query('SELECT MAX(magnitude) m FROM earthquakes WHERE event_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)')->fetch();
            $stats['max_mag_24h'] = $r['m'] ? (float)$r['m'] : 0;

            $r = $db->query('SELECT COUNT(*) c FROM earthquakes WHERE tsunami=1 AND event_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->fetch();
            $stats['tsunami_alerts'] = (int)$r['c'];

            $r = $db->query(
                'SELECT COUNT(*) c FROM earthquakes
                 WHERE latitude BETWEEN ' . PH_LAT_MIN . ' AND ' . PH_LAT_MAX . '
                   AND longitude BETWEEN ' . PH_LON_MIN . ' AND ' . PH_LON_MAX . '
                   AND event_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)'
            )->fetch();
            $stats['ph_24h'] = (int)$r['c'];

            $r = $db->query(
                'SELECT * FROM earthquakes ORDER BY event_time DESC LIMIT 1'
            )->fetch();
            $stats['latest'] = $r ?: null;

            echo json_encode(['status' => 'ok', 'stats' => $stats]);
            break;

        // ── Save settings ──
        case 'save_settings':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['status' => 'error', 'message' => 'POST required']);
                break;
            }
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $allowed = ['min_magnitude','alert_magnitude','fetch_interval','alert_sound','philippines_focus','ph_min_mag'];
            foreach ($allowed as $key) {
                if (isset($input[$key])) {
                    Database::setSetting($key, $input[$key]);
                }
            }
            echo json_encode(['status' => 'ok', 'message' => 'Settings saved']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

function self_severity(float $mag): string {
    if ($mag >= 7.0) return 'extreme';
    if ($mag >= 6.0) return 'severe';
    if ($mag >= 5.0) return 'strong';
    if ($mag >= 4.0) return 'moderate';
    if ($mag >= 3.0) return 'light';
    return 'micro';
}
