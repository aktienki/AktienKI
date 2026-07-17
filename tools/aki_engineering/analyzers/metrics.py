from __future__ import annotations

import ast
from collections import Counter
from pathlib import Path

from ..models import FileMetric
from ..project import ParsedModule

BRANCH_NODES = (
    ast.If, ast.For, ast.AsyncFor, ast.While, ast.Try,
    ast.With, ast.AsyncWith, ast.BoolOp, ast.IfExp,
    ast.Match, ast.comprehension,
)


def analyze_metrics(project_root: Path, modules: list[ParsedModule]):
    metrics: list[FileMetric] = []
    package_counts: Counter[str] = Counter()
    total_lines = 0
    total_code_lines = 0

    for mod in modules:
        lines = mod.text.splitlines()
        code_lines = sum(
            1 for line in lines
            if line.strip() and not line.lstrip().startswith('#')
        )
        total_lines += len(lines)
        total_code_lines += code_lines

        if mod.tree is None:
            classes = functions = imports = complexity = 0
        else:
            nodes = list(ast.walk(mod.tree))
            classes = sum(isinstance(n, ast.ClassDef) for n in nodes)
            functions = sum(isinstance(n, (ast.FunctionDef, ast.AsyncFunctionDef)) for n in nodes)
            imports = sum(isinstance(n, (ast.Import, ast.ImportFrom)) for n in nodes)
            complexity = 1 + sum(isinstance(n, BRANCH_NODES) for n in nodes)

        rel = mod.path.relative_to(project_root)
        package = rel.parts[0] if rel.parts else '.'
        if len(rel.parts) > 1:
            package = '/'.join(rel.parts[:2])
        package_counts[package] += 1

        metrics.append(FileMetric(
            path=str(rel),
            lines=len(lines),
            code_lines=code_lines,
            classes=classes,
            functions=functions,
            imports=imports,
            complexity=complexity,
        ))

    return metrics, dict(sorted(package_counts.items())), total_lines, total_code_lines
