#!/usr/bin/env python3
from __future__ import annotations

import argparse
import ast
import hashlib
import json
import re
import shutil
import subprocess
import sys
import zipfile
from dataclasses import asdict, dataclass
from datetime import datetime
from pathlib import Path
from typing import Iterable


GENERATED_DIR_NAMES = {
    "__pycache__",
    ".pytest_cache",
    ".mypy_cache",
    ".ruff_cache",
}

GENERATED_FILE_NAMES = {
    ".DS_Store",
}

GENERATED_PREFIXES = {
    "._",
}

PROTOTYPE_NAME_PATTERNS = (
    re.compile(r".*_adaptive\.py$"),
    re.compile(r".*_fixed\.py$"),
    re.compile(r".*_complete\.py$"),
    re.compile(r".*_old\.py$"),
    re.compile(r".*_backup\.py$"),
    re.compile(r".*_copy\.py$"),
    re.compile(r".*_feature\d+\.py$"),
    re.compile(r".*\(\d+\)\.py$"),
)

# Nur Kandidaten. Verschoben wird ausschließlich, wenn die Datei
# nirgendwo importiert wird und eine kanonische Datei vorhanden ist.
CANONICAL_REPLACEMENTS = {
    "app/training/nexus_engine_adaptive.py":
        "app/training/nexus_engine.py",
    "app/training/consensus_engine.py":
        "app/training/nexus_engine.py",
}


@dataclass(slots=True)
class Action:
    kind: str
    path: str
    reason: str
    destination: str | None = None
    safe: bool = True


@dataclass(slots=True)
class CleanupReport:
    project_root: str
    app_root: str
    mode: str
    generated_actions: list[Action]
    prototype_actions: list[Action]
    duplicate_groups: list[list[str]]
    import_references: dict[str, list[str]]
    compile_success: bool | None
    test_command: str | None
    test_success: bool | None
    backup_path: str | None
    quarantine_path: str | None


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description=(
            "Räumt die AktienKI-Python-Struktur sicher auf. "
            "Standardmäßig wird nur ein Bericht erzeugt."
        )
    )

    parser.add_argument(
        "--project-root",
        type=Path,
        default=Path.cwd(),
        help="Pfad zum python-engine-Ordner.",
    )

    parser.add_argument(
        "--apply",
        action="store_true",
        help=(
            "Änderungen tatsächlich anwenden. Ohne diese Option "
            "läuft das Script ausschließlich im Dry-Run."
        ),
    )

    parser.add_argument(
        "--force-prototypes",
        action="store_true",
        help=(
            "Auch referenzierte Prototypdateien in Quarantäne verschieben. "
            "Nur verwenden, wenn die Importe vorher angepasst wurden."
        ),
    )

    parser.add_argument(
        "--test-command",
        default=None,
        help=(
            "Optionaler Testbefehl nach dem Aufräumen, z. B. "
            "'python test_multi_target.py NVDA'."
        ),
    )

    parser.add_argument(
        "--skip-compile",
        action="store_true",
        help="python -m compileall app überspringen.",
    )

    return parser.parse_args()


def sha256(path: Path) -> str:
    digest = hashlib.sha256()

    with path.open("rb") as handle:
        for chunk in iter(
            lambda: handle.read(1024 * 1024),
            b"",
        ):
            digest.update(chunk)

    return digest.hexdigest()


def iter_python_files(app_root: Path) -> Iterable[Path]:
    yield from sorted(
        path
        for path in app_root.rglob("*.py")
        if not any(
            part in GENERATED_DIR_NAMES
            for part in path.parts
        )
    )


def module_name(
    project_root: Path,
    path: Path,
) -> str:
    relative = path.relative_to(project_root)
    parts = list(relative.with_suffix("").parts)

    if parts[-1] == "__init__":
        parts = parts[:-1]

    return ".".join(parts)


class ImportCollector(ast.NodeVisitor):
    def __init__(self) -> None:
        self.modules: set[str] = set()

    def visit_Import(
        self,
        node: ast.Import,
    ) -> None:
        for alias in node.names:
            self.modules.add(alias.name)

    def visit_ImportFrom(
        self,
        node: ast.ImportFrom,
    ) -> None:
        if node.module:
            self.modules.add(node.module)


def collect_import_references(
    project_root: Path,
    app_root: Path,
) -> dict[str, list[str]]:
    references: dict[str, list[str]] = {}

    files = list(iter_python_files(app_root))
    modules = {
        module_name(project_root, path): path
        for path in files
    }

    for source in files:
        try:
            tree = ast.parse(
                source.read_text(
                    encoding="utf-8",
                )
            )
        except (
            SyntaxError,
            UnicodeDecodeError,
        ):
            continue

        collector = ImportCollector()
        collector.visit(tree)

        for imported in collector.modules:
            for candidate_module, candidate_path in modules.items():
                if (
                    imported == candidate_module
                    or imported.startswith(
                        candidate_module + "."
                    )
                ):
                    key = str(
                        candidate_path.relative_to(
                            project_root
                        )
                    )
                    references.setdefault(
                        key,
                        [],
                    ).append(
                        str(
                            source.relative_to(
                                project_root
                            )
                        )
                    )

    for key in references:
        references[key] = sorted(
            set(references[key])
        )

    return references


