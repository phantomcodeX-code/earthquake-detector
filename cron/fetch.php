#!/usr/bin/env php
<?php
// =============================================
// cron/fetch.php — Background USGS Fetcher
// Run via cron: */1 * * * * php /path/to/cron/fetch.php
// =============================================

define('CRON_RUN', true);

// Handle CLI execution path
$root = dirname(__DIR__);
require_once $root . '/config.php';
require_once $root . '/includes/db.php';
require_once $root . '/includes/fetcher.php';

$start = microtime(true);
$ts    = date('Y-m-d H:i:s');

echo "[{$ts}] SeismoWatch Cron Fetch starting...\n";

try {
    $newEvents = USGSFetcher::fetchAll();
    $alertMag  = (float)Database::getSetting('alert_magnitude', '4.5');
    $phMinMag  = (float)Database::getSetting('ph_min_mag', '2.0');

    $alertCount = 0;
    foreach ($newEvents as $ev) {
        $isPH    = USGSFetcher::isPhilippines($ev['latitude'], $ev['longitude']);
        $isAlert = $ev['magnitude'] >= $alertMag
                   || ($isPH && $ev['magnitude'] >= $phMinMag)
                   || $ev['tsunami'] == 1;

        echo sprintf(
            "  [%s] M%.1f — %s%s%s\n",
            $ev['event_time'],
            $ev['magnitude'],
            $ev['place'],
            $isPH ? ' [PH]' : '',
            $ev['tsunami'] ? ' [TSUNAMI!]' : ''
        );

        if ($isAlert) {
            $alertCount++;
            $db = Database::connect();
            $db->prepare('UPDATE earthquakes SET notified=0 WHERE id=? AND notified=1')
               ->execute([$ev['id']]);
            // Re-flag as pending so next page load picks it up
            $db->prepare('UPDATE earthquakes SET notified=0 WHERE id=?')
               ->execute([$ev['id']]);
        }
    }

    $elapsed = round((microtime(true) - $start) * 1000, 1);
    echo "[{$ts}] Done. New events: " . count($newEvents)
       . " | Alerts: {$alertCount} | Elapsed: {$elapsed}ms\n";

} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
