<?php
/**
 * DSHC — tests de la logique tarifaire.
 * Usage : php tests/run.php
 */
declare(strict_types=1);
require __DIR__ . '/../dshc.php';

$GLOBALS['fail'] = 0;
$GLOBALS['n']    = 0;

function check(string $label, mixed $got, mixed $want): void
{
    $GLOBALS['n']++;
    if ($got === $want) {
        printf("  ✓ %s\n", $label);
        return;
    }
    $GLOBALS['fail']++;
    printf("  ✗ %s\n      got  = %s\n      want = %s\n", $label,
        var_export($got, true), var_export($want, true));
}

/** ds_state() à un instant UTC donné (ISO 8601). */
function st(string $iso): array
{
    return ds_state(new DateTimeImmutable($iso, new DateTimeZone('UTC')));
}
function peak(string $iso): bool
{
    return st($iso)['peak'];
}
function reason(string $iso): string
{
    return st($iso)['reason'];
}

echo "\n=== 1. Fenêtres de pointe (semaine ouvrée : mardi 29/09/2026) ===\n";
check('23:07 UTC lundi 28/09 → creuse',        peak('2026-09-28T23:07:00Z'), false);
check('00:59 UTC mardi 29/09 → creuse',        peak('2026-09-29T00:59:00Z'), false);
check('01:00 UTC (début 1re plage) → pointe',  peak('2026-09-29T01:00:00Z'), true);
check('01:30 UTC → pointe',                    peak('2026-09-29T01:30:00Z'), true);
check('03:59 UTC (fin 1re plage) → pointe',    peak('2026-09-29T03:59:00Z'), true);
check('04:00 UTC (trou 04-06) → creuse',       peak('2026-09-29T04:00:00Z'), false);
check('05:59 UTC (trou) → creuse',             peak('2026-09-29T05:59:00Z'), false);
check('06:00 UTC (début 2e plage) → pointe',   peak('2026-09-29T06:00:00Z'), true);
check('09:59 UTC (fin 2e plage) → pointe',     peak('2026-09-29T09:59:00Z'), true);
check('10:00 UTC → creuse',                    peak('2026-09-29T10:00:00Z'), false);
check('12:00 UTC → creuse',                    peak('2026-09-29T12:00:00Z'), false);
check('motif 01:30 UTC = peak_window',         reason('2026-09-29T01:30:00Z'), 'peak_window');
check('motif 12:00 UTC = outside_windows',     reason('2026-09-29T12:00:00Z'), 'outside_windows');

echo "\n=== 2. Week-ends ===\n";
check('samedi 26/09 02:00 UTC → creuse',       peak('2026-09-26T02:00:00Z'), false);
check('dimanche 27/09 02:00 UTC → creuse',     peak('2026-09-27T02:00:00Z'), false);
check('motif samedi 19/09 = weekend',          reason('2026-09-19T02:00:00Z'), 'weekend');
check('samedi 03/10 21:00 UTC → creuse',       peak('2026-10-03T21:00:00Z'), false);
check('un samedi férié est signalé « holiday » (priorité au motif le plus précis)',
      reason('2026-09-26T02:00:00Z'), 'holiday');

echo "\n=== 3. Jours fériés chinois 2026 (source : 国办发明电〔2025〕7号) ===\n";
check('mi-automne ven 25/09 02:00 → creuse',   peak('2026-09-25T02:00:00Z'), false);
check('mi-automne motif = holiday',            reason('2026-09-25T02:00:00Z'), 'holiday');
check('mi-automne nom = Fête de la mi-automne',
      st('2026-09-25T02:00:00Z')['holiday']['fr'], 'Fête de la mi-automne');