def generated_actions(
    project_root: Path,
) -> list[Action]:
    actions: list[Action] = []

    for path in sorted(
        project_root.rglob("*")
    ):
        if any(
            part in {
                ".venv",
                "venv",
                ".git",
                "storage",
            }
            for part in path.parts
        ):
            continue

        relative = str(
            path.relative_to(project_root)
        )

        if (
            path.is_dir()
            and path.name in GENERATED_DIR_NAMES
        ):
            actions.append(
                Action(
                    kind="delete_generated_directory",
                    path=relative,
                    reason="Generierter Cache-Ordner",
                )
            )

        elif (
            path.is_file()
            and (
                path.name in GENERATED_FILE_NAMES
                or any(
                    path.name.startswith(prefix)
                    for prefix in GENERATED_PREFIXES
                )
                or path.suffix == ".pyc"
            )
        ):
            actions.append(
                Action(
                    kind="delete_generated_file",
                    path=relative,
                    reason="Generierte/macOS-Datei",
                )
            )

    return actions


def duplicate_groups(
    app_root: Path,
    project_root: Path,
) -> list[list[str]]:
    groups: dict[str, list[Path]] = {}

    for path in iter_python_files(app_root):
        groups.setdefault(
            sha256(path),
            [],
        ).append(path)

    return [
        [
            str(
                path.relative_to(project_root)
            )
            for path in paths
        ]
        for paths in groups.values()
        if len(paths) > 1
    ]


def find_canonical_for_pattern(
    path: Path,
) -> Path | None:
    name = path.name

    candidates = [
        re.sub(
            r"_adaptive(?=\.py$)",
            "",
            name,
        ),
        re.sub(
            r"_fixed(?=\.py$)",
            "",
            name,
        ),
        re.sub(
            r"_complete(?=\.py$)",
            "",
            name,
        ),
        re.sub(
            r"_old(?=\.py$)",
            "",
            name,
        ),
        re.sub(
            r"_backup(?=\.py$)",
            "",
            name,
        ),
        re.sub(
            r"_copy(?=\.py$)",
            "",
            name,
        ),
        re.sub(
            r"_feature\d+(?=\.py$)",
            "",
            name,
        ),
        re.sub(
            r"\(\d+\)(?=\.py$)",
            "",
            name,
        ),
    ]

    for candidate_name in candidates:
        candidate = path.with_name(
            candidate_name
        )

        if (
            candidate != path
            and candidate.exists()
        ):
            return candidate

    return None


def prototype_actions(
    project_root: Path,
    app_root: Path,
    references: dict[str, list[str]],
    quarantine_root: Path,
    force: bool,
) -> list[Action]:
    actions: list[Action] = []
    candidates: dict[Path, Path] = {}

    for relative_source, relative_canonical in (
        CANONICAL_REPLACEMENTS.items()
    ):
        source = project_root / relative_source
        canonical = project_root / relative_canonical

        if source.exists() and canonical.exists():
            candidates[source] = canonical

    for path in iter_python_files(app_root):
        if not any(
            pattern.match(path.name)
            for pattern in PROTOTYPE_NAME_PATTERNS
        ):
            continue

        canonical = find_canonical_for_pattern(
            path
        )

        if canonical is not None:
            candidates.setdefault(
                path,
                canonical,
            )

    for source, canonical in sorted(
        candidates.items(),
        key=lambda item: str(item[0]),
    ):
        relative_source = str(
            source.relative_to(project_root)
        )

        used_by = references.get(
            relative_source,
            [],
        )

        safe = (
            not used_by
            or force
        )

        reason = (
            "Nicht referenzierter Prototyp; "
            f"kanonische Datei: "
            f"{canonical.relative_to(project_root)}"
        )

        if used_by:
            reason += (
                "; referenziert durch: "
                + ", ".join(used_by)
            )

        destination = quarantine_root / (
            source.relative_to(
                project_root
            )
        )

        actions.append(
            Action(
                kind="quarantine_prototype",
                path=relative_source,
                reason=reason,
                destination=str(
                    destination.relative_to(
                        project_root
                    )
                ),
                safe=safe,
            )
        )

    return actions


def create_backup(
    project_root: Path,
    app_root: Path,
    timestamp: str,
) -> Path:
    backup_dir = (
        project_root
        / "storage"
        / "cleanup_backups"
    )
    backup_dir.mkdir(
        parents=True,
        exist_ok=True,
    )

    backup_path = (
        backup_dir
        / f"app_before_cleanup_{timestamp}.zip"
    )

    with zipfile.ZipFile(
        backup_path,
        "w",
        zipfile.ZIP_DEFLATED,
    ) as archive:
        for path in sorted(
            app_root.rglob("*")
        ):
            if path.is_file():
                archive.write(
                    path,
                    path.relative_to(
                        project_root
                    ),
                )

    return backup_path


