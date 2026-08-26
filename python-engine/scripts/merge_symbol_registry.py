from __future__ import annotations

import argparse
import json
import os
import tempfile
from pathlib import Path


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument('--symbol', required=True)
    parser.add_argument('--records', type=Path, required=True)
    parser.add_argument('--registry', type=Path, required=True)
    args = parser.parse_args()
    incoming = json.loads(args.records.read_text(encoding='utf-8'))
    existing = json.loads(args.registry.read_text(encoding='utf-8'))
    symbol = args.symbol.upper()
    retained = [record for record in existing if str(record.get('metadata', {}).get('symbol', '')).upper() != symbol]
    merged = retained + incoming
    fd, temporary = tempfile.mkstemp(prefix='registry-', suffix='.json', dir=args.registry.parent)
    try:
        with os.fdopen(fd, 'w', encoding='utf-8') as handle:
            json.dump(merged, handle, ensure_ascii=False, separators=(',', ':'))
            handle.flush(); os.fsync(handle.fileno())
        os.replace(temporary, args.registry)
    finally:
        if os.path.exists(temporary): os.unlink(temporary)
    print(f'merged_symbol={args.symbol} records={len(incoming)} total={len(merged)}')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
