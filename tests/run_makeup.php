<?php
/**
 * DSHC — vérifie la variante DS_MAKEUP_PEAK = true (les samedis/dimanches de
 * rattrapage 调休 comptent comme des jours ouvrés).
 *
 * Usage : php tests/run_makeup.php /chemin/vers/copie_de_dshc.php
 * (la copie doit avoir DS_MAKEUP_PEAK = true)
 */
declare(strict_types=1);

if (!isset($argv[1]) || !is_file($argv[1])) {
    fwrite(STDERR, "usage: php tests/run_makeup.php <copie de dshc.php avec DS_MAKEUP_PEAK = true>\n");
    exit(2);
}
require $argv[1];

if (DS_MAKEUP_PEAK !== true) {
    fwrite(STDERR, "ERREUR : la copie fournie n'a pas DS_MAKEUP_PEAK = true\n");
    exit(2);
}

$fail = 0;
$n    = 0;
function check(string $label, mixed $got, mixed $want): void
{
    global $fail, $n;
    $n++;
    if ($got === $want) {
        printf("  ✓ %s\n", $label);
        return;
    }
    $fail++;
    printf("  ✗ %s (got=%s want=%s)\n", $label, var_export($got, true), var_export($want, true));
}
function s(string $iso): array
{
    return ds_state(new DateTimeImmutable($iso, new DateTimeZone('UTC')));
}

echo "\n=== DS_MAKEUP_PEAK = true ===\n";
// 2026-02-14 = samedi de rattrapage du Nouvel An chinois
check('samedi de rattrapage 14/02 02:00 → pointe', s('2026-02-14T02:00:00Z')['peak'], true);
check('motif = makeup_workday',                    s('2026-02-14T02:00:00Z')['reason'], 'makeup_workday');
check('samedi de rattrapage 14/02 12:00 → creuse', s('2026-02-14T12:00:00Z')['peak'], false);
check('samedi de rattrapage 14/02 12:00 → hors plage',
      s('2026-02-14T12:00:00Z')['reason'], 'outside_windows');
// 2026-01-04 = dimanche travaillé
check('dimanche travaillé 04/01 02:00 → pointe',   s('2026-01-04T02:00:00Z')['peak'], true);
// 2026-10-10 = samedi travaillé
check('samedi travaillé 10/10 09:30 → pointe',     s('2026-10-10T09:30:00Z')['peak'], true);
// Un samedi ordinaire reste creux
check('samedi ordinaire 19/09 02:00 → creuse',     s('2026-09-19T02:00:00Z')['peak'], false);
check('samedi ordinaire motif = weekend',          s('2026-09-19T02:00:00Z')['reason'], 'weekend');
// Le férié garde la priorité sur le rattrapage
check('férié 15/02 02:00 → creuse même en mode rattrapage',
      s('2026-02-15T02:00:00Z')['peak'], false);
check('férié motif = holiday',                     s('2026-02-15T02:00:00Z')['reason'], 'holiday');

printf("\n%d vérifications, %d échec(s)\n\n", $n, $fail);
exit($fail === 0 ? 0 : 1);
