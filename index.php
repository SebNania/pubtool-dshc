<?php
/**
 * DSHC — page publique. Style Apple/iOS, clair + sombre automatique.
 * Auto-suffisant : CSS et JS en ligne, aucune ressource externe.
 */
declare(strict_types=1);
require __DIR__ . '/dshc.php';

$p       = ds_payload();
$creuse  = $p['off_peak'];
$stClass = $creuse ? 'ok' : 'warn';

/* URL publique de l'API, calculée depuis la requête */
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
         . rtrim(dirname($_SERVER['PHP_SELF'] ?? '/dshc/index.php'), '/');
$apiUrl  = $baseUrl . '/api.php';

/* URL publique réelle (jamais dérivée du Host de la requête) : sert au
   canonical et à la carte de partage. */
$canon   = DS_PUBLIC_BASE . '/';
$ogImage = DS_PUBLIC_BASE . '/og.png';
$icon    = DS_PUBLIC_BASE . '/icon.png';
$favSvg  = DS_PUBLIC_BASE . '/favicon.svg';
$favIco  = DS_PUBLIC_BASE . '/favicon.ico';

$h = static fn (?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

/* Aperçu 14 jours */
$holIdx  = ds_holiday_index();
$mkIdx   = ds_makeup_index();
$preview = [];
for ($i = 0; $i < 14; $i++) {
    $d    = gmdate('Y-m-d', time() + $i * 86400);
    $iso  = (int) gmdate('N', strtotime($d . ' 12:00:00 UTC'));
    $mk   = isset($mkIdx[$d]);
    $holi = $holIdx[$d] ?? null;
    $type = $holi !== null ? 'holiday' : (($iso >= 6 && !(DS_MAKEUP_PEAK && $mk)) ? 'weekend' : 'workday');
    $preview[] = [
        'date'    => $d,
        'label'   => ds_day_name($iso) . ' ' . gmdate('d/m', strtotime($d . ' 12:00:00 UTC')),
        'today'   => $i === 0,
        'type'    => $type,
        'holiday' => $holi,
        'makeup'  => $mk,
    ];
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">
<meta name="author" content="Printland">
<meta name="description" content="<?= $h(DS_OG_DESC) ?>">
<meta name="theme-color" content="#f2f2f7" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#000000" media="(prefers-color-scheme: dark)">
<link rel="canonical" href="<?= $h($canon) ?>">
<!-- Icône d'onglet : SVG pour les navigateurs modernes, .ico (16.32.48) en
     secours universel, PNG 180 pour l'écran d'accueil iOS/Android. -->
<link rel="icon" type="image/svg+xml" href="<?= $h($favSvg) ?>">
<link rel="icon" href="<?= $h($favIco) ?>" sizes="any">
<link rel="apple-touch-icon" sizes="180x180" href="<?= $h($icon) ?>">
<!-- Carte de partage (Facebook, WhatsApp, X, Telegram, Slack, Discord, LinkedIn).
     Les robots de ces plateformes sont autorisés dans robots.txt ; les moteurs
     de recherche restent exclus (pas de référencement de l'outil). -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="Printland">
<meta property="og:locale" content="fr_FR">
<meta property="og:url" content="<?= $h($canon) ?>">
<meta property="og:title" content="<?= $h(DS_OG_TITLE) ?>">
<meta property="og:description" content="<?= $h(DS_OG_DESC) ?>">
<meta property="og:image" content="<?= $h($ogImage) ?>">
<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="DeepSeek : heure creuse (−50 %) ou heure pleine (×2) ?">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= $h(DS_OG_TITLE) ?>">
<meta name="twitter:description" content="<?= $h(DS_OG_DESC) ?>">
<meta name="twitter:image" content="<?= $h($ogImage) ?>">
<title><?= $h(DS_TITLE) ?></title>
<style>
:root{
  color-scheme: light dark;
  --bg:#f2f2f7; --card:#fff; --field:rgba(118,118,128,.12); --field2:#e8e8ed;
  --text:#1d1d1f; --text2:#6e6e73; --sep:rgba(0,0,0,.08); --glass:rgba(255,255,255,.72);
  --accent:#0071e3; --link:#0066cc;
  --ok:#34c759; --ok-soft:rgba(52,199,89,.12);
  --warn:#ff9500; --warn-soft:rgba(255,149,0,.14);
  --shadow:0 1px 3px rgba(0,0,0,.06);
}
@media (prefers-color-scheme: dark){
  :root{
    --bg:#000; --card:#1c1c1e; --field:rgba(118,118,128,.24); --field2:#3a3a3c;
    --text:#f5f5f7; --text2:#a1a1a6; --sep:rgba(255,255,255,.10); --glass:rgba(22,22,24,.78);
    --accent:#2997ff; --link:#2997ff;
    --ok:#30d158; --ok-soft:rgba(48,209,88,.16);
    --warn:#ff9f0a; --warn-soft:rgba(255,159,10,.18);
    --shadow:0 1px 3px rgba(0,0,0,.5);
  }
}
*{box-sizing:border-box}
html,body{margin:0;padding:0}
body{
  font-family:-apple-system,BlinkMacSystemFont,"SF Pro Display","SF Pro Text","Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
  background:var(--bg); color:var(--text); letter-spacing:-.01em;
  -webkit-font-smoothing:antialiased;
}
.head{
  position:sticky; top:0; z-index:5; background:var(--glass);
  backdrop-filter:saturate(180%) blur(20px); -webkit-backdrop-filter:saturate(180%) blur(20px);
  border-bottom:.5px solid var(--sep);
}
.head-in{max-width:680px;margin:0 auto;padding:12px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px}
.head b{font-size:.95rem;font-weight:600}
.head a{color:var(--link);text-decoration:none;font-size:.85rem;font-weight:500}
.wrap{max-width:680px;margin:0 auto;padding:22px 20px 60px}
h1{font-size:1.75rem;font-weight:700;letter-spacing:-.02em;line-height:1.15;margin:.2em 0 .1em}
.sub{color:var(--text2);font-size:.95rem;margin:0 0 20px}
.card{background:var(--card);border-radius:16px;padding:18px;box-shadow:var(--shadow);margin-bottom:16px}
.st{display:flex;align-items:center;gap:16px}
.dot{width:54px;height:54px;border-radius:50%;flex:0 0 54px;display:flex;align-items:center;justify-content:center;font-size:1.6rem}
.st.ok  .dot{background:var(--ok-soft);color:var(--ok)}
.st.warn .dot{background:var(--warn-soft);color:var(--warn)}
.st .big{font-size:1.5rem;font-weight:700;line-height:1.2}
.st.ok  .big{color:var(--ok)}
.st.warn .big{color:var(--warn)}
.st .why{color:var(--text2);font-size:.88rem;margin-top:3px}
.sect{font-size:.78rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--text2);margin:0 0 12px}
.grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.clock{background:var(--field);border-radius:12px;padding:12px 10px;text-align:center}
.clock .lab{font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text2);font-weight:600}
.clock .val{font-variant-numeric:tabular-nums;font-size:1.35rem;font-weight:600;margin-top:4px}
.clock.utc{background:var(--accent);color:#fff}
.clock.utc .lab{color:rgba(255,255,255,.8)}
.clock .sm{font-size:.72rem;color:var(--text2);margin-top:2px}
.row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:11px 0;border-bottom:.5px solid var(--sep)}
.row:last-child{border-bottom:0;padding-bottom:0}
.row:first-of-type{padding-top:0}
.row .k{color:var(--text2);font-size:.9rem}
.row .v{font-weight:600;font-variant-numeric:tabular-nums;text-align:right}
.pill{display:inline-block;background:var(--field);border-radius:999px;padding:4px 11px;font-size:.8rem;font-weight:600;font-variant-numeric:tabular-nums;margin:0 6px 6px 0}
.pill.hot{background:var(--warn-soft);color:var(--warn)}
.pill.cool{background:var(--ok-soft);color:var(--ok)}
table{width:100%;border-collapse:collapse;font-size:.88rem}
th,td{text-align:left;padding:8px 6px;border-bottom:.5px solid var(--sep)}
th{font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text2);font-weight:600}
td.n{text-align:right;font-variant-numeric:tabular-nums;color:var(--text2)}
tr.today td{font-weight:700}
.badge{font-size:.72rem;font-weight:600;border-radius:6px;padding:2px 7px;white-space:nowrap}
.badge.wd{background:var(--warn-soft);color:var(--warn)}
.badge.we{background:var(--ok-soft);color:var(--ok)}
.badge.hl{background:var(--accent);color:#fff}
code,pre{font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
pre{background:var(--field);border-radius:12px;padding:12px;overflow-x:auto;font-size:.78rem;line-height:1.55;margin:0}
.note{color:var(--text2);font-size:.82rem;line-height:1.5}
.hol{background:var(--accent);color:#fff;border-radius:12px;padding:11px 14px;font-size:.88rem;margin-top:14px}
.warnbox{background:var(--warn-soft);color:var(--warn);border-radius:12px;padding:11px 14px;font-size:.82rem;margin-bottom:16px;line-height:1.5}
details{margin-top:12px}
summary{cursor:pointer;color:var(--link);font-size:.88rem;font-weight:500}
footer{color:var(--text2);font-size:.76rem;line-height:1.6;margin-top:24px;text-align:center}
@media (max-width:520px){
  .grid3{grid-template-columns:1fr 1fr}
  .clock.utc{grid-column:span 2}
  h1{font-size:1.5rem}
}
</style>
</head>
<body>

<div class="head"><div class="head-in">
  <b><?= $h(DS_TITLE) ?></b>
  <a href="<?= $h($apiUrl) ?>">API JSON</a>
</div></div>

<div class="wrap">
  <h1>Tarif DeepSeek en direct</h1>
  <p class="sub">Heure creuse&nbsp;= −50&nbsp;% sur tous les tokens. Fenêtres de pointe&nbsp;:
     <?= $h(implode(' et ', DS_WINDOWS)) ?> UTC, du lundi au vendredi.</p>

<?php foreach ($p['warnings'] as $w): ?>
  <div class="warnbox">⚠︎ <?= $h($w) ?></div>
<?php endforeach; ?>

  <div class="card">
    <div class="st <?= $stClass ?>" id="st">
      <div class="dot" id="dot"><?= $creuse ? '💰' : '⏳' ?></div>
      <div>
        <div class="big" id="state-label"><?= $h($p['label']) ?><?= $creuse ? ' (Off-Peak)' : ' (Peak)' ?></div>
        <div class="why" id="state-sub"><?= $h($p['reason_label']) ?>
          <?= $creuse ? '— tarif réduit de 50&nbsp;%' : '— tarif normal (×2)' ?></div>
      </div>
    </div>
    <?php if ($p['holiday']): ?>
      <div class="hol">
        🎉 Jour férié en Chine&nbsp;: <b><?= $h($p['holiday']['fr']) ?></b>
        (<?= $h($p['holiday']['cn']) ?>) — <?= $h($p['holiday']['from']) ?> → <?= $h($p['holiday']['to']) ?>.
        Heure creuse pendant toute la période.
      </div>
    <?php elseif ($p['next_holiday']): ?>
      <div class="note" style="margin-top:14px">
        Prochain jour férié chinois&nbsp;: <b><?= $h($p['next_holiday']['fr']) ?></b>
        (<?= $h($p['next_holiday']['from']) ?> → <?= $h($p['next_holiday']['to']) ?>)
        — heure creuse sur toute la période.
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <p class="sect">Horloges</p>
    <div class="grid3">
      <div class="clock utc">
        <div class="lab">UTC — DeepSeek</div>
        <div class="val" id="c-utc"><?= $h($p['now']['utc_time_s']) ?></div>
        <div class="sm" id="c-utc-date"><?= $h($p['now']['utc_date']) ?></div>
      </div>
      <div class="clock">
        <div class="lab">Pékin</div>
        <div class="val" id="c-cn"><?= $h($p['now']['beijing_time']) ?></div>
        <div class="sm">UTC+8</div>
      </div>
      <div class="clock">
        <div class="lab">Local</div>
        <div class="val" id="c-local"><?= $h($p['now']['local_time']) ?></div>
        <div class="sm" id="c-local-tz"><?= $h($p['now']['local_tz']) ?></div>
      </div>
    </div>
  </div>

  <div class="card">
    <p class="sect">Prochaine bascule</p>
    <div class="row">
      <span class="k">Dans</span>
      <span class="v" id="next-in"><?= $p['next_change'] ? $h($p['next_change']['in_human']) : '—' ?></span>
    </div>
    <div class="row">
      <span class="k">Devient</span>
      <span class="v" id="next-becomes"><?= $p['next_change'] ? $h($p['next_change']['becomes_label']) : '—' ?></span>
    </div>
    <div class="row">
      <span class="k">Heure UTC</span>
      <span class="v" id="next-at"><?= $p['next_change'] ? $h(substr($p['next_change']['at_utc'], 11, 5)) : '—' ?></span>
    </div>
    <div class="row">
      <span class="k">Début heure creuse</span>
      <span class="v"><?= $p['next_off_peak_start'] ? $h(ds_human($p['next_off_peak_start']['in_seconds'])) : 'en cours' ?></span>
    </div>
    <div class="row">
      <span class="k">Début plage de pointe</span>
      <span class="v"><?= $p['next_peak_start'] ? $h(ds_human($p['next_peak_start']['in_seconds'])) : '—' ?></span>
    </div>
  </div>

  <div class="card">
    <p class="sect">Plages de pointe (heure UTC)</p>
    <?php foreach (DS_WINDOWS as $w): ?>
      <span class="pill hot"><?= $h($w) ?></span>
    <?php endforeach; ?>
    <span class="pill cool">17 h / jour creuses</span>
    <p class="note" style="margin:12px 0 0">
      Du lundi au vendredi&nbsp;: pointe de 01:00 à 04:00 et de 06:00 à 10:00 UTC, tout le reste est creux.
      Samedis, dimanches et jours fériés chinois&nbsp;: creux sur 24&nbsp;h.
      <br>Soit, en heure locale&nbsp;:
      <?php
      $tzL = new DateTimeZone(DS_TZ_LOCAL);
      $loc = [];
      foreach (DS_WINDOWS as $w) {
          [$a, $b] = explode('-', $w);
          $loc[] = (new DateTimeImmutable(gmdate('Y-m-d') . ' ' . $a . ':00', new DateTimeZone('UTC')))
                        ->setTimezone($tzL)->format('H:i')
                 . ' à '
                 . (new DateTimeImmutable(gmdate('Y-m-d') . ' ' . $b . ':00', new DateTimeZone('UTC')))
                        ->setTimezone($tzL)->format('H:i');
      }
      echo $h(implode(' et de ', $loc)) . ' (' . $h((new DateTimeImmutable('now', $tzL))->format('T')) . ')';
      ?>
    </p>
  </div>

  <div class="card">
    <p class="sect">14 prochains jours</p>
    <table>
      <thead><tr><th>Jour</th><th>Type</th><th>Plages UTC</th></tr></thead>
      <tbody>
      <?php foreach ($preview as $d): ?>
        <tr class="<?= $d['today'] ? 'today' : '' ?>">
          <td><?= $h($d['label']) ?></td>
          <td>
            <?php if ($d['type'] === 'holiday'): ?>
              <span class="badge hl"><?= $h($d['holiday']['fr']) ?></span>
            <?php elseif ($d['type'] === 'weekend'): ?>
              <span class="badge we">week-end</span>
            <?php else: ?>
              <span class="badge wd">ouvré</span>
            <?php endif; ?>
          </td>
          <td class="n"><?= $d['type'] === 'workday' ? '01-04 et 06-10' : 'aucune' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="note" style="margin:12px 0 0">
      Attention&nbsp;: ces jours sont des journées UTC. Une journée «&nbsp;ouvrée&nbsp;» contient les deux plages de pointe.
    </p>
  </div>

  <div class="card">
    <p class="sect">API — lecture seule</p>
    <pre>curl <?= $h($apiUrl) ?>
curl <?= $h($apiUrl) ?>?format=text
curl "<?= $h($apiUrl) ?>?at=2026-10-01T02:30Z"
curl "<?= $h($apiUrl) ?>?action=month&amp;month=2026-10"</pre>
    <details>
      <summary>Capteur Home Assistant (remplace le calcul dans configuration.yaml)</summary>
      <pre>rest:
  - resource: <?= $h($apiUrl) ?>

    scan_interval: 60
    sensor:
      - name: "DeepSeek Tariff Status"
        unique_id: deepseek_tariff_status
        value_template: >-
          {{ 'Heure Creuse (Off-Peak)' if value_json.off_peak
             else 'Heure Pleine (Peak)' }}
        json_attributes:
          - state
          - reason
          - reason_label
          - utc_time
          - next_change
        icon: mdi:currency-usd
</pre>
    </details>
    <p class="note" style="margin:12px 0 0">
      Champs&nbsp;: <code>off_peak</code>, <code>state</code>, <code>label</code>, <code>discount_pct</code>,
      <code>reason</code>, <code>now.utc</code>, <code>next_change</code>, <code>next_peak_start</code>,
      <code>next_off_peak_start</code>, <code>holiday</code>, <code>peak_windows_utc</code>, <code>warnings</code>.
      <code>?fields=state,next_change</code> pour une réponse réduite.
    </p>
  </div>

  <footer>
    Barème&nbsp;: documentation officielle DeepSeek (<i>Off-peak rates are half of the peak rates…</i>).<br>
    Jours fériés&nbsp;: Conseil d'État chinois, 国办发明电〔2025〕7号 (2026) et 〔2024〕12号 (2025).<br>
    Page rechargée automatiquement toutes les 60&nbsp;s — aucune donnée collectée.
  </footer>
</div>

<script>
(function () {
  var SERVER_TS = <?= (int) $p['now']['timestamp'] ?>;   // secondes UTC, horloge serveur
  var T0 = Date.now();
  var pad = function (n) { return (n < 10 ? '0' : '') + n; };

  function tick() {
    var d = new Date(SERVER_TS * 1000 + (Date.now() - T0));
    var u = [pad(d.getUTCHours()), pad(d.getUTCMinutes()), pad(d.getUTCSeconds())].join(':');
    var cn = new Date(d.getTime() + 8 * 3600 * 1000);
    var el;
    if ((el = document.getElementById('c-utc'))) el.textContent = u;
    if ((el = document.getElementById('c-utc-date'))) {
      el.textContent = d.getUTCFullYear() + '-' + pad(d.getUTCMonth() + 1) + '-' + pad(d.getUTCDate());
    }
    if ((el = document.getElementById('c-cn'))) el.textContent = pad(cn.getUTCHours()) + ':' + pad(cn.getUTCMinutes());
    if ((el = document.getElementById('c-local'))) {
      el.textContent = pad(d.getHours()) + ':' + pad(d.getMinutes());
      var tz = document.getElementById('c-local-tz');
      if (tz) { tz.textContent = (Intl.DateTimeFormat().resolvedOptions().timeZone || 'local'); }
    }
  }
  tick();
  setInterval(tick, 1000);

  function refresh() {
    fetch('api.php', { cache: 'no-store' }).then(function (r) { return r.ok ? r.json() : null; })
      .then(function (j) {
        if (!j || !j.ok) { return; }
        SERVER_TS = j.now.timestamp;
        T0 = Date.now();
        var box = document.getElementById('st');
        box.className = 'st ' + (j.off_peak ? 'ok' : 'warn');
        document.getElementById('dot').textContent = j.off_peak ? '💰' : '⏳';
        document.getElementById('state-label').textContent = j.label + (j.off_peak ? ' (Off-Peak)' : ' (Peak)');
        document.getElementById('state-sub').textContent = j.reason_label;
        var nxt = j.next_change;
        document.getElementById('next-in').textContent = nxt ? nxt.in_human : '—';
        document.getElementById('next-becomes').textContent = nxt ? nxt.becomes_label : '—';
        document.getElementById('next-at').textContent = nxt ? nxt.at_utc.substr(11, 5) : '—';
      })['catch'](function () { /* hors ligne : on garde l'affichage précédent */ });
  }
  setInterval(refresh, 60000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) { refresh(); } });
})();
</script>
</body>
</html>