check('fête nationale jeu 01/10 02:30 → creuse', peak('2026-10-01T02:30:00Z'), false);
check('fête nationale nom = Fête nationale',   st('2026-10-01T02:30:00Z')['holiday']['fr'], 'Fête nationale');
check('fête nationale mer 07/10 09:00 → creuse', peak('2026-10-07T09:00:00Z'), false);
check('reprise jeu 08/10 02:00 → pointe',      peak('2026-10-08T02:00:00Z'), true);
check('Nouvel An chinois 15/02/2026 → férié',  st('2026-02-15T02:00:00Z')['holiday']['cn'], '春节');
check('Nouvel An chinois dernier jour 23/02',  peak('2026-02-23T02:00:00Z'), false);
check('lendemain 24/02/2026 02:00 → pointe',   peak('2026-02-24T02:00:00Z'), true);
check('Qingming 06/04/2026 (lundi) → creuse',  peak('2026-04-06T02:00:00Z'), false);
check('Qingming 03/04/2026 (vendredi) → pointe', peak('2026-04-03T02:00:00Z'), true);
check('Travail 04/05/2026 (lundi) → creuse',   peak('2026-05-04T02:00:00Z'), false);
check('Travail 06/05/2026 (mercredi) → pointe', peak('2026-05-06T02:00:00Z'), true);
check('Duanwu 19/06/2026 (vendredi) → creuse', peak('2026-06-19T02:00:00Z'), false);

echo "\n=== 4. Jours fériés 2025 (source : 国办发明电〔2024〕12号) ===\n";
check('fête nationale+mi-automne 03/10/2025 → creuse', peak('2025-10-03T02:00:00Z'), false);
check('Nouvel An chinois 29/01/2025 → férié',  st('2025-01-29T02:00:00Z')['holiday']['cn'], '春节');

echo "\n=== 5. Année non couverte (2027 : non annoncée) ===\n";
check('12/01/2027 02:00 → pointe (mardi ouvré)', peak('2027-01-12T02:00:00Z'), true);
check('avertissement présent pour 2027',       count(ds_payload(new DateTimeImmutable('2027-01-12T02:00:00Z'))['warnings']) > 0, true);
check('aucun avertissement en 2026',           ds_payload(new DateTimeImmutable('2026-09-29T02:00:00Z'))['warnings'], []);

echo "\n=== 6. Prochaines bascules ===\n";
$p = ds_payload(new DateTimeImmutable('2026-09-28T23:07:00Z', new DateTimeZone('UTC')));
check('bascule depuis lundi 23:07 → 29/09 01:00 UTC', $p['next_change']['at_utc'], '2026-09-29T01:00:00Z');
check('bascule devient = pointe',              $p['next_change']['becomes'], 'peak');
check('délai = 1 h 53',                        $p['next_change']['in_human'], '1 h 53');

$p = ds_payload(new DateTimeImmutable('2026-09-29T02:00:00Z', new DateTimeZone('UTC')));
check('bascule depuis mardi 02:00 → 04:00 UTC', $p['next_change']['at_utc'], '2026-09-29T04:00:00Z');
check('bascule devient = creuse',              $p['next_change']['becomes'], 'off_peak');
check('fenêtre courante 01:00-04:00',          $p['in_peak_window']['range_utc'], '01:00-04:00');
check('fin de fenêtre dans 120 min',           $p['in_peak_window']['ends_in_seconds'], 7200);
check('pas de fenêtre courante hors pointe',
      ds_payload(new DateTimeImmutable('2026-09-29T12:00:00Z', new DateTimeZone('UTC')))['in_peak_window'], null);

// Vendredi 10:00 UTC → lundi 01:00 UTC : le trou le plus long (63 h)
$p = ds_payload(new DateTimeImmutable('2026-09-18T10:30:00Z', new DateTimeZone('UTC')));
check('prochaine pointe : lundi 21/09 01:00 UTC', $p['next_peak_start']['at_utc'], '2026-09-21T01:00:00Z');
check('soit ~62,5 h d\'attente',               $p['next_peak_start']['in_human'], '2 j 14 h');

$p = ds_payload(new DateTimeImmutable('2026-09-29T09:00:00Z', new DateTimeZone('UTC')));
check('retour creuse depuis mardi 09:00 → 10:00', $p['next_off_peak_start']['at_utc'], '2026-09-29T10:00:00Z');

