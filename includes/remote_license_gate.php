<?php
/**
 * Remote kill-switch: include this before session_start(), headers, or other output.
 * Remote file must contain exactly STOP (after trim) to halt; RUN or unreachable URL allows execution.
 */
if (defined('AURAGOLD_REMOTE_LICENSE_CHECKED')) {
    return;
}
define('AURAGOLD_REMOTE_LICENSE_CHECKED', true);

if (PHP_SAPI === 'cli') {
    return;
}

/** Set in config.php as $auragold_remote_license_url (empty = disabled). */
$license_url = isset($auragold_remote_license_url) ? trim((string) $auragold_remote_license_url) : '';
if ($license_url === '') {
    return;
}
/** Skip remote check on local dev — avoids 2s HTTP timeout on every cache miss. */
if (defined('AURAGOLD_PROJECT') && (string) AURAGOLD_PROJECT === 'local') {
    return;
}

/** Avoid blocking every page load on a slow/unreachable license URL (cache 1 hour). */
$__license_cache_file = __DIR__ . '/../cache/remote_license.cache';
$__license_cache_ttl  = 86400;
$__license_stop       = false;
if (is_file($__license_cache_file) && (time() - (int) filemtime($__license_cache_file)) < $__license_cache_ttl) {
    $__license_stop = trim((string) @file_get_contents($__license_cache_file)) === 'STOP';
} else {
    $license_ctx = stream_context_create([
        'http' => ['timeout' => 2, 'ignore_errors' => true],
        'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $license_response = @file_get_contents($license_url, false, $license_ctx);
    $__license_stop = ($license_response !== false && trim($license_response) === 'STOP');
    $cacheDir = dirname($__license_cache_file);
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    @file_put_contents($__license_cache_file, $__license_stop ? 'STOP' : 'OK');
}

if (!$__license_stop) {
    return;
}

header('Content-Type: text/html; charset=UTF-8', true, 503);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Software Temporarily Disabled</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Roboto, system-ui, -apple-system, "Segoe UI", Arial, sans-serif;
            background: linear-gradient(160deg, #eef1f8 0%, #f5f6fa 50%, #e8ecf4 100%);
            color: #2c3e50;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .box {
            text-align: center;
            background: #fff;
            max-width: 420px;
            width: 100%;
            padding: 48px 40px;
            border-radius: 12px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.08), 0 0 0 1px rgba(0, 0, 0, 0.04);
        }
        .icon {
            width: 56px;
            height: 56px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: #fdecea;
            color: #e74c3c;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            line-height: 1;
        }
        h1 {
            margin: 0 0 12px;
            font-size: 1.35rem;
            font-weight: 700;
            color: #c0392b;
        }
        p {
            margin: 0;
            font-size: 1rem;
            line-height: 1.5;
            color: #5d6d7e;
        }
    </style>
</head>
<body>
    <div class="box">
        <div class="icon" aria-hidden="true">!</div>
        <h1>Software Temporarily Disabled</h1>
        <p>Please Contact Developer.</p>
    </div>
</body>
</html>
<?php
exit;
