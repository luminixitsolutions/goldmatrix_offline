<?php
session_start();
require_once __DIR__ . '/config.php';

$notif_uid = (int) ($_SESSION['user_id'] ?? $_SESSION['Admin']['id'] ?? 0);
if ($notif_uid <= 0) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <title>Notifications — GoldMatrix</title>
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include __DIR__ . '/header-script.php'; ?>
    <style>
        html, body { overflow-x: hidden; min-height: 100vh; background: #f0f2f7; }
        .layout-content { min-height: calc(100vh - 60px); }
        .notif-page-wrap { max-width: 920px; margin: 0 auto; padding: 16px 16px 32px; }
        .notif-page-top {
            background: linear-gradient(180deg, #f8f6f1 0%, #efe8dc 100%);
            border: 1px solid rgba(17, 41, 75, 0.12);
            border-bottom: 3px solid #c5a864;
            border-radius: 10px 10px 0 0;
            padding: 14px 20px;
            font-weight: 700;
            font-size: 1.05rem;
            color: #11294b;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }
        .notif-page-panel {
            background: #fff;
            border: 1px solid rgba(17, 41, 75, 0.12);
            border-top: none;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 10px 28px rgba(17, 41, 75, 0.08), 0 0 1px rgba(197, 168, 100, 0.4);
            overflow: hidden;
        }
        .notif-page-tabs {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0;
            padding: 0 8px 0 12px;
            border-bottom: 2px solid #e2e8f0;
            background: #fafbfc;
        }
        .notif-page-tabs .notif-tab {
            border: none;
            background: transparent;
            padding: 12px 16px 10px;
            font-size: 0.9rem;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: color 0.15s, border-color 0.15s;
        }
        .notif-page-tabs .notif-tab:hover { color: #11294b; background: rgba(17, 41, 75, 0.04); }
        .notif-page-tabs .notif-tab.active {
            color: #11294b;
            border-bottom-color: #c5a864;
        }
        .notif-page-tabs .notif-tab-spacer { flex: 1; min-width: 8px; }
        .notif-mark-all-global {
            border: none;
            background: none;
            padding: 10px 14px;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #a68a4a;
            cursor: pointer;
            white-space: nowrap;
        }
        .notif-mark-all-global:hover { color: #8b7139; text-decoration: underline; }
        .notif-page-list {
            padding: 16px 18px 20px;
            min-height: 200px;
            max-height: calc(100vh - 280px);
            overflow-y: auto;
            background: linear-gradient(180deg, #faf9f7 0%, #ffffff 48%);
        }
        .notif-page-card {
            border: 1px solid rgba(17, 41, 75, 0.1);
            border-radius: 10px;
            padding: 14px 16px 14px 18px;
            margin-bottom: 14px;
            background: #fff;
            border-left: 5px solid #c5a864;
            box-shadow: 0 2px 6px rgba(17, 41, 75, 0.05);
        }
        .notif-page-card.is-unread {
            background: linear-gradient(to right, rgba(197, 168, 100, 0.2), #fff 46%);
            border-left-color: #a68a4a;
        }
        .notif-page-card-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 8px;
        }
        .notif-page-card-title {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 700;
            color: #11294b;
            line-height: 1.3;
        }
        .notif-page-card-mark {
            border: none;
            background: none;
            padding: 0;
            font-size: 0.8rem;
            font-weight: 600;
            color: #a68a4a;
            cursor: pointer;
            white-space: nowrap;
            text-decoration: none;
        }
        .notif-page-card-mark:hover { text-decoration: underline; color: #8b7139; }
        .notif-page-card-body {
            margin: 0 0 10px;
            font-size: 0.875rem;
            line-height: 1.55;
            color: #475569;
        }
        .notif-page-card-time {
            text-align: right;
            font-size: 0.78rem;
            color: #64748b;
            text-decoration: underline;
            text-underline-offset: 2px;
            text-decoration-color: rgba(197, 168, 100, 0.7);
        }
        .notif-page-empty {
            text-align: center;
            padding: 48px 16px;
            color: #64748b;
            font-size: 0.95rem;
        }
        .notif-page-loading { text-align: center; padding: 40px; color: #94a3b8; }
        .notif-page-err { text-align: center; padding: 24px; color: #b91c1c; font-size: 0.9rem; }
        @media (max-width: 576px) {
            .notif-page-tabs .notif-tab { padding-left: 10px; padding-right: 10px; font-size: 0.82rem; }
            .notif-mark-all-global { font-size: 0.72rem; padding: 10px 8px; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/sidebar.php'; ?>

<div class="layout-content">
    <div class="container-fluid flex-grow-1" style="padding-top:0;padding-bottom:0;">
        <div class="notif-page-wrap">
            <div class="notif-page-top">Notifications</div>
            <div class="notif-page-panel">
                <div class="notif-page-tabs" role="tablist">
                    <button type="button" class="notif-tab active" data-filter="all" role="tab" aria-selected="true">All</button>
                    <button type="button" class="notif-tab" data-filter="unread" role="tab" aria-selected="false">Unread</button>
                    <button type="button" class="notif-tab" data-filter="read" role="tab" aria-selected="false">Read</button>
                    <span class="notif-tab-spacer" aria-hidden="true"></span>
                    <button type="button" class="notif-mark-all-global" id="notifPageMarkAll">Mark all as Read</button>
                </div>
                <div class="notif-page-list" id="notifPageList"></div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var feedBase = 'ajax/notifications-feed.php';
    var listEl = document.getElementById('notifPageList');
    var currentFilter = 'all';

    function renderItem(it) {
        var unread = !!it.unread;
        var card = document.createElement('article');
        card.className = 'notif-page-card' + (unread ? ' is-unread' : '');
        var head = document.createElement('div');
        head.className = 'notif-page-card-head';
        var h = document.createElement('h2');
        h.className = 'notif-page-card-title';
        h.textContent = it.title || 'Notification';
        var mark = document.createElement('button');
        mark.type = 'button';
        mark.className = 'notif-page-card-mark';
        mark.textContent = 'Mark as Read';
        mark.style.visibility = unread ? 'visible' : 'hidden';
        mark.addEventListener('click', function () {
            if (!unread) return;
            var fd = new FormData();
            fd.append('id', String(it.id));
            fetch(feedBase + '?action=mark_one', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function () { loadList(currentFilter); })
                .catch(function () {});
        });
        head.appendChild(h);
        head.appendChild(mark);
        var body = document.createElement('p');
        body.className = 'notif-page-card-body';
        body.textContent = it.message || '';
        var time = document.createElement('div');
        time.className = 'notif-page-card-time';
        time.textContent = it.time_ago || '';
        card.appendChild(head);
        card.appendChild(body);
        card.appendChild(time);
        return card;
    }

    function loadList(filter) {
        currentFilter = filter || 'all';
        listEl.innerHTML = '';
        var load = document.createElement('div');
        load.className = 'notif-page-loading';
        load.textContent = 'Loading…';
        listEl.appendChild(load);
        fetch(feedBase + '?action=list&filter=' + encodeURIComponent(currentFilter) + '&limit=200', {
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                listEl.innerHTML = '';
                if (!d || !d.ok || !Array.isArray(d.items)) {
                    listEl.innerHTML = '<div class="notif-page-err">Could not load notifications.</div>';
                    return;
                }
                if (d.items.length === 0) {
                    listEl.innerHTML = '<div class="notif-page-empty">No notifications in this tab.</div>';
                    return;
                }
                d.items.forEach(function (it) {
                    listEl.appendChild(renderItem(it));
                });
            })
            .catch(function () {
                listEl.innerHTML = '<div class="notif-page-err">Could not load notifications.</div>';
            });
    }

    document.querySelectorAll('.notif-page-tabs .notif-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.notif-page-tabs .notif-tab').forEach(function (b) {
                b.classList.toggle('active', b === btn);
                b.setAttribute('aria-selected', b === btn ? 'true' : 'false');
            });
            loadList(btn.getAttribute('data-filter') || 'all');
        });
    });

    document.getElementById('notifPageMarkAll').addEventListener('click', function () {
        var fd = new FormData();
        fd.append('action', 'mark_all');
        fetch(feedBase + '?action=mark_all', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function () { loadList(currentFilter); })
            .catch(function () {});
    });

    loadList('all');
})();
</script>
</body>
</html>
