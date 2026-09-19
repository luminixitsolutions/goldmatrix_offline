<?php

/**
 * Mail deliverability helpers (DNS checks + user-facing tips).
 */

/**
 * Extract domain from email address.
 */
function auragold_mail_domain_from_email(string $email): string
{
    $email = trim($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return '';
    }
    $parts = explode('@', $email);

    return strtolower(trim((string) ($parts[1] ?? '')));
}

/**
 * @return list<string>
 */
function auragold_mail_dns_txt_records(string $host): array
{
    $host = trim($host);
    if ($host === '') {
        return [];
    }
    $rows = @dns_get_record($host, DNS_TXT);
    if (!is_array($rows)) {
        return [];
    }
    $out = [];
    foreach ($rows as $r) {
        $txt = trim((string) ($r['txt'] ?? ''));
        if ($txt !== '') {
            $out[] = $txt;
        }
    }

    return $out;
}

/**
 * @return array{domain:string,smtp_host:string,smtp_ip:string,ptr:array<int,string>,mx:array<int,string>,spf:array<int,string>,dmarc:array<int,string>,dkim:array<int,string>,warnings:array<int,string>,tips:array<int,string>}
 */
function auragold_mail_deliverability_report(string $fromEmail, string $smtpHost = ''): array
{
    $domain = auragold_mail_domain_from_email($fromEmail);
    $smtpHost = trim($smtpHost);
    $report = [
        'domain'     => $domain,
        'smtp_host'  => $smtpHost,
        'smtp_ip'    => '',
        'ptr'        => [],
        'mx'         => [],
        'spf'        => [],
        'dmarc'      => [],
        'dkim'       => [],
        'warnings'   => [],
        'tips'       => [],
    ];

    if ($domain === '') {
        $report['warnings'][] = 'From email is invalid — set SMTP username / From email to noreply@yourdomain.com.';
        return $report;
    }

    if ($smtpHost !== '') {
        $ip = @gethostbyname($smtpHost);
        if ($ip !== '' && $ip !== $smtpHost) {
            $report['smtp_ip'] = $ip;
            $ptrRows = @dns_get_record($ip, DNS_PTR);
            if (is_array($ptrRows)) {
                foreach ($ptrRows as $ptr) {
                    $target = strtolower(rtrim(trim((string) ($ptr['target'] ?? '')), '.'));
                    if ($target !== '') {
                        $report['ptr'][] = $target;
                    }
                }
            }
            if ($report['ptr'] === []) {
                $report['warnings'][] = 'Reverse DNS (PTR) is MISSING for SMTP IP ' . $ip . '. Gmail often blocks or spams mail without PTR. Ask your host to set PTR for ' . $ip . ' → mail.' . $domain . ' (or ' . $smtpHost . ').';
            } else {
                $expected = [strtolower(rtrim($smtpHost, '.')), strtolower('mail.' . $domain), strtolower($domain)];
                $ptrOk = false;
                foreach ($report['ptr'] as $p) {
                    foreach ($expected as $exp) {
                        if ($p === $exp || str_ends_with($p, '.' . $domain)) {
                            $ptrOk = true;
                            break 2;
                        }
                    }
                }
                if (!$ptrOk) {
                    $report['warnings'][] = 'PTR record (' . implode(', ', $report['ptr']) . ') does not match SMTP host ' . $smtpHost . '. Ask host to align reverse DNS with your mail hostname.';
                }
            }
        }
    }

    $mxRows = @dns_get_record($domain, DNS_MX);
    if (is_array($mxRows)) {
        foreach ($mxRows as $mx) {
            $target = trim((string) ($mx['target'] ?? ''));
            if ($target !== '') {
                $report['mx'][] = $target;
            }
        }
    }
    if ($report['mx'] === []) {
        $report['warnings'][] = 'No MX records found for ' . $domain . '. Outbound mail may still work via SMTP, but receiving/reputation can suffer.';
    }

    $spfAll = auragold_mail_dns_txt_records($domain);
    foreach ($spfAll as $txt) {
        if (stripos($txt, 'v=spf1') === 0) {
            $report['spf'][] = $txt;
        }
    }
    if ($report['spf'] === []) {
        $report['warnings'][] = 'SPF record missing. In cPanel → Email Deliverability → Manage, install SPF for ' . $domain . '.';
    } else {
        $spfJoined = implode(' ', $report['spf']);
        if ($smtpHost !== '' && stripos($spfJoined, $smtpHost) === false && stripos($spfJoined, 'a:') === false && stripos($spfJoined, 'mx') === false && stripos($spfJoined, 'include:') === false) {
            $report['warnings'][] = 'SPF exists but may not authorize server ' . $smtpHost . '. Open Email Deliverability in cPanel and repair DNS.';
        }
    }

    $dmarcHost = '_dmarc.' . $domain;
    foreach (auragold_mail_dns_txt_records($dmarcHost) as $txt) {
        if (stripos($txt, 'v=DMARC1') === 0) {
            $report['dmarc'][] = $txt;
        }
    }
    if ($report['dmarc'] === []) {
        $report['warnings'][] = 'DMARC record missing. Gmail/Yahoo increasingly filter mail without DMARC. Add via cPanel Email Deliverability.';
    } elseif (stripos(implode(' ', $report['dmarc']), 'p=none') !== false) {
        $report['warnings'][] = 'DMARC policy is p=none (monitor only). For better Gmail trust, ask host to set p=quarantine or p=reject after monitoring.';
    }

    $dkimSelectors = ['default', 'default._domainkey', 'mail', 'selector1', 'selector2', 'k1'];
    $seenDkim = [];
    foreach ($dkimSelectors as $sel) {
        $hosts = [$sel . '._domainkey.' . $domain];
        if (strpos($sel, '_domainkey') === false && $sel !== 'default') {
            $hosts[] = $sel . '._domainkey.' . $domain;
        }
        foreach ($hosts as $h) {
            if (isset($seenDkim[$h])) {
                continue;
            }
            $seenDkim[$h] = true;
            foreach (auragold_mail_dns_txt_records($h) as $txt) {
                if (stripos($txt, 'v=DKIM1') !== false || stripos($txt, 'p=') !== false) {
                    $report['dkim'][] = $h . ': ' . (strlen($txt) > 80 ? substr($txt, 0, 77) . '…' : $txt);
                }
            }
        }
    }
    if ($report['dkim'] === []) {
        $report['warnings'][] = 'DKIM not detected in public DNS. In cPanel → Email Deliverability → Manage → enable DKIM for ' . $domain . ', then wait up to 24h for DNS propagation.';
    }

    $report['tips'] = [
        'GoldMatrix hands mail to your SMTP server successfully. Gmail inbox delivery depends on cPanel/Exim + server reputation (PTR, SPF, DKIM).',
        'FIRST: cPanel → Track Delivery → search your queue ID. If status is deferred/rejected to gmail.com, copy the error — that is the real reason.',
        'Test to your own mailbox: send to noreply@' . $domain . ' and check Roundcube/webmail. If that arrives but Gmail does not, the issue is Gmail ↔ your server IP.',
        'Check Gmail Spam, Promotions, and Updates — search from:noreply@' . $domain . '.',
        'If PTR is missing: open a ticket with your hosting provider (not fixable in GoldMatrix).',
        'Wait 30–60 min between test bursts — cPanel may discard mail after too many failures to Gmail.',
    ];

    return $report;
}
