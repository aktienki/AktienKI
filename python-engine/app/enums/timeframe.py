from enum import Enum


class TimeFrame(str, Enum):
    """
    Supported market timeframes.
    """

    M15 = "15m"

    H1 = "1h"

    H4 = "4h"

    D1 = "1d"

    W1 = "1w"

    MN1 = "1M"

    @property
    def is_intraday(self) -> bool:
        return self in {
            TimeFrame.M15,
            TimeFrame.H1,
            TimeFrame.H4,
        }

    @property
    def is_daily(self) -> bool:
        return self == TimeFrame.D1

    @property
    def is_weekly(self) -> bool:
        return self == TimeFrame.W1

    @property
    def is_monthly(self) -> bool:
        return self == TimeFrame.MN1

    @property
    def pandas_interval(self) -> str:
        """
        Interval used by pandas / yfinance.
        """

        return self.value

    @property
    def description(self) -> str:

        descriptions = {

            TimeFrame.M15: "15 Minute",

            TimeFrame.H1: "1 Stunde",

            TimeFrame.H4: "4 Stunden",

            TimeFrame.D1: "1 Tag",

            TimeFrame.W1: "1 Woche",

            TimeFrame.MN1: "1 Monat",

        }

        return descriptions[self]