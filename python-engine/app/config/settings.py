from dataclasses import dataclass
import os
from dotenv import load_dotenv

@dataclass(frozen=True, slots=True)
class Settings:
    database_url: str
    yahoo_timeout_seconds: int
    import_batch_size: int
    log_level: str

    @classmethod
    def load(cls):
        load_dotenv()
        url = os.getenv("DATABASE_URL", "").strip()
        if not url:
            raise RuntimeError("DATABASE_URL fehlt. .env.example nach .env kopieren.")
        return cls(
            database_url=url,
            yahoo_timeout_seconds=int(os.getenv("YAHOO_TIMEOUT_SECONDS", "30")),
            import_batch_size=max(1, int(os.getenv("IMPORT_BATCH_SIZE", "1000"))),
            log_level=os.getenv("LOG_LEVEL", "INFO").upper(),
        )
