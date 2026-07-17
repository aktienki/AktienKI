from __future__ import annotations

from dataclasses import dataclass, field, asdict
from pathlib import Path
from typing import Any


@dataclass(slots=True)
class FileMetric:
    path: str
    lines: int
    code_lines: int
    classes: int
    functions: int
    imports: int
    complexity: int


@dataclass(slots=True)
class DuplicateGroup:
    similarity: float
    files: list[str]
    reason: str


@dataclass(slots=True)
class AuditReport:
    project_root: str
    generated_at: str
    python_files: int
    total_lines: int
    total_code_lines: int
    package_counts: dict[str, int]
    file_metrics: list[FileMetric] = field(default_factory=list)
    import_graph: dict[str, list[str]] = field(default_factory=dict)
    reverse_import_graph: dict[str, list[str]] = field(default_factory=dict)
    orphan_modules: list[str] = field(default_factory=list)
    duplicate_groups: list[DuplicateGroup] = field(default_factory=list)
    syntax_errors: list[dict[str, Any]] = field(default_factory=list)
    findings: list[dict[str, Any]] = field(default_factory=list)
    architecture_score: float = 0.0

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)
