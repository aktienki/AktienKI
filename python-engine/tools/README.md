# AKI Engineering Suite v0.1

In den Ordner `python-engine/tools` kopieren.

## Audit

```bash
python tools/aki_audit.py
```

Reports:

```text
storage/aki_audit/aki_audit.html
storage/aki_audit/aki_audit.md
storage/aki_audit/aki_audit.json
storage/aki_audit/dependency_graph.dot
```

## Cleanup prüfen

```bash
python tools/aki_audit.py --clean
```

## Cleanup anwenden

```bash
python tools/aki_audit.py --clean --apply
```

## Git/CI Quality Gate

```bash
python tools/aki_audit.py --fail-under 85
```

Die Orphan-Erkennung ist bewusst konservativ: Module ohne interne Importe sind Kandidaten, keine automatischen Löschungen.
