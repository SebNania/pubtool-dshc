<?php
/**
 * DSHC — DeepSeek Heure Creuse (off-peak)
 * ----------------------------------------------------------------------------
 * Bibliothèque de calcul : fenêtres de pointe UTC + jours fériés chinois.
 * Aucune dépendance : pas de base de données, pas de bibliothèque externe.
 *
 * Barème DeepSeek (doc officielle) :
 *   « Off-peak rates are half of the peak rates. Peak hours are 01:00 - 04:00
 *     and 06:00 - 10:00 UTC, Monday through Friday, excluding Chinese public
 *     holidays. All other hours are off-peak, including weekends and Chinese
 *     public holidays in full. »
 *
 * >>> SEUL FICHIER À METTRE À JOUR, ~1 FOIS PAR AN : la table DS_HOLIDAYS
 *     (le Conseil d'État chinois publie l'arrangement de l'année N+1 en
 *     général début novembre). Tant qu'une année manque, l'outil continue de
 *     fonctionner (week-ends + fenêtres de pointe) et le signale.
 */
declare(strict_types=1);

/* ═══════════════════════════ RÉGLAGES ═══════════════════════════════════════ */

/** Titre affiché sur la page. */
const DS_TITLE = 'DeepSeek — Heure creuse';

/** URL publique canonique de l'outil (balises canonical + og:).
 *  Écrite en dur volontairement : un en-tête « Host » falsifié ne doit pas
 *  pouvoir faire pointer la carte de partage vers un autre domaine. */
const DS_PUBLIC_BASE = 'https://pubtool.3dprintland.fr/dshc';

/** Titre + description de la carte de partage (balises og:). Statiques à
 *  dessein : Facebook garde la carte en cache ~7 jours, une description
 *  « état en direct » y serait périmée une fois sur deux. */
const DS_OG_TITLE = 'DeepSeek : heure creuse ou heure pleine ?';
const DS_OG_DESC  = "Les mêmes tokens coûtent deux fois moins cher hors des plages de pointe "
                  . "(01:00-04:00 et 06:00-10:00 UTC, du lundi au vendredi) — week-ends et fériés "
                  . "chinois compris dans l'heure creuse. État en direct et prochaine bascule.";

/** Clé d'API facultative. '' = API publique (aucune authentification).
 *  Sinon : ?key=... ou en-tête X-API-Key. */
const DS_API_KEY = '';

/** Fuseau utilisé pour l'affichage « heure locale ». */
const DS_TZ_LOCAL = 'Europe/Paris';

/** Fuseau des jours fériés chinois (ne pas changer). */
const DS_TZ_CN = 'Asia/Shanghai';

/** Plages de pointe, en UTC, du lundi au vendredi. Format HH:MM-HH:MM. */
const DS_WINDOWS = ['01:00-04:00', '06:00-10:00'];

/** false = un samedi/dimanche reste « heure creuse » même s'il est travaillé
 *  en Chine (调休). C'est la lecture littérale du barème DeepSeek
 *  (« including weekends »). Passer à true si DeepSeek suit le calendrier
 *  chinois au jour près. */
const DS_MAKEUP_PEAK = false;

/** Fenêtre d'analyse maximale pour trouver la prochaine bascule (jours). */
const DS_HORIZON_DAYS = 9;

/* ═══════════════════ JOURS FÉRIÉS CHINOIS (jours non travaillés) ═══════════
 * Source : 国务院办公厅 (Bureau du Conseil d'État), 国办发明电
 * Uniquement les périodes de 放假 (jours chômés). Les samedis/dimanches de
 * rattrapage (调休上班) sont listés dans 'makeup' et n'ont d'effet que si
 * DS_MAKEUP_PEAK = true (sinon : week-end → heure creuse, comme avant).
 * `from`/`to` inclusifs, dates en heure de Pékin.
 */
