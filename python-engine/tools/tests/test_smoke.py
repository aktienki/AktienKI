from pathlib import Path
import tempfile

from aki_engineering.project import parse_project
from aki_engineering.analyzers.metrics import analyze_metrics


def test_smoke():
    with tempfile.TemporaryDirectory() as temp:
        root = Path(temp)
        (root / 'app').mkdir()
        (root / 'app' / '__init__.py').write_text('', encoding='utf-8')
        (root / 'app' / 'demo.py').write_text('def hello():\n    return 1\n', encoding='utf-8')
        modules = parse_project(root)
        metrics, _, _, _ = analyze_metrics(root, modules)
        assert len(modules) == 2
        assert any(item.functions == 1 for item in metrics)
