from __future__ import annotations

import ast
from dataclasses import dataclass
from pathlib import Path
from typing import Iterable

EXCLUDED_DIRS = {
    '.git', '.venv', 'venv', '__pycache__', '.pytest_cache',
    '.mypy_cache', '.ruff_cache', 'storage', 'vendor', 'node_modules'
}


@dataclass(slots=True)
class ParsedModule:
    path: Path
    module: str
    tree: ast.AST | None
    error: str | None
    text: str


def iter_python_files(root: Path) -> Iterable[Path]:
    for path in sorted(root.rglob('*.py')):
        if any(part in EXCLUDED_DIRS for part in path.parts):
            continue
        yield path


def module_name(project_root: Path, path: Path) -> str:
    rel = path.relative_to(project_root).with_suffix('')
    parts = list(rel.parts)
    if parts and parts[-1] == '__init__':
        parts.pop()
    return '.'.join(parts)


def parse_project(project_root: Path) -> list[ParsedModule]:
    modules: list[ParsedModule] = []
    for path in iter_python_files(project_root):
        text = path.read_text(encoding='utf-8', errors='replace')
        try:
            tree = ast.parse(text, filename=str(path))
            error = None
        except SyntaxError as exc:
            tree = None
            error = f'{exc.msg} (line {exc.lineno})'
        modules.append(ParsedModule(
            path=path,
            module=module_name(project_root, path),
            tree=tree,
            error=error,
            text=text,
        ))
    return modules