const DS_HOLIDAYS = [
    2025 => [
        'notice' => '国办发明电〔2024〕12号 — 2024-11-12',
        'periods' => [
            ['cn' => '元旦',            'fr' => 'Nouvel An',                    'from' => '2025-01-01', 'to' => '2025-01-01'],
            ['cn' => '春节',            'fr' => 'Nouvel An chinois',            'from' => '2025-01-28', 'to' => '2025-02-04'],
            ['cn' => '清明节',          'fr' => 'Qingming (fête des morts)',    'from' => '2025-04-04', 'to' => '2025-04-06'],
            ['cn' => '劳动节',          'fr' => 'Fête du travail',              'from' => '2025-05-01', 'to' => '2025-05-05'],
            ['cn' => '端午节',          'fr' => 'Duanwu (bateaux-dragons)',     'from' => '2025-05-31', 'to' => '2025-06-02'],
            ['cn' => '国庆节、中秋节',  'fr' => 'Fête nationale + Mi-automne',  'from' => '2025-10-01', 'to' => '2025-10-08'],
        ],
        'makeup' => ['2025-01-26', '2025-02-08', '2025-04-27', '2025-09-28', '2025-10-11'],
    ],
    2026 => [
        'notice' => '国办发明电〔2025〕7号 — 2025-11-04',
        'periods' => [
            ['cn' => '元旦',    'fr' => 'Nouvel An',                   'from' => '2026-01-01', 'to' => '2026-01-03'],
            ['cn' => '春节',    'fr' => 'Nouvel An chinois',           'from' => '2026-02-15', 'to' => '2026-02-23'],
            ['cn' => '清明节',  'fr' => 'Qingming (fête des morts)',   'from' => '2026-04-04', 'to' => '2026-04-06'],
            ['cn' => '劳动节',  'fr' => 'Fête du travail',             'from' => '2026-05-01', 'to' => '2026-05-05'],
            ['cn' => '端午节',  'fr' => 'Duanwu (bateaux-dragons)',    'from' => '2026-06-19', 'to' => '2026-06-21'],
            ['cn' => '中秋节',  'fr' => 'Fête de la mi-automne',       'from' => '2026-09-25', 'to' => '2026-09-27'],
            ['cn' => '国庆节',  'fr' => 'Fête nationale',              'from' => '2026-10-01', 'to' => '2026-10-07'],
        ],
        'makeup' => ['2026-01-04', '2026-02-14', '2026-02-28', '2026-05-09', '2026-09-20', '2026-10-10'],
    ],
];

/* ═══════════════════════════ OUTILS INTERNES ═══════════════════════════════ */

/** Plages de pointe converties en minutes depuis minuit UTC : [[60,240],[360,600]]. */
function ds_windows_minutes(): array
{
    static $w = null;
    if ($w !== null) {
        return $w;
    }
    $w = [];
    foreach (DS_WINDOWS as $range) {
        [$a, $b] = explode('-', $range);
        $w[] = [ds_hm_to_min($a), ds_hm_to_min($b)];
    }
    return $w;
}

/** 'HH:MM' → minutes depuis minuit. */
function ds_hm_to_min(string $hm): int
{
    $p = explode(':', $hm);
    return ((int) $p[0]) * 60 + ((int) ($p[1] ?? 0));
}

/** Index plat date(heure de Pékin) → infos férié, mémoïsé. */
function ds_holiday_index(): array
{
    static $idx = null;
    if ($idx !== null) {
        return $idx;
    }
    $idx = [];
    foreach (DS_HOLIDAYS as $year => $data) {
        foreach ($data['periods'] as $p) {
            $d = $p['from'];
            while ($d <= $p['to']) {
                $idx[$d] = [
                    'cn' => $p['cn'], 'fr' => $p['fr'],
                    'from' => $p['from'], 'to' => $p['to'],
                ];
                $d = date('Y-m-d', strtotime($d . ' +1 day'));
            }
        }
    }
    ksort($idx);
    return $idx;
}

/** Sam/dim de rattrapage, mémoïsé : ['2026-10-10' => 2026, ...]. */
function ds_makeup_index(): array
{
    static $idx = null;
    if ($idx !== null) {
        return $idx;
    }
    $idx = [];
    foreach (DS_HOLIDAYS as $year => $data) {
        foreach ($data['makeup'] ?? [] as $d) {
            $idx[$d] = $year;
        }
    }
    return $idx;
}

/** Années couvertes par la table des fériés. */
function ds_years(): array
{
    return array_keys(DS_HOLIDAYS);
}