echo "\n=== 7. Invariants balayés sur 3 semaines (30 240 minutes) ===\n";
$bad = [];
$t   = new DateTimeImmutable('2026-09-14T00:00:00Z', new DateTimeZone('UTC'));
$countPeak = 0;
for ($i = 0; $i < 21 * 1440; $i++) {
    $t  = $t->modify('+1 minute');
    $s  = ds_state($t);
    $hm = ((int) $t->format('G')) * 60 + (int) $t->format('i');
    $inW = false;
    foreach (ds_windows_minutes() as [$a, $b]) {
        if ($hm >= $a && $hm < $b) { $inW = true; }
    }
    $isd = (int) $t->format('N');
    $isH = isset(ds_holiday_index()[$t->setTimezone(new DateTimeZone('Asia/Shanghai'))->format('Y-m-d')]);
    if ($s['peak']) {
        $countPeak++;
        // pointe ⇒ dans une plage ET jour ouvré ET pas férié
        if (!$inW || $isd >= 6 || $isH) {
            $bad[] = $t->format('c') . ' pointe incohérente';
        }
    } else {
        // creuse ⇒ plage absente OU week-end OU férié
        if ($inW && $isd < 6 && !$isH) {
            $bad[] = $t->format('c') . ' creuse incohérente';
        }
    }
}
check('aucune incohérence en 30 240 minutes', $bad, []);
check('total de minutes de pointe = 3 semaines ouvrées × 7 h (2030 attendu ≈ 420×n)',
      $countPeak > 0 && $countPeak % 420 === 0, true);

// Une journée ouvrée « normale » : 7 h de pointe, 17 h creuses
$peakMin = 0;
$t = new DateTimeImmutable('2026-09-29T00:00:00Z', new DateTimeZone('UTC'));
for ($i = 0; $i < 1440; $i++) {
    if (ds_state($t->modify("+$i minute"))['peak']) { $peakMin++; }
}
check('mardi 29/09 : 420 min de pointe (7 h)', $peakMin, 420);
check('mardi 29/09 : 1020 min creuses (17 h)', 1440 - $peakMin, 1020);

// Un férié entier : 0 minutes de pointe
$peakMin = 0;
$t = new DateTimeImmutable('2026-10-01T00:00:00Z', new DateTimeZone('UTC'));
for ($i = 0; $i < 1440; $i++) {
    if (ds_state($t->modify("+$i minute"))['peak']) { $peakMin++; }
}
check('01/10/2026 (férié) : 0 min de pointe', $peakMin, 0);

// Un samedi entier : 0 minute de pointe
$peakMin = 0;
$t = new DateTimeImmutable('2026-09-26T00:00:00Z', new DateTimeZone('UTC'));
for ($i = 0; $i < 1440; $i++) {
    if (ds_state($t->modify("+$i minute"))['peak']) { $peakMin++; }
}
check('samedi 26/09/2026 : 0 min de pointe', $peakMin, 0);

echo "\n=== 8. Table des fériés ===\n";
check('2025 : 6 périodes', count(DS_HOLIDAYS[2025]['periods']), 6);
check('2026 : 7 périodes', count(DS_HOLIDAYS[2026]['periods']), 7);
check('2026 : 9 jours de Nouvel An chinois (15→23/02)',
      (strtotime('2026-02-23') - strtotime('2026-02-15')) / 86400 + 1, 9);
check('2026-02-14 (rattrapage) n\'est PAS férié', isset(ds_holiday_index()['2026-02-14']), false);
check('2026-02-14 est un samedi de rattrapage', isset(ds_makeup_index()['2026-02-14']), true);
check('index fériés : nombre de jours 2026',
      count(array_filter(array_keys(ds_holiday_index()), static fn ($d) => str_starts_with($d, '2026'))),
      3 + 9 + 3 + 5 + 3 + 3 + 7);
check('périodes à plat : 13 au total', count(ds_all_periods()), 13);

echo "\n=== 9. Vue calendrier (2026-10) ===\n";
$m = ds_month('2026-10');
check('31 jours', count($m), 31);
check('01/10 = férié', $m[0]['type'], 'holiday');
check('07/10 = férié', $m[6]['type'], 'holiday');
check('08/10 = ouvré, 2 plages', $m[7]['type'] . '/' . count($m[7]['peak_windows_utc']), 'workday/2');
check('10/10 = samedi de rattrapage, reste creux',
      $m[9]['type'] === 'weekend' && $m[9]['makeup'] === true, true);
check('31/10 = samedi', $m[30]['type'], 'weekend');
check('mois invalide → tableau vide', ds_month('nawak'), []);

