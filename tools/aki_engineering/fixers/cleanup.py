from __future__ import annotations

import shutil
from pathlib import Path

CACHE_DIRS = {'__pycache__', '.pytest_cache', '.mypy_cache', '.ruff_cache'}


def clean_generated(project_root: Path, apply: bool = False) -> list[str]:
    candidates: list[Path] = []
    for path in project_root.rglob('*'):
        if '.git' in path.parts or '.venv' in path.parts or 'venv' in path.parts:
            continue
        if path.is_dir() and path.name in CACHE_DIRS:
            candidates.append(path)
        elif path.is_file() and (path.suffix == '.pyc' or path.name == '.DS_Store' or path.name.startswith('._')):
            candidates.append(path)

    if apply:
        for path in sorted(candidates, key=lambda p: len(p.parts), reverse=True):
            if not path.exists():
                continue
            if path.is_dir():
                shutil.rmtree(path)
            else:
                path.unlink()
    return [str(p.relative_to(project_root)) for p in candidates]
