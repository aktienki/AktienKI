from __future__ import annotations

import json
from pathlib import Path

from app.knowledge.model_memory import ModelMemory


class KnowledgeStore:

    def __init__(self):

        self.folder = Path("storage/knowledge")

        self.folder.mkdir(

            parents=True,

            exist_ok=True,

        )

    # -------------------------------------------------------

    def save(

        self,

        memory: ModelMemory,

    ):

        filename = (

            self.folder

            / f"{memory.symbol}_{memory.timeframe}.json"

        )

        filename.write_text(

            json.dumps(

                memory.__dict__,

                default=str,

                indent=4,

            )

        )

    # -------------------------------------------------------

    def load(

        self,

        symbol,

        timeframe,

    ):

        filename = (

            self.folder

            / f"{symbol}_{timeframe}.json"

        )

        if not filename.exists():

            return None

        return json.loads(

            filename.read_text()

        )