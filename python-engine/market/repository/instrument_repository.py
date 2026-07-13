from sqlalchemy import text

class InstrumentRepository:
    def __init__(self, session):
        self.session=session

    def get_by_symbol(self,symbol:str):
        sql=text("SELECT * FROM instruments WHERE symbol=:symbol LIMIT 1")
        return self.session.execute(sql,{"symbol":symbol}).mappings().first()

    def active(self):
        sql=text("SELECT * FROM instruments WHERE is_active=true")
        return self.session.execute(sql).mappings().all()
