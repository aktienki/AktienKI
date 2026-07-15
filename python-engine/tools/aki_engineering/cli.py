from __future__ import annotations

import argparse
import json
from datetime import datetime, timezone
from pathlib import Path

from .analyzers.duplicates import analyze_duplicates
from .analyzers.findings import architecture_score, build_findings
from .analyzers.imports import analyze_imports
from .analyzers.metrics import analyze_metrics
from .fixers.cleanup import clean_generated
from .models import AuditReport
from .project import parse_project
from .reports.writers import write_dot, write_html, write_json, write_markdown


def parser() -> argparse.ArgumentParser:
    p = argparse.ArgumentParser(description='AKI Engineering Suite')
    p.add_argument('--project-root', type=Path, default=Path.cwd())
    p.add_argument('--output', type=Path, default=None)
    p.add_argument('--duplicate-threshold', type=float, default=0.92)
    p.add_argument('--clean', action='store_true', help='Caches und .DS_Store löschen')
    p.add_argument('--apply', action='store_true', help='Cleanup tatsächlich anwenden')
    p.add_argument('--fail-under', type=float, default=None)
    return p


def main() -> int:
    args = parser().parse_args()
    root = args.project_root.expanduser().resolve()
    if not (root / 'app').is_dir():
        raise SystemExit(f'Kein app-Ordner unter {root}')

    if args.clean:
        items = clean_generated(root, apply=args.apply)
        print(json.dumps({'cleanup_candidates': items, 'applied': args.apply}, indent=2, ensure_ascii=False))

    modules = parse_project(root)
    syntax_errors = [
        {'path': str(m.path.relative_to(root)), 'error': m.error}
        for m in modules if m.error
    ]
    metrics, packages, total_lines, total_code = analyze_metrics(root, modules)
    graph, reverse, orphans = analyze_imports(modules)
    duplicates = analyze_duplicates(modules, threshold=args.duplicate_threshold)
    findings = build_findings(metrics, orphans, duplicates, syntax_errors)
    score = architecture_score(findings, len(modules))

    report = AuditReport(
        project_root=str(root),
        generated_at=datetime.now(timezone.utc).isoformat(),
        python_files=len(modules),
        total_lines=total_lines,
        total_code_lines=total_code,
        package_counts=packages,
        file_metrics=metrics,
        import_graph=graph,
        reverse_import_graph=reverse,
        orphan_modules=orphans,
        duplicate_groups=duplicates,
        syntax_errors=syntax_errors,
        findings=findings,
        architecture_score=score,
    )

    out = (args.output or (root / 'storage' / 'aki_audit')).resolve()
    out.mkdir(parents=True, exist_ok=True)
    paths = [write_json(report, out), write_markdown(report, out), write_html(report, out), write_dot(report, out)]

    print(f'AKI Audit: {score}/100')
    for path in paths:
        print(path)

    if args.fail_under is not None and score < args.fail_under:
        return 1
    return 0
