from __future__ import annotations

import ast
from collections import defaultdict

from ..project import ParsedModule


def _resolve_relative(current: str, level: int, name: str | None) -> str:
    parts = current.split('.')
    if parts:
        parts = parts[:-1]
    if level:
        parts = parts[:max(0, len(parts) - level + 1)]
    if name:
        parts.extend(name.split('.'))
    return '.'.join(parts)


def analyze_imports(modules: list[ParsedModule]):
    known = {m.module for m in modules}
    graph: dict[str, set[str]] = defaultdict(set)
    reverse: dict[str, set[str]] = defaultdict(set)

    for mod in modules:
        if mod.tree is None:
            continue
        for node in ast.walk(mod.tree):
            targets: list[str] = []
            if isinstance(node, ast.Import):
                targets = [a.name for a in node.names]
            elif isinstance(node, ast.ImportFrom):
                if node.level:
                    base = _resolve_relative(mod.module, node.level, node.module)
                else:
                    base = node.module or ''
                targets = [base] if base else []

            for target in targets:
                matches = [k for k in known if target == k or target.startswith(k + '.') or k.startswith(target + '.')]
                if matches:
                    chosen = max(matches, key=len)
                    if chosen != mod.module:
                        graph[mod.module].add(chosen)
                        reverse[chosen].add(mod.module)

    graph_out = {k: sorted(v) for k, v in sorted(graph.items())}
    reverse_out = {k: sorted(v) for k, v in sorted(reverse.items())}
    orphans = sorted(
        m.module for m in modules
        if m.module and not reverse.get(m.module)
        and not m.module.endswith('.cli')
        and not m.module.endswith('.__main__')
        and m.path.name != '__init__.py'
    )
    return graph_out, reverse_out, orphans
