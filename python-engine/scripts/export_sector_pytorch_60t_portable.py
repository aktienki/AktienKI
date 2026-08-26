from __future__ import annotations

import argparse
import json
from pathlib import Path

import numpy as np
import torch


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Export the trained sector GRU for NumPy-only server inference"
    )
    parser.add_argument("--artifact", type=Path, required=True)
    parser.add_argument("--report", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()

    checkpoint = torch.load(args.artifact, map_location="cpu", weights_only=False)
    metadata = {
        "feature_names": list(checkpoint["feature_names"]),
        "horizons": list(checkpoint["horizons"]),
        "sector_ids": {str(key): int(value) for key, value in checkpoint["sector_ids"].items()},
        "sequence_length": int(checkpoint["sequence_length"]),
        "trained_at": str(checkpoint.get("trained_at", args.artifact.name)),
        "report": json.loads(args.report.read_text(encoding="utf-8")),
    }
    arrays = {
        "metadata": np.asarray(json.dumps(metadata, ensure_ascii=False)),
        "normalization_mean": np.asarray(checkpoint["normalization_mean"], dtype=np.float32),
        "normalization_std": np.asarray(checkpoint["normalization_std"], dtype=np.float32),
    }
    arrays.update({
        f"state__{name}": tensor.detach().cpu().numpy().astype(np.float32)
        for name, tensor in checkpoint["state_dict"].items()
    })
    args.output.parent.mkdir(parents=True, exist_ok=True)
    np.savez_compressed(args.output, **arrays)
    print(json.dumps({
        "source": str(args.artifact), "output": str(args.output),
        "sectors": len(metadata["sector_ids"]), "horizons": metadata["horizons"],
    }))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
