#!/usr/bin/env python3
"""Génère les images de l'outil DSHC depuis les templates HTML du dossier assets/.

    /opt/homebrew/bin/python3 assets/render_og.py

Produit (à déposer dans public_html/pubtool/dshc/) :
  - og.png       1200×630  carte de partage (og:image)
  - icon.png      180×180  icône iOS / Android (apple-touch-icon)
  - favicon.svg    64×64   icône vectorielle (navigateurs modernes)
  - favicon.ico   16+32+48 icône d'onglet universelle (« % » vert)

Le .ico est assemblé à la main (conteneur PNG-dans-ICO, format Vista+) : pas
d'ImageMagick ni de Pillow requis. Les rendus intermédiaires vont dans
assets/.build/ (non versionné).

À copier aussi à la racine du sous-domaine (public_html/pubtool/favicon.ico) :
les navigateurs demandent /favicon.ico sans lire la page, et un 404 y laisse
l'onglet sans icône.
"""
from __future__ import annotations

from pathlib import Path
import glob
import os
import struct

from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parent.parent
BUILD = ROOT / "assets" / ".build"
TILE = "assets/favicon-tile.html"
# Le 16 px a son propre dessin, sur la grille pixel : à cette taille, l'anti-
# aliasing du tracé vectoriel mange la barre et les pastilles (vérifié sur
# planche de contrôle — la version grille est la seule nette).
ICO_SIZES = ((16, "assets/favicon-tile-16.html"), (32, TILE), (48, TILE))

JOBS = [
    ("assets/og-card.html", "og.png", 1200, 630),
    # Même signe que l'onglet, pour un jeu d'icônes cohérent : sur iOS le nom
    # de l'app s'affiche déjà sous l'icône, un « DeepSeek » gravé dedans est
    # redondant (et jurait avec le reste du jeu).
    ("assets/favicon-tile.html", "icon.png", 180, 180),
]


def chromium_path() -> str | None:
    """Playwright épingle une révision de Chromium ; si celle installée diffère
    (mise à jour du paquet), on pointe explicitement sur le binaire présent."""
    pats = [
        "~/Library/Caches/ms-playwright/chromium_headless_shell-*/chrome-headless-shell-mac-*/chrome-headless-shell",
        "~/Library/Caches/ms-playwright/chromium-*/chrome-mac-*/Chromium.app/Contents/MacOS/Chromium",
    ]
    found: list[str] = []
    for pat in pats:
        found += [f for f in glob.glob(str(Path(pat).expanduser())) if os.access(f, os.X_OK)]
    return sorted(found)[-1] if found else None


def render(browser, src: Path, out: Path, w: int, h: int) -> None:
    page = browser.new_page(viewport={"width": w, "height": h}, device_scale_factor=1)
    page.goto(src.as_uri())
    page.wait_for_timeout(400)  # laisse les polices système s'installer
    page.screenshot(path=str(out), clip={"x": 0, "y": 0, "width": w, "height": h})
    page.close()


def build_ico(pngs: list[tuple[int, bytes]], out: Path) -> None:
    """Conteneur ICO embarquant des PNG (16, 32, 48 px)."""
    header = struct.pack("<HHH", 0, 1, len(pngs))          # reserved, type=icon, count
    offset = len(header) + 16 * len(pngs)
    entries, blobs = b"", b""
    for size, data in pngs:
        w = h = 0 if size >= 256 else size                  # 0 = 256 px, convention ICO
        entries += struct.pack("<BBBBHHII", w, h, 0, 0, 1, 32, len(data), offset)
        blobs += data
        offset += len(data)
    out.write_bytes(header + entries + blobs)


def main() -> int:
    BUILD.mkdir(parents=True, exist_ok=True)
    with sync_playwright() as p:
        exe = chromium_path()
        if exe:
            browser = p.chromium.launch(headless=True, executable_path=exe)
        else:
            browser = p.chromium.launch(headless=True)

        for src, out_name, w, h in JOBS:
            out = ROOT / out_name
            render(browser, ROOT / src, out, w, h)
            print(f"{out.name:11} {w}×{h}   {out.stat().st_size} octets")

        pngs = []
        for size, src in ICO_SIZES:
            tmp = BUILD / f"tile-{size}.png"
            render(browser, ROOT / src, tmp, size, size)
            pngs.append((size, tmp.read_bytes()))
        browser.close()

    ico = ROOT / "favicon.ico"
    build_ico(pngs, ico)
    print(f"{ico.name:11} {'.'.join(str(s) for s, _ in ICO_SIZES)}   {ico.stat().st_size} octets")

    svg_src = ROOT / "assets" / "favicon.svg"
    svg_dst = ROOT / "favicon.svg"
    svg_dst.write_bytes(svg_src.read_bytes())
    print(f"{svg_dst.name:11} 64×64 (vectoriel)   {svg_dst.stat().st_size} octets")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
