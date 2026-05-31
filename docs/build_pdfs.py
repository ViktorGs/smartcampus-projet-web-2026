"""
Convertit les .md sans PDF en PDF stylisé (ECE).
Génère :
  - RAPPORT-compromis-techniques.pdf
  - 05-Repartition-travail.pdf
  - 06-Journal-assistance-IA.pdf
"""
import subprocess
import markdown
from pathlib import Path

ROOT = Path(__file__).resolve().parent
LOGO = ROOT / "assets" / "ece-logo.jpg"
EDGE = r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe"

CSS = """
@page { size: A4; margin: 22mm 18mm 22mm 18mm; }
@page :first { margin: 0; }
html, body { margin: 0; padding: 0; }
body {
  font-family: "Calibri", "Segoe UI", Arial, sans-serif;
  font-size: 11pt; line-height: 1.45; color: #1a1a1a;
}
:root { --ece: #00807F; --ece-soft: #E6F1F1; --ece-dark: #006666; }
h1, h2, h3, h4 { color: var(--ece); font-weight: 700; }
h1 { font-size: 18pt; margin: 0 0 4mm 0; }
h2 { font-size: 14pt; margin: 6mm 0 3mm 0; page-break-after: avoid;
     border-bottom: 0.4pt solid var(--ece-soft); padding-bottom: 1mm; }
h3 { font-size: 12pt; margin: 4mm 0 2mm 0; page-break-after: avoid; }
h4 { font-size: 11pt; margin: 3mm 0 1mm 0; }
p { margin: 0 0 2.5mm 0; text-align: justify; }
ul, ol { margin: 0 0 3mm 0; padding-left: 6mm; }
li { margin-bottom: 1mm; }
strong { color: var(--ece-dark); }
blockquote {
  border-left: 2pt solid var(--ece);
  background: var(--ece-soft);
  padding: 3mm 4mm; margin: 3mm 0;
  font-style: italic; color: #3d3d3d;
}
code {
  font-family: "Consolas", monospace; font-size: 9.5pt;
  background: #f4f4f4; padding: 0.5mm 1.5mm; border-radius: 1mm;
}
table { width: 100%; border-collapse: collapse; margin: 2mm 0 4mm 0;
        font-size: 10pt; page-break-inside: avoid; }
thead th { background: var(--ece); color: #fff; text-align: left;
           padding: 1.5mm 2mm; font-weight: 600; }
tbody td { border: 0.3pt solid #c8c8c8; padding: 1.5mm 2mm; vertical-align: top; }
tbody tr:nth-child(even) td { background: #fafafa; }

.cover { height: 297mm; width: 210mm; box-sizing: border-box;
         page-break-after: always; position: relative; overflow: hidden; }
.cv-logo { position: absolute; top: 18mm; left: 18mm; }
.cv-logo img { width: 32mm; height: 32mm; display: block; }
.cv-ing { position: absolute; top: 18mm; right: 18mm;
          color: #4a4a4a; font-size: 10pt; text-align: right; }
.cv-title {
  position: absolute; top: 90mm; left: 18mm; right: 18mm;
  padding: 8mm 4mm;
  border-top: 1.2pt solid var(--ece); border-bottom: 1.2pt solid var(--ece);
  text-align: center;
}
.cv-title .doc-type {
  color: var(--ece); font-size: 22pt; font-weight: 800;
  letter-spacing: 0.5pt; margin-bottom: 3mm;
}
.cv-title .doc-subtitle {
  color: var(--ece); font-size: 14pt; font-style: italic; font-weight: 500;
}
.cv-meta {
  position: absolute; top: 150mm; left: 18mm; right: 18mm;
  font-size: 11pt;
}
.cv-meta .label { font-style: italic; color: #4a4a4a; margin-bottom: 3mm; }
.cv-meta .name { margin-bottom: 1.5mm; }
.cv-footer {
  position: absolute; bottom: 18mm; left: 18mm; right: 18mm;
  border-top: 0.4pt solid #c8c8c8; padding-top: 4mm;
  font-size: 9.5pt; color: #4a4a4a; text-align: center;
}
.body { padding: 0; }
"""

def cover(title, subtitle):
    return f"""
<div class="cover">
  <div class="cv-logo"><img src="{LOGO.as_uri()}" alt="ECE"></div>
  <div class="cv-ing">ING2<br>Projet Web dynamique 2026</div>
  <div class="cv-title">
    <div class="doc-type">{title}</div>
    <div class="doc-subtitle">{subtitle}</div>
  </div>
  <div class="cv-meta">
    <div class="label">Équipe SmartCampus</div>
    <div class="name">Viktor GOUSSOT</div>
    <div class="name">Nicolas CARMINATI</div>
    <div class="name">Louis PEZE</div>
    <div class="name">Alexis OSORIO</div>
  </div>
  <div class="cv-footer">
    SmartCampus &nbsp;·&nbsp; Sujet 2 — La gestion académique de notre époque &nbsp;·&nbsp; 31 mai 2026
  </div>
</div>
"""


def build(md_path: Path, pdf_path: Path, cover_title: str, cover_subtitle: str):
    md_text = md_path.read_text(encoding="utf-8")
    body = markdown.markdown(md_text, extensions=["tables", "fenced_code"])
    html_path = pdf_path.with_suffix(".html")
    full = f"""<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>{cover_title} — SmartCampus</title>
<style>{CSS}</style></head><body>
{cover(cover_title, cover_subtitle)}
<div class="body">{body}</div>
</body></html>"""
    html_path.write_text(full, encoding="utf-8")
    cmd = [EDGE, "--headless", "--disable-gpu", "--no-pdf-header-footer",
           f"--print-to-pdf={pdf_path}", html_path.as_uri()]
    subprocess.run(cmd, check=True, timeout=60)
    print(f"OK : {pdf_path.name}  ({pdf_path.stat().st_size // 1024} Ko)")


# 3 documents à générer
build(
    ROOT / "RAPPORT-compromis-techniques.md",
    ROOT / "RAPPORT-compromis-techniques.pdf",
    "RAPPORT DE COMPROMIS<br>TECHNIQUES",
    "Décisions de conception &amp; limites du projet",
)
build(
    ROOT / "05-repartition-travail.md",
    ROOT / "Repartition-travail.pdf",
    "RÉPARTITION<br>DU TRAVAIL",
    "Rôles, modules, bilans individuels &amp; collectif",
)
build(
    ROOT / "06-journal-assistance-IA.md",
    ROOT / "Journal-assistance-IA.pdf",
    "JOURNAL<br>D'ASSISTANCE IA",
    "Outils, tâches, analyse critique &amp; limites observées",
)
