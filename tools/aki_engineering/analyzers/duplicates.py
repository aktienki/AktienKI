from __future__ import annotations

import ast
import hashlib
from collections import defaultdict
from difflib import SequenceMatcher

from ..models import DuplicateGroup
from ..project import ParsedModule


def _normalized(tree: ast.AST | None) -> str:
    if tree is None:
        return ''
    return ast.dump(tree, annotate_fields=False, include_attributes=False)


def analyze_duplicates(modules: list[ParsedModule], threshold: float = 0.92) -> list[DuplicateGroup]:
    groups: list[DuplicateGroup] = []
    by_hash: dict[str, list[ParsedModule]] = defaultdict(list)
    normalized: dict[str, str] = {}

    for mod in modules:
        value = _normalized(mod.tree)
        normalized[mod.module] = value
        if value:
            by_hash[hashlib.sha256(value.encode()).hexdigest()].append(mod)

    exact_modules: set[str] = set()
    for mods in by_hash.values():
        if len(mods) > 1:
            paths = [str(m.path) for m in mods]
            groups.append(DuplicateGroup(1.0, paths, 'AST-identisch'))
            exact_modules.update(m.module for m in mods)

    candidates = [m for m in modules if m.module not in exact_modules and len(normalized[m.module]) >= 300]
    for i, left in enumerate(candidates):
        for right in candidates[i + 1:]:
            if left.path.name == '__init__.py' or right.path.name == '__init__.py':
                continue
            ratio = SequenceMatcher(None, normalized[left.module], normalized[right.module]).ratio()
            if ratio >= threshold:
                groups.append(DuplicateGroup(
                    round(ratio, 4),
                    [str(left.path), str(right.path)],
                    'Hohe AST-Ähnlichkeit',
                ))
    return sorted(groups, key=lambda g: g.similarity, reverse=True)
