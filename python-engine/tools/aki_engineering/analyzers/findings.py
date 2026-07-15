from __future__ import annotations

from ..models import FileMetric, DuplicateGroup


def build_findings(metrics: list[FileMetric], orphans: list[str], duplicates: list[DuplicateGroup], syntax_errors):
    findings: list[dict] = []
    for item in metrics:
        if item.lines > 500:
            findings.append({'severity': 'warning', 'type': 'large_file', 'path': item.path, 'message': f'{item.lines} Zeilen'})
        if item.complexity > 80:
            findings.append({'severity': 'warning', 'type': 'complexity', 'path': item.path, 'message': f'Komplexität {item.complexity}'})
        if item.imports > 25:
            findings.append({'severity': 'info', 'type': 'many_imports', 'path': item.path, 'message': f'{item.imports} Imports'})

    for module in orphans:
        findings.append({'severity': 'info', 'type': 'orphan_module', 'path': module, 'message': 'Kein interner Import gefunden; Entry-Point oder Dead-Code prüfen'})

    for group in duplicates:
        findings.append({'severity': 'warning', 'type': 'duplicate_code', 'path': ', '.join(group.files), 'message': f'{group.reason}, Ähnlichkeit {group.similarity:.1%}'})

    for error in syntax_errors:
        findings.append({'severity': 'error', 'type': 'syntax_error', 'path': error['path'], 'message': error['error']})
    return findings


def architecture_score(findings: list[dict], python_files: int) -> float:
    penalties = {'error': 8.0, 'warning': 2.0, 'info': 0.25}
    total = sum(penalties.get(f.get('severity', 'info'), 0.25) for f in findings)
    normalization = max(1.0, python_files / 25.0)
    return round(max(0.0, 100.0 - total / normalization), 2)
