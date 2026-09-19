<?php

/**
 * Suggested defaults when mail settings row is empty (no password stored here).
 *
 * @return array<string, mixed>
 */
function auragold_mail_settings_suggested_defaults(): array
{
    return [
        'smtp_host'       => 'mail.goldmatrixsoft.com',
        'smtp_port'       => 465,
        'smtp_encryption' => 'ssl',
        'smtp_username'   => 'noreply@goldmatrixsoft.com',
        'from_name'       => 'GoldMatrix',
        'from_email'      => 'noreply@goldmatrixsoft.com',
        'incoming_host'   => 'mail.goldmatrixsoft.com',
        'imap_port'       => 993,
        'pop3_port'       => 995,
    ];
}

/**
 * Merge saved row with suggested defaults for empty fields only.
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function auragold_mail_settings_with_defaults(array $row): array
{
    $suggested = auragold_mail_settings_suggested_defaults();
    foreach ($suggested as $key => $val) {
        if (!isset($row[$key]) || trim((string) $row[$key]) === '') {
            $row[$key] = $val;
        }
    }

    return $row;
}

/**
 * Build SMTP config for sending: saved row + optional form overrides (password kept from DB if blank).
 *
 * @param array<string, mixed> $saved
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function auragold_mail_settings_merge_for_send(array $saved, array $overrides = []): array
{
    $cfg = auragold_mail_settings_with_defaults($saved);
    $keys = [
        'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password',
        'from_name', 'from_email', 'incoming_host', 'imap_port', 'pop3_port',
    ];
    foreach ($keys as $key) {
        if (!array_key_exists($key, $overrides)) {
            continue;
        }
        $val = $overrides[$key];
        if ($key === 'smtp_password') {
            $pwd = preg_replace('/\s+/', '', trim((string) $val));
            if ($pwd !== '') {
                $cfg['smtp_password'] = $pwd;
            }
            continue;
        }
        if ($key === 'smtp_port' || $key === 'imap_port' || $key === 'pop3_port') {
            $cfg[$key] = (int) $val;
            continue;
        }
        $cfg[$key] = trim((string) $val);
    }

    $user = trim((string) ($cfg['smtp_username'] ?? ''));
    $from = trim((string) ($cfg['from_email'] ?? ''));
    if ($user !== '' && filter_var($user, FILTER_VALIDATE_EMAIL)) {
        if ($from === '' || strcasecmp($from, $user) !== 0) {
            $cfg['from_email'] = $user;
        }
    }

    return $cfg;
}

/**
 * @return array{ok:bool,message:string}
 */
function auragold_mail_settings_is_ready(array $cfg): array
{
    if (trim((string) ($cfg['smtp_host'] ?? '')) === '') {
        return ['ok' => false, 'message' => 'SMTP server is not configured. Open Settings → Mail Setting and save SMTP details.'];
    }
    if (trim((string) ($cfg['smtp_username'] ?? '')) === '' || trim((string) ($cfg['smtp_password'] ?? '')) === '') {
        return ['ok' => false, 'message' => 'SMTP username or password is missing. Save Mail Setting with your email account password.'];
    }
    if (trim((string) ($cfg['from_email'] ?? '')) === '') {
        return ['ok' => false, 'message' => 'From email is not set in Mail Setting.'];
    }

    return ['ok' => true, 'message' => ''];
}
