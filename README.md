# DSHC — DeepSeek Heure Creuse

Petit outil web qui dit si l'API DeepSeek est **en heure creuse (off-peak, −50 %)**
ou en heure pleine, et qui expose ça en **JSON** pour Home Assistant, un script ou
une amie.

- Page lisible : `https://pubtool.3dprintland.fr/dshc/`
- API : `https://pubtool.3dprintland.fr/dshc/api.php`

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
| `index.php` | la page (CSS + JS en ligne, clair/sombre auto, **balises og:**) | oui |
| `htaccess` | durcissement + HTTPS + noindex → renommer `.htaccess` | oui |
| `robots.txt` | aperçus de partage autorisés, moteurs exclus (copie de cohérence) | oui |
| `og.png` | carte de partage 1200×630 (`og:image`) | oui |
| `icon.png` | icône 180×180 écran d'accueil iOS/Android (même « % » que l'onglet) | oui |
| `favicon.ico` | icône d'onglet universelle (16+32+48) | oui |
| `favicon.svg` | icône d'onglet vectorielle (navigateurs modernes) | oui |
| `assets/` | templates HTML des images + `render_og.py` (générateur) | **non** |
| `tests/run.php`, `tests/run_makeup.php` | 128 vérifications de la logique | **non** |
| `README.md` | ce fichier | non |

## Déploiement (cPanel o2switch)

1. **cPanel → Domains → Create A New Domain** : domaine `pubtool.3dprintland.fr`,
   document root `public_html/pubtool` (décocher « share document root »).
   o2switch crée aussi l'enregistrement DNS A. Le certificat SSL est émis par
   AutoSSL — si le sous-domaine n'est pas couvert tout de suite : cPanel →
   **SSL/TLS Status** → *Run AutoSSL*.
2. **File Manager** : créer `public_html/pubtool/dshc`, y déposer
   `dshc.php`, `api.php`, `index.php`, `robots.txt`, `og.png`, `icon.png`,
   `favicon.ico`, `favicon.svg`, puis renommer `htaccess` en `.htaccess` (avec
   le point).
   ⚠ Le `.htaccess` du dossier déclare `DirectoryIndex index.php` : c'est ce qui
   fait répondre `/dshc/` (sans nom de fichier). Sans lui, le `.htaccess` parent
   de `pubtool` ne cherche que `index.html` et `/dshc/` renvoie un 403.
3. **Racine du sous-domaine** (`public_html/pubtool/`) : y déposer `robots.txt`,
   `sitemap.xml` et `favicon.ico` (dépôt `o2switch-docroots/pubtool/`) — c'est
   **ce** `robots.txt` que lisent les robots, celui de `/dshc/` est ignoré par
   le protocole ; le `favicon.ico` racine couvre la demande automatique de
   `/favicon.ico`.
4. Tester : `https://pubtool.3dprintland.fr/dshc/` et
   `https://pubtool.3dprintland.fr/dshc/api.php?format=text` → `offpeak` ou `peak`.
   Puis, pour la carte de partage, coller l'URL dans
   <https://developers.facebook.com/tools/debug/> → *Scrape Again* (indispensable
   la première fois : Facebook met la carte en cache ~7 jours).

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
  - resource: https://pubtool.3dprintland.fr/dshc/api.php
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

## Partage et indexation — carte Facebook / WhatsApp, Google / Bing

L'outil est **public** : il est fait pour être partagé *et* trouvé.

1. **`robots.txt` à la racine du sous-domaine** (dépôt `o2switch-docroots/pubtool/`) :
   `Allow: /` — les robots d'aperçu (facebookexternalhit, Twitterbot, WhatsApp,
   TelegramBot, Slackbot, Discordbot, LinkedInBot, Applebot) n'ont plus besoin
   d'exception puisqu'ils ne sont plus bloqués. Deux exclusions seulement :
   `/dshc/api.php` (l'API JSON n'a rien à faire dans un index) et
   `/index.html` (la page d'accueil du sous-domaine, un clin d'œil sans
   contenu, reste hors index).
   **Le sous-domaine racine reste fermé** : c'est un sous-domaine d'outils, rien
   d'autre n'a à être exposé. Son `.htaccess` parent garde donc son
   `X-Robots-Tag: noindex, nofollow`, et le `.htaccess` de `/dshc/` le neutralise
   pour lui seul avec un `Header unset` (cf. le commentaire dans `htaccess`).
   Sans ce `unset`, Google répondrait « Explorée, non indexée » et la Search
   Console annoncerait l'outil bloqué.
2. **`sitemap.xml`** (racine du sous-domaine) : une entrée par outil public.
   Déclaré dans le `robots.txt`, à soumettre une fois dans la Search Console.
3. **Balises `og:` de `index.php`** + `og.png` (1200×630) :
   `og:title`, `og:description`, `og:image`, `og:url`, `og:locale`, plus le
   `twitter:card = summary_large_image`. `og:url` et `og:image` sont écrites à
   partir de `DS_PUBLIC_BASE` (constante, jamais du `Host` de la requête) : un
   en-tête `Host` falsifié ne peut pas détourner la carte.

**Vérifier après déploiement** :