def apply_generated_actions(
    project_root: Path,
    actions: list[Action],
) -> None:
    directories = [
        action
        for action in actions
        if action.kind
        == "delete_generated_directory"
    ]

    files = [
        action
        for action in actions
        if action.kind
        == "delete_generated_file"
    ]

    for action in files:
        path = project_root / action.path

        if path.exists():
            path.unlink()

    for action in sorted(
        directories,
        key=lambda item: len(
            Path(item.path).parts
        ),
        reverse=True,
    ):
        path = project_root / action.path

        if path.exists():
            shutil.rmtree(path)


def apply_prototype_actions(
    project_root: Path,
    actions: list[Action],
) -> None:
    for action in actions:
        if not action.safe:
            continue

        source = project_root / action.path
        destination = (
            project_root
            / str(action.destination)
        )

        if not source.exists():
            continue

        destination.parent.mkdir(
            parents=True,
            exist_ok=True,
        )

        shutil.move(
            str(source),
            str(destination),
        )


def run_command(
    command: str,
    cwd: Path,
) -> bool:
    print(f"\n$ {command}")

    result = subprocess.run(
        command,
        cwd=cwd,
        shell=True,
        text=True,
    )

    return result.returncode == 0


def write_report(
    report: CleanupReport,
    project_root: Path,
    timestamp: str,
) -> Path:
    report_dir = (
        project_root
        / "storage"
        / "cleanup_reports"
    )
    report_dir.mkdir(
        parents=True,
        exist_ok=True,
    )

    report_path = (
        report_dir
        / f"cleanup_report_{timestamp}.json"
    )

    report_path.write_text(
        json.dumps(
            asdict(report),
            indent=2,
            ensure_ascii=False,
        ),
        encoding="utf-8",
    )

    return report_path


def print_summary(
    report: CleanupReport,
    report_path: Path,
) -> None:
    print("\n" + "=" * 72)
    print("AKI STRUCTURE CLEANUP")
    print("=" * 72)
    print(f"Modus: {report.mode}")
    print(
        "Generierte Dateien/Ordner: "
        f"{len(report.generated_actions)}"
    )
    print(
        "Prototyp-Kandidaten: "
        f"{len(report.prototype_actions)}"
    )
    print(
        "Sicher verschiebbar: "
        f"{sum(a.safe for a in report.prototype_actions)}"
    )
    print(
        "Hash-identische Dateigruppen: "
        f"{len(report.duplicate_groups)}"
    )

    unsafe = [
        action
        for action in report.prototype_actions
        if not action.safe
    ]

    if unsafe:
        print("\nNicht automatisch verschoben:")
        for action in unsafe:
            print(
                f"- {action.path}: {action.reason}"
            )

    print(f"\nBericht: {report_path}")

    if report.backup_path:
        print(f"Backup: {report.backup_path}")

    if report.quarantine_path:
        print(
            "Quarantäne: "
            f"{report.quarantine_path}"
        )

    print("\nNächster Test:")
    print("python -m compileall app")


def main() -> int:
    args = parse_args()

    project_root = (
        args.project_root
        .expanduser()
        .resolve()
    )

    app_root = project_root / "app"

    if not app_root.is_dir():
        print(
            f"Fehler: {app_root} wurde nicht gefunden.",
            file=sys.stderr,
        )
        return 2

    timestamp = datetime.now().strftime(
        "%Y%m%d_%H%M%S"
    )

    quarantine_root = (
        project_root
        / "storage"
        / "cleanup_quarantine"
        / timestamp
    )

    references = collect_import_references(
        project_root,
        app_root,
    )

    generated = generated_actions(
        project_root
    )

    prototypes = prototype_actions(
        project_root=project_root,
        app_root=app_root,
        references=references,
        quarantine_root=quarantine_root,
        force=args.force_prototypes,
    )

    duplicates = duplicate_groups(
        app_root,
        project_root,
    )

    backup_path: Path | None = None
    compile_success: bool | None = None
    test_success: bool | None = None

    if args.apply:
        backup_path = create_backup(
            project_root,
            app_root,
            timestamp,
        )

        apply_generated_actions(
            project_root,
            generated,
        )

        apply_prototype_actions(
            project_root,
            prototypes,
        )

        if not args.skip_compile:
            compile_success = run_command(
                "python -m compileall app",
                project_root,
            )

        if (
            args.test_command
            and compile_success is not False
        ):
            test_success = run_command(
                args.test_command,
                project_root,
            )

    report = CleanupReport(
        project_root=str(project_root),
        app_root=str(app_root),
        mode=(
            "apply"
            if args.apply
            else "dry-run"
        ),
        generated_actions=generated,
        prototype_actions=prototypes,
        duplicate_groups=duplicates,
        import_references=references,
        compile_success=compile_success,
        test_command=args.test_command,
        test_success=test_success,
        backup_path=(
            str(backup_path)
            if backup_path
            else None
        ),
        quarantine_path=(
            str(quarantine_root)
            if args.apply
            else None
        ),
    )

    report_path = write_report(
        report,
        project_root,
        timestamp,
    )

    print_summary(
        report,
        report_path,
    )

    if compile_success is False:
        return 1

    if test_success is False:
        return 1

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