/** Toutes les périodes de congés, à plat, avec leur année. */
function ds_all_periods(): array
{
    $out = [];
    foreach (DS_HOLIDAYS as $year => $data) {
        foreach ($data['periods'] as $p) {
            $out[] = $p + ['year' => $year];
        }
    }
    return $out;
}

/** Maintenant, en UTC. */
function ds_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('UTC'));
}

/**
 * Interprète une date passée en ?at=…
 *   "2026-10-01T02:30:00Z" / "+02:00"  → respecte le décalage
 *   "2026-10-01 02:30"                 → considéré comme UTC
 *   "2026-10-01"                       → 00:00 UTC
 * Retourne null si illisible.
 */
function ds_parse_at(?string $at): ?DateTimeImmutable
{
    if ($at === null || trim($at) === '') {
        return null;
    }
    $at = trim($at);
    $utc = new DateTimeZone('UTC');
    try {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $at)) {
            return new DateTimeImmutable($at . ' 00:00:00', $utc);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/', $at)) {
            return new DateTimeImmutable(str_replace('T', ' ', $at), $utc);
        }
        // ISO 8601 avec décalage explicite → ramené en UTC (le reste du code
        // suppose toujours un DateTimeImmutable en UTC)
        return (new DateTimeImmutable($at))->setTimezone($utc);
    } catch (Throwable $e) {
        return null;
    }
}

/** "1 h 53 min" / "38 min" / "12 s". */
function ds_human(int $sec): string
{
    $sec = max(0, $sec);
    if ($sec < 60) {
        return $sec . ' s';
    }
    $m = intdiv($sec, 60);
    $h = intdiv($m, 60);
    $d = intdiv($h, 24);
    if ($d > 0) {
        return $d . ' j ' . ($h % 24) . ' h';
    }
    if ($h > 0) {
        return $h . ' h ' . str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT);
    }
    return $m . ' min';
}

