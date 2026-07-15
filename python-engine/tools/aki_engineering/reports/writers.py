from __future__ import annotations

import html
import json
from pathlib import Path

from ..models import AuditReport


def write_json(report: AuditReport, out_dir: Path) -> Path:
    path = out_dir / 'aki_audit.json'
    path.write_text(json.dumps(report.to_dict(), ensure_ascii=False, indent=2), encoding='utf-8')
    return path


def write_markdown(report: AuditReport, out_dir: Path) -> Path:
    path = out_dir / 'aki_audit.md'
    lines = [
        '# AKI Engineering Audit', '',
        f'- Architecture Score: **{report.architecture_score}/100**',
        f'- Python-Dateien: **{report.python_files}**',
        f'- Codezeilen: **{report.total_code_lines}**',
        f'- Syntaxfehler: **{len(report.syntax_errors)}**',
        f'- Duplikatgruppen: **{len(report.duplicate_groups)}**',
        f'- Mögliche Orphan-Module: **{len(report.orphan_modules)}**', '',
        '## Findings', '',
    ]
    for f in report.findings:
        lines.append(f"- **{f['severity'].upper()}** `{f['type']}` — `{f['path']}`: {f['message']}")
    lines += ['', '## Größte Dateien', '']
    for m in sorted(report.file_metrics, key=lambda x: x.lines, reverse=True)[:20]:
        lines.append(f'- `{m.path}` — {m.lines} Zeilen, Komplexität {m.complexity}')
    path.write_text('\n'.join(lines), encoding='utf-8')
    return path


def write_html(report: AuditReport, out_dir: Path) -> Path:
    path = out_dir / 'aki_audit.html'
    rows = ''.join(
        f"<tr><td>{html.escape(f['severity'])}</td><td>{html.escape(f['type'])}</td><td>{html.escape(f['path'])}</td><td>{html.escape(f['message'])}</td></tr>"
        for f in report.findings
    )
    body = f'''<!doctype html><html lang="de"><head><meta charset="utf-8"><title>AKI Audit</title>
<style>body{{font-family:Arial,sans-serif;margin:32px;background:#0e0820;color:#eee}}.card{{background:#18102d;padding:20px;border-radius:14px;margin-bottom:18px}}table{{width:100%;border-collapse:collapse}}td,th{{padding:8px;border-bottom:1px solid #3b2d59;text-align:left}}h1,h2{{color:#c4a7ff}}</style></head><body>
<h1>AKI Engineering Audit</h1><div class="card"><h2>Score: {report.architecture_score}/100</h2><p>{report.python_files} Python-Dateien · {report.total_code_lines} Codezeilen · {len(report.findings)} Findings</p></div>
<div class="card"><h2>Findings</h2><table><thead><tr><th>Severity</th><th>Typ</th><th>Pfad</th><th>Hinweis</th></tr></thead><tbody>{rows}</tbody></table></div>
</body></html>'''
    path.write_text(body, encoding='utf-8')
    return path


def write_dot(report: AuditReport, out_dir: Path) -> Path:
    path = out_dir / 'dependency_graph.dot'
    lines = ['digraph AKI {', '  rankdir=LR;', '  node [shape=box, fontsize=9];']
    for source, targets in report.import_graph.items():
        for target in targets:
            lines.append(f'  "{source}" -> "{target}";')
    lines.append('}')
    path.write_text('\n'.join(lines), encoding='utf-8')
    return path
