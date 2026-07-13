from sqlalchemy import create_engine
from sqlalchemy.orm import Session, sessionmaker

def build_engine(settings):
    return create_engine(settings.database_url, pool_pre_ping=True, future=True)

def build_session_factory(engine):
    return sessionmaker(bind=engine, class_=Session, autoflush=False, expire_on_commit=False)
