from dataclasses import dataclass
from datetime import datetime

@dataclass
class ImportStatus:
    symbol:str
    started_at:datetime=datetime.utcnow()
    bars_written:int=0
    retries:int=0
    success:bool=False

class RetryHandler:
    def __init__(self,max_retries=3):
        self.max_retries=max_retries
    def run(self,func,*args,**kwargs):
        last=None
        for i in range(self.max_retries):
            try:
                return func(*args,**kwargs),i
            except Exception as e:
                last=e
        raise last