```bash
curl -sI https://pubtool.3dprintland.fr/dshc/ | grep -i x-robots   # doit ne RIEN afficher
curl -sS https://pubtool.3dprintland.fr/robots.txt                # Allow: / + Sitemap
curl -sS -o /dev/null -w '%{http_code}\n' https://pubtool.3dprintland.fr/sitemap.xml
```

Puis Search Console → *Inspection de l'URL* → `https://pubtool.3dprintland.fr/dshc/`
→ **Tester l'URL en direct** (doit dire « URL disponible pour Google ») →
**Demander l'indexation**. La ligne `X-Robots-Tag` est le seul piège : tant
qu'elle est là, Google crawle et refuse d'indexer.

**Vérifier / forcer une carte de partage** :
<https://developers.facebook.com/tools/debug/> (« Scrape Again »). Facebook
garde la carte en cache ~7 jours ; une modification de `og:title` ou d'`og.png`
n'apparaît donc pas immédiatement.

### Icône d'onglet (favicon)

Trois fichiers, trois rôles — un navigateur choisit le premier qu'il comprend :

| Fichier | Pour qui |
| --- | --- |
| `favicon.svg` | Chrome/Edge 80+, Firefox 41+, Safari 26+ : net à toute taille |
| `favicon.ico` | secours universel, contient 16, 32 et 48 px — c'est lui que sert Safari avant la 26 |
| `icon.png` (180) | `apple-touch-icon` : icône sur l'écran d'accueil iOS/Android |

Le 16 px a **son propre dessin**, sur la grille pixel (`assets/favicon-tile-16.html`) :
à cette taille, le tracé vectoriel du « % » se fait manger par l'anti-aliasing —
barre et pastilles fusionnent (vérifié sur planche de contrôle en comparant les
deux versions à 16 px réel).

⚠ **Copier `favicon.ico` aussi à la racine du sous-domaine**
(`public_html/pubtool/favicon.ico`) : un navigateur demande `/favicon.ico` sans
lire la page, et un 404 y laisse l'onglet sans icône. En cas de doute sur ce que
le navigateur a en mémoire, forcer le rechargement de l'icône (⌥⌘R sur Safari /
Chrome) : le cache de favicon est le plus tenace de tous, il survit souvent à un
vidage de cache ordinaire.

**Régénérer les images** (après une retouche de `assets/*.html`) :

```bash
/opt/homebrew/bin/python3 assets/render_og.py    # → og.png, icon.png, favicon.ico, favicon.svg
```

Le générateur assemble le `.ico` lui-même (PNG-dans-ICO) : ni ImageMagick ni
Pillow requis. Il pointe explicitement sur le binaire Chromium de Playwright
présent dans `~/Library/Caches/ms-playwright/` (Playwright épingle une révision,
qui diverge après une mise à jour du paquet). Si Playwright manque :
`/opt/homebrew/bin/python3 -m pip install playwright`.

## Entretien — 1 fois par an

### Veille automatique (cron) — rien à retenir

Un watchdog tourne **tous les jours à 9 h** (job Hermes `no_agent`, aucun LLM) :
`~/.hermes/profiles/linus/scripts/deepseek_pricing_watch.py`. Il est **muet**
quand tout va bien et n'écrit que s'il y a une action à faire, avec le fichier et
la commande exacts. Il surveille :

| Contrôle | Déclencheur |
| --- | --- |
| Barème publié par DeepSeek | la note « Off-peak rates… » a changé (fenêtres, jours, fériés) |
| Cohérence de l'outil | `DS_WINDOWS` de `dshc.php` ne correspond plus au barème publié |
| Table des fériés | à partir de novembre, l'année N+1 manque dans `DS_HOLIDAYS` |
| Disponibilité | `api.php` ne renvoie plus `ok:true`, ou `og.png` / `robots.txt` en 404 |

Contrôle manuel :

```bash
python3 ~/.hermes/profiles/linus/scripts/deepseek_pricing_watch.py --verbose   # affiche les faits, n'alerte pas
python3 ~/.hermes/profiles/linus/scripts/deepseek_pricing_watch.py --test-drift "note simulée"
```

État (anti-répétition, cooldown 24 h sur les pannes) :
`~/.hermes/profiles/linus/scripts/state/dshc_watch.json`.

### La table des fériés, à la main

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
php tests/run.php                                  # 128 vérifications
php -l dshc.php api.php index.php                  # syntaxe
# variante DS_MAKEUP_PEAK = true :
mkdir /tmp/t && cp dshc.php /tmp/t/ && sed -i '' 's/DS_MAKEUP_PEAK = false/DS_MAKEUP_PEAK = true/' /tmp/t/dshc.php
php tests/run_makeup.php /tmp/t/dshc.php
```

Couverture : bornes exactes des fenêtres (01:00, 03:59, 04:00, 06:00, 09:59, 10:00),
week-ends, les 13 périodes de fériés 2025-2026, invariant balayé sur 30 240 minutes
consécutives, prochaines bascules (dont le trou de 63 h du vendredi au lundi),
contrat de la charge utile, analyse de `?at=`, et bornes de `?action=month`
(un mois hors 01-12 renvoyait un HTTP 500 avant le correctif du 02/10/2026 —
`ds_month()` borne désormais le mois et l'année avant tout calcul de date).
