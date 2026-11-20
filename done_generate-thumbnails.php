<?php
/**
 * generate-thumbnails.php — mrakibulislam.com
 *
 * One-time (and re-runnable) thumbnail fetcher for portfolio.html.
 * Uses thum.io (free, no API key) instead of the old ScreenshotOne
 * integration, which needs a paid key and was rendering broken images.
 *
 * HOW TO USE:
 * 1. Upload this file to the same directory as portfolio.html on your host.
 * 2. Make sure /images/portfolio/ exists and is writable:
 *      mkdir -p images/portfolio && chmod 755 images/portfolio
 * 3. Visit https://mrakibulislam.com/generate-thumbnails.php in a browser,
 *    or run it via CLI: php generate-thumbnails.php
 * 4. It downloads one JPG per project into /images/portfolio/{slug}.jpg.
 *    portfolio.html already points at these paths, so once the files
 *    exist the real screenshots appear — no other changes needed.
 * 5. Already-downloaded thumbnails are skipped on re-run, so it's safe
 *    to re-run any time you add a new project to the array below.
 * 6. DELETE OR PASSWORD-PROTECT THIS FILE after running it — it makes
 *    outbound HTTP requests on demand and shouldn't stay publicly
 *    executable indefinitely.
 */

// ── Projects needing a thumbnail (slug => live site URL) ──────────────
$projects = [
    ['slug' => 'dyln-co', 'url' => 'https://www.dyln.co'],
    ['slug' => 'pairofthieves-com', 'url' => 'https://pairofthieves.com'],
    ['slug' => 'taftclothing-com', 'url' => 'https://taftclothing.com'],
    ['slug' => 'solti-com', 'url' => 'https://solti.com'],
    ['slug' => 'georgestonecrab-com', 'url' => 'https://www.georgestonecrab.com'],
    ['slug' => 'sandbysaya-com', 'url' => 'https://sandbysaya.com'],
    ['slug' => 'naturaldog-com', 'url' => 'https://naturaldog.com'],
    ['slug' => 'permacultureplanet-co', 'url' => 'https://permacultureplanet.co'],
    ['slug' => 'circaoldhouses-com', 'url' => 'https://circaoldhouses.com'],
    ['slug' => 'littil-com-au', 'url' => 'https://www.littil.com.au'],
    ['slug' => 'eventbookings-com', 'url' => 'https://www.eventbookings.com'],
    ['slug' => 'stonecrabwholesale-com', 'url' => 'https://stonecrabwholesale.com'],
    ['slug' => 'propertymoose-co-uk', 'url' => 'https://propertymoose.co.uk'],
    ['slug' => 'ozrockexcavations-com-au', 'url' => 'https://ozrockexcavations.com.au'],
    ['slug' => 'brouwerijdemolen-nl', 'url' => 'https://brouwerijdemolen.nl'],
    ['slug' => 'cherryenergysolutions-com-au', 'url' => 'https://www.cherryenergysolutions.com.au'],
];

// ── Config ──────────────────────────────────────────────────────────
$outputDir   = __DIR__ . '/images/portfolio';
$thumWidth   = 1280;   // thum.io capture width
$userAgent   = 'Mozilla/5.0 (compatible; mrakibulislam-thumbnail-bot/1.0)';
$timeoutSecs = 25;

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

header('Content-Type: text/plain; charset=UTF-8');
echo "Thumbnail generation started — " . date('Y-m-d H:i:s') . "\n";
echo str_repeat('-', 60) . "\n";

$success = 0;
$skipped = 0;
$failed  = 0;

foreach ($projects as $project) {
    $slug     = $project['slug'];
    $url      = $project['url'];
    $destPath = "{$outputDir}/{$slug}.jpg";

    if (file_exists($destPath) && filesize($destPath) > 0) {
        echo "SKIP   {$slug} (already exists)\n";
        $skipped++;
        continue;
    }

    // thum.io free endpoint: width, then the target URL
    $thumUrl = "https://image.thum.io/get/width/{$thumWidth}/crop/800/noanimate/{$url}";

    $ch = curl_init($thumUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => $timeoutSecs,
        CURLOPT_USERAGENT      => $userAgent,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $imageData = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($imageData === false || $httpCode !== 200 || strlen($imageData) < 1000) {
        echo "FAIL   {$slug} — HTTP {$httpCode}" . ($curlError ? " ({$curlError})" : '') . "\n";
        $failed++;
        continue;
    }

    file_put_contents($destPath, $imageData);
    echo "OK     {$slug} — saved " . round(strlen($imageData) / 1024) . " KB\n";
    $success++;

    // Be polite to the free thum.io service between requests.
    usleep(400000); // 0.4s
}

echo str_repeat('-', 60) . "\n";
echo "Done. {$success} downloaded, {$skipped} skipped, {$failed} failed.\n";

if ($failed > 0) {
    echo "\nSome thumbnails failed. Re-run this script later — thum.io\n";
    echo "renders on demand and can take a couple of tries for slow sites.\n";
}