echo "\n=== 10. Analyse de ?at= ===\n";
check('date seule → 00:00 UTC',      ds_parse_at('2026-10-01')->format('Y-m-d\TH:i:s\Z'), '2026-10-01T00:00:00Z');
check('date + heure (sans fuseau) → UTC', ds_parse_at('2026-10-01 02:30')->format('Y-m-d\TH:i:s\Z'), '2026-10-01T02:30:00Z');
check('ISO Z',                        ds_parse_at('2026-10-01T02:30:00Z')->format('Y-m-d\TH:i:s\Z'), '2026-10-01T02:30:00Z');
check('ISO +02:00 → ramené en UTC',   ds_parse_at('2026-10-01T04:30:00+02:00')->format('Y-m-d\TH:i:s\Z'), '2026-10-01T02:30:00Z');
check('chaîne invalide → null',       ds_parse_at('nawak'), null);
check('vide → null',                  ds_parse_at('  '), null);
check('?at= férié → creuse à 03:00 UTC', (bool) ds_payload(ds_parse_at('2026-10-01T03:00:00Z'))['off_peak'], true);

echo "\n=== 11. Contrat de la charge utile ===\n";
$p = ds_payload(new DateTimeImmutable('2026-09-29T01:30:00Z', new DateTimeZone('UTC')));
foreach (['ok','schema','state','off_peak','label','label_en','discount_pct','multiplier','reason',
          'reason_label','holiday','next_holiday','now','utc_time','in_peak_window','next_change',
          'next_peak_start','next_off_peak_start','peak_windows_utc','holidays','warnings'] as $k) {
    check("clé « $k » présente", array_key_exists($k, $p), true);
}
check('state = peak',            $p['state'], 'peak');
check('off_peak = false',        $p['off_peak'], false);
check('discount_pct = 0',        $p['discount_pct'], 0);
check('multiplier = 1.0',        $p['multiplier'], 1.0);
check('now.utc bien formaté',    $p['now']['utc'], '2026-09-29T01:30:00Z');
check('heure de Pékin = 09:30',  $p['now']['beijing_time'], '09:30');
check('prochain férié = Fête nationale', $p['next_holiday']['fr'], 'Fête nationale');
check('JSON sérialisable',       is_string(json_encode($p)), true);

$p2 = ds_payload(new DateTimeImmutable('2026-09-29T12:00:00Z', new DateTimeZone('UTC')));
check('creuse hors pointe : discount 50 %', $p2['discount_pct'], 50);
check('creuse hors pointe : next_peak_start 30/09 01:00',
      $p2['next_peak_start']['at_utc'], '2026-09-30T01:00:00Z');

echo "\n=== 12. Divers ===\n";
check('ds_human(45)',      ds_human(45), '45 s');
check('ds_human(600)',     ds_human(600), '10 min');
check('ds_human(7200)',    ds_human(7200), '2 h 00');
check('ds_human(225000)',  ds_human(225000), '2 j 14 h');
check('jours FR',          ds_day_name(3), 'mercredi');
check('fenêtres en minutes', ds_windows_minutes(), [[60, 240], [360, 600]]);

printf("\n=== 13. ?action=month : bornes ===\n");
check('2026-10 → 31 jours',          count(ds_month('2026-10')), 31);
check('2026-10 → 1er jour',          ds_month('2026-10')[0]['date'], '2026-10-01');
check('2026-02 → 28 jours (non bissextile)', count(ds_month('2026-02')), 28);
check('2028-02 → 29 jours (bissextile)',     count(ds_month('2028-02')), 29);
check('mois 13 refusé',              ds_month('2026-13'), []);
check('mois 00 refusé',              ds_month('2026-00'), []);
check('mois 99 refusé',              ds_month('9999-99'), []);
check('année 1969 refusée',          ds_month('1969-01'), []);
check('format libre refusé',         ds_month('abc'), []);
check('format court refusé',         ds_month('2026-1'), []);
$mk = ds_month('2026-10');
$bad = array_filter($mk, static fn (array $d): bool => $d['weekday'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['date']));
check('aucun jour invalide sur 2026-10', count($bad), 0);
check('2026-10-31 = samedi',         $mk[30]['weekday'], 'samedi');
check('2026-10-31 = week-end',       $mk[30]['type'], 'weekend');
check('2026-10-01 = jeudi, férié chinois (Fête nationale)', $mk[0]['type'], 'holiday');
check('2026-10-09 = vendredi ouvré',  $mk[8]['type'], 'workday');

printf("\n──────────────────────────────────────────\n%d vérifications, %d échec(s)\n\n", $GLOBALS['n'], $GLOBALS['fail']);
exit($GLOBALS['fail'] === 0 ? 0 : 1);
