from sqlalchemy import text

class PriceRepository:
    def __init__(self, session):
        self.session=session

    def latest(self,instrument_id:int,interval:str="1d",limit:int=500):
        sql=text("""
        SELECT *
        FROM price_bars
        WHERE instrument_id=:iid
          AND interval=:interval
        ORDER BY bar_time DESC
        LIMIT :limit
        """)
        return self.session.execute(sql,{
            "iid":instrument_id,
            "interval":interval,
            "limit":limit
        }).mappings().all()