/** Noms de jours en français (1 = lundi). */
function ds_day_name(int $iso): string
{
    return [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi',
            5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche'][$iso] ?? '?';
}

/* ═══════════════════════════ CŒUR DU CALCUL ═══════════════════════════════ */

/**
 * État tarifaire à un instant donné.
 *
 * Note de rigueur : la pointe se juge sur la DATE UTC et l'HEURE UTC. Les deux
 * plages (01:00-04:00 et 06:00-10:00 UTC) tombent entièrement dans une même
 * journée de Pékin (09:00-18:00 CST), donc date UTC et date chinoise coïncident
 * toujours à l'intérieur des plages → aucune ambiguïté de jour. Le férié est
 * testé sur la date de Pékin (un férié chinois est une journée de Pékin).
 *
 * @return array{peak:bool,reason:string,reason_label:string,holiday:?array,makeup:bool}
 */
function ds_state(DateTimeImmutable $t): array
{
    $utcMin  = ((int) $t->format('G')) * 60 + ((int) $t->format('i'));
    $isoDay  = (int) $t->format('N');
    $cnDate  = $t->setTimezone(new DateTimeZone(DS_TZ_CN))->format('Y-m-d');
    $utcDate = $t->format('Y-m-d');

    $holiday = ds_holiday_index()[$cnDate] ?? null;
    $makeup  = isset(ds_makeup_index()[$utcDate]);

    $inWindow = false;
    foreach (ds_windows_minutes() as [$a, $b]) {
        if ($utcMin >= $a && $utcMin < $b) {
            $inWindow = true;
            break;
        }
    }

    $isWeekend = ($isoDay >= 6);

    if ($holiday !== null) {
        return ['peak' => false, 'reason' => 'holiday', 'holiday' => $holiday,
                'makeup' => $makeup, 'reason_label' => 'Jour férié chinois — heure creuse toute la journée'];
    }
    if ($isWeekend && !(DS_MAKEUP_PEAK && $makeup)) {
        return ['peak' => false, 'reason' => 'weekend', 'holiday' => null,
                'makeup' => $makeup, 'reason_label' => 'Week-end — heure creuse toute la journée'];
    }
    if ($inWindow) {
        $r = ($isWeekend && $makeup) ? 'makeup_workday' : 'peak_window';
        return ['peak' => true, 'reason' => $r, 'holiday' => null, 'makeup' => $makeup,
                'reason_label' => $r === 'makeup_workday'
                    ? 'Samedi/dimanche travaillé (调休) — plage de pointe'
                    : 'Jour ouvré dans une plage de pointe'];
    }
    return ['peak' => false, 'reason' => 'outside_windows', 'holiday' => null,
            'makeup' => $makeup, 'reason_label' => 'Jour ouvré hors plage de pointe'];
}

/** Prochaine minute où l'état change, à partir de $from exclu. */
function ds_next_flip(DateTimeImmutable $from, mixed $target = null): ?DateTimeImmutable
{
    $cur = ds_state($from)['peak'];
    $t   = $from->setTime((int) $from->format('G'), (int) $from->format('i'), 0);
    $limit = $from->modify('+' . DS_HORIZON_DAYS . ' days');
    for ($i = 0; $i < DS_HORIZON_DAYS * 1440; $i++) {
        $t = $t->modify('+1 minute');
        if ($t > $limit) {
            break;
        }
        $p = ds_state($t)['peak'];
        if ($target === null ? ($p !== $cur) : ($p === (bool) $target)) {
            return $t;
        }
    }
    return null;
}

/** Prochain début de plage de pointe (ou null). */
function ds_next_peak_start(DateTimeImmutable $from): ?DateTimeImmutable
{
    return ds_state($from)['peak'] ? ds_next_flip($from, false) : ds_next_flip($from, true);
}

/** Prochain retour en heure creuse (ou null). */
function ds_next_off_peak_start(DateTimeImmutable $from): ?DateTimeImmutable
{
    return ds_state($from)['peak'] ? ds_next_flip($from, false) : null;
}

/** Charge utile complète (c'est le contrat d'API — voir README). */
function ds_payload(?DateTimeImmutable $now = null): array
{
    $now = $now ?? ds_now();
    $now = $now->setTimezone(new DateTimeZone('UTC'));

    $st      = ds_state($now);
    $wins    = ds_windows_minutes();
    $utcMin  = ((int) $now->format('G')) * 60 + ((int) $now->format('i'));
    $year    = (int) $now->format('Y');
    $idx     = ds_holiday_index();

    // Férié en cours / à venir (sur 400 jours, pour l'affichage)
    $holiday = $st['holiday'];
    $nextHoliday = null;
    if ($holiday === null) {
        $cnDate = $now->setTimezone(new DateTimeZone(DS_TZ_CN))->format('Y-m-d');
        foreach ($idx as $d => $h) {
            if ($d > $cnDate) {
                $nextHoliday = ['date' => $d] + $h;
                break;
            }
        }
    }

    // Plage en cours (si pointe)
    $curWindow = null;
    foreach ($wins as $k => [$a, $b]) {
        if ($utcMin >= $a && $utcMin < $b) {
            [$ws, $we] = explode('-', DS_WINDOWS[$k]);
            $curWindow = [
                'start_utc'       => $ws,
                'end_utc'         => $we,
                'range_utc'       => DS_WINDOWS[$k],
                'ends_in_seconds' => ($b - $utcMin) * 60 - (int) $now->format('s'),
            ];
            break;
        }
    }

    $mkNext = static function (?DateTimeImmutable $t) use ($now): ?array {
        if ($t === null) {
            return null;
        }
        $s = $t->getTimestamp() - $now->getTimestamp();
        return ['at_utc' => $t->format('Y-m-d\TH:i:s\Z'), 'in_seconds' => $s, 'in_human' => ds_human($s)];
    };

    $flip  = ds_next_flip($now);
    $flipP = $flip ? ds_state($flip) : null;

    $warnings = [];
    if (!isset(DS_HOLIDAYS[$year])) {
        $warnings[] = 'Table des jours fériés absente pour ' . $year
            . ' : seuls les week-ends et les plages horaires sont pris en compte. '
            . 'Le Conseil d\'État chinois publie l\'arrangement de l\'année N+1 en novembre '
            . '(à ajouter dans DS_HOLIDAYS de dshc.php).';
    }
    if ($st['peak'] && $curWindow === null) {
        $warnings[] = 'Incohérence : état pointe hors plage — vérifier DS_WINDOWS.';
    }

    $tzLocal  = new DateTimeZone(DS_TZ_LOCAL);
    $tzCn     = new DateTimeZone(DS_TZ_CN);
    $cn       = $now->setTimezone($tzCn);
    $loc      = $now->setTimezone($tzLocal);

    return [
        'ok'            => true,
        'schema'        => 1,
        'state'         => $st['peak'] ? 'peak' : 'off_peak',
        'off_peak'      => !$st['peak'],
        'label'         => $st['peak'] ? 'Heure pleine' : 'Heure creuse',
        'label_en'      => $st['peak'] ? 'Peak' : 'Off-peak',
        'discount_pct'  => $st['peak'] ? 0 : 50,
        'multiplier'    => $st['peak'] ? 1.0 : 0.5,
        'reason'        => $st['reason'],
        'reason_label'  => $st['reason_label'],
        'holiday'       => $holiday,
        'next_holiday'  => $nextHoliday,
        'now'           => [
            'utc'        => $now->format('Y-m-d\TH:i:s\Z'),
            'utc_date'   => $now->format('Y-m-d'),
            'utc_time'   => $now->format('H:i'),
            'utc_time_s' => $now->format('H:i:s'),
            'utc_weekday'=> ds_day_name((int) $now->format('N')),
            'beijing'    => $cn->format('Y-m-d H:i'),
            'beijing_time' => $cn->format('H:i'),
            'local'      => $loc->format('Y-m-d H:i'),
            'local_time' => $loc->format('H:i'),
            'local_tz'   => $loc->format('T'),
            'timestamp'  => $now->getTimestamp(),
        ],
        'utc_time'      => $now->format('H:i'),
        'in_peak_window'=> $curWindow,
        'next_change'   => $flip ? ($mkNext($flip) + ['becomes' => $flipP['peak'] ? 'peak' : 'off_peak',
                                                      'becomes_label' => $flipP['peak'] ? 'Heure pleine' : 'Heure creuse']) : null,
        'next_peak_start'    => $mkNext(ds_next_peak_start($now)),
        'next_off_peak_start'=> $mkNext(ds_next_off_peak_start($now)),
        'peak_windows_utc'   => DS_WINDOWS,
        'makeup_workdays_peak' => DS_MAKEUP_PEAK,
        'holidays'      => [
            'years'   => ds_years(),
            'source'  => array_map(static fn ($y) => DS_HOLIDAYS[$y]['notice'], ds_years()),
            'periods' => ds_all_periods(),
        ],
        'warnings'      => $warnings,
    ];
}

/** Vue calendrier d'un mois : un enregistrement par jour (heures UTC). */
function ds_month(string $ym): array
{
    if (!preg_match('/^(\d{4})-(\d{2})$/', $ym, $m)) {
        return [];
    }
    $y  = (int) $m[1];
    $mo = (int) $m[2];
    /* Bornes obligatoires : un mois hors 01-12 (ou une année absurde) produit
       une date invalide ; strtotime() renvoie alors false et date() lève un
       TypeError sous strict_types → HTTP 500 sur une API publique. */
    if ($mo < 1 || $mo > 12 || $y < 1970 || $y > 2100) {
        return [];
    }
    $days = (int) gmdate('t', gmmktime(0, 0, 0, $mo, 1, $y));
    $idx  = ds_holiday_index();
    $out  = [];
    for ($d = 1; $d <= $days; $d++) {
        $utcDate = sprintf('%04d-%02d-%02d', $y, $mo, $d);
        $mk = ds_makeup_index()[$utcDate] ?? false;
        // date de Pékin = date UTC (les deux plages UTC tombent le même jour CST)
        $holi = $idx[$utcDate] ?? null;
        $iso  = (int) gmdate('N', gmmktime(12, 0, 0, $mo, $d, $y));
        $type = $holi !== null ? 'holiday' : (($iso >= 6 && !(DS_MAKEUP_PEAK && $mk)) ? 'weekend' : 'workday');
        $peak = [];
        foreach (DS_WINDOWS as $w) {
            if ($type === 'workday') {
                $peak[] = $w;
            }
        }
        $out[] = [
            'date'          => $utcDate,
            'weekday'       => ds_day_name($iso),
            'type'          => $type,
            'holiday'       => $holi,
            'makeup'        => (bool) $mk,
            'peak_windows_utc' => $peak,
            'peak_hours'    => count($peak) * 3,
        ];
    }
    return $out;
}
