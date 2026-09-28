# DSHC — DeepSeek Heure Creuse

Petit outil web qui dit si l'API DeepSeek est **en heure creuse (off-peak, −50 %)**
ou en heure pleine, et qui expose ça en **JSON** pour Home Assistant, un script ou
une amie.

- Page lisible : `https://pubtool.3dprintland.fr/DSHC/`
- API : `https://pubtool.3dprintland.fr/DSHC/api.php`

Aucune base de données, aucune dépendance, aucun secret, aucune donnée collectée.
Trois fichiers PHP suffisent (l'API est en lecture seule).

## Le barème, tel que le documente DeepSeek

> Off-peak rates are half of the peak rates. Peak hours are **01:00 – 04:00** and
> **06:00 – 10:00 UTC**, **Monday through Friday**, excluding Chinese public holidays.
> All other hours are off-peak, including **weekends and Chinese public holidays in full**.

Conséquences : 7 h de pointe par jour ouvré, 17 h creuses ; week-ends et fériés
chinois entièrement creux. Le plus long trou sans pointe va du **vendredi 10:00 UTC
au lundi 01:00 UTC** (63 h).

## Fichiers

| Fichier | Rôle | À déployer |
| --- | --- | --- |
| `dshc.php` | librairie : fenêtres + **table des jours fériés** + calculs | oui (masqué par `.htaccess`) |
| `api.php` | l'API JSON / texte | oui |
| `index.php` | la page (CSS + JS en ligne, clair/sombre auto) | oui |
| `htaccess` | durcissement + HTTPS + noindex → renommer `.htaccess` | oui |
| `robots.txt` | anti-indexation | oui |
| `tests/run.php`, `tests/run_makeup.php` | 113 vérifications de la logique | **non** |
| `README.md` | ce fichier | non |

## Déploiement (cPanel o2switch)

1. **cPanel → Domains → Create A New Domain** : domaine `pubtool.3dprintland.fr`,
   document root `public_html/pubtool` (décocher « share document root »).
   o2switch crée aussi l'enregistrement DNS A. Le certificat SSL est émis par
   AutoSSL — si le sous-domaine n'est pas couvert tout de suite : cPanel →
   **SSL/TLS Status** → *Run AutoSSL*.
2. **File Manager** : créer `public_html/pubtool/DSHC`, y déposer
   `dshc.php`, `api.php`, `index.php`, `robots.txt`, puis renommer `htaccess`
   en `.htaccess` (avec le point).
3. Tester : `https://pubtool.3dprintland.fr/DSHC/` et
   `https://pubtool.3dprintland.fr/DSHC/api.php?format=text` → `offpeak` ou `peak`.

Si le serveur déjà en PHP 8.3 (MultiPHP) renvoie une 500, commenter la ligne
`AddHandler` du `.htaccess`.

## API

| Requête | Réponse |
| --- | --- |
| `api.php` | JSON complet, état du moment |
| `api.php?format=text` | `offpeak` ou `peak` (texte brut, idéal pour un template) |
| `api.php?at=2026-10-01T02:30:00Z` | état à un instant donné (`2026-10-01 02:30` sans fuseau = UTC) |
| `api.php?action=month&month=2026-10` | un enregistrement par jour du mois |
| `api.php?fields=state,next_change` | réponse réduite |
| `api.php?pretty=0` | JSON compact |

Champs principaux (le reste est dans la réponse) :

```json
{
  "ok": true,
  "state": "off_peak",            // off_peak | peak
  "off_peak": true,
  "label": "Heure creuse",
  "discount_pct": 50,             // 0 en heure pleine
  "multiplier": 0.5,
  "reason": "outside_windows",    // peak_window | outside_windows | weekend | holiday | makeup_workday
  "reason_label": "Jour ouvré hors plage de pointe",
  "holiday": null,                // {"cn":"国庆节","fr":"Fête nationale","from":"…","to":"…"}
  "now": { "utc": "2026-09-28T23:11:07Z", "utc_time": "23:11",
           "beijing_time": "07:11", "local_time": "01:11", "timestamp": 1790637067 },
  "next_change":          { "at_utc": "2026-09-29T01:00:00Z", "in_seconds": 6533, "becomes": "peak" },
  "next_peak_start":      { "at_utc": "…", "in_seconds": 6533 },
  "next_off_peak_start":  null,
  "peak_windows_utc": ["01:00-04:00", "06:00-10:00"],
  "holidays": { "years": [2025, 2026], "periods": [ … ] },
  "warnings": []
}
```

La réponse est mise en cache 30 s (public, CORS ouvert) : elle peut être appelée
sans risque par plusieurs consommateurs.

## Home Assistant — remplace le calcul dans `configuration.yaml`

```yaml
rest:
  - resource: https://pubtool.3dprintland.fr/DSHC/api.php
    scan_interval: 60
    sensor:
      - name: "DeepSeek Tariff Status"       # conserve sensor.deepseek_tariff_status
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
```

L'attribut `next_change` contient `at_utc`, `in_seconds` et `becomes` : de quoi
programmer une action « avant la bascule » sans refaire le calcul en YAML.

## Entretien — 1 fois par an

`dshc.php` contient la table `DS_HOLIDAYS` : les périodes de congés chinoises
(sources officielles `国办发明电`). Le Conseil d'État publie l'arrangement de
l'année N+1 **début novembre** — il suffit d'ajouter un bloc `2027 => […]` avec
`notice`, `periods` et `makeup`, puis de redéposer `dshc.php`.

- 2025 : `国办发明电〔2024〕12号` (6 périodes)
- 2026 : `国办发明电〔2025〕7号` (7 périodes)

Tant qu'une année manque, l'outil continue de fonctionner (fenêtres + week-ends)
et renvoie un `warning` explicite pour les dates concernées.

Les `makeup` sont les samedis/dimanches travaillés en Chine (调休). Par défaut
(`DS_MAKEUP_PEAK = false`) ils restent **creux**, lecture littérale du barème
(« including weekends »). Passer la constante à `true` si DeepSeek suit le
calendrier chinois au jour près.

## Tests

```bash
php tests/run.php                                  # 113 vérifications
php -l dshc.php api.php index.php                  # syntaxe
# variante DS_MAKEUP_PEAK = true :
mkdir /tmp/t && cp dshc.php /tmp/t/ && sed -i '' 's/DS_MAKEUP_PEAK = false/DS_MAKEUP_PEAK = true/' /tmp/t/dshc.php
php tests/run_makeup.php /tmp/t/dshc.php
```

Couverture : bornes exactes des fenêtres (01:00, 03:59, 04:00, 06:00, 09:59, 10:00),
week-ends, les 13 périodes de fériés 2025-2026, invariant balayé sur 30 240 minutes
consécutives, prochaines bascules (dont le trou de 63 h du vendredi au lundi),
contrat de la charge utile, analyse de `?at=`.
