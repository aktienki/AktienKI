from __future__ import annotations


class TrainTestSplit:

    @staticmethod
    def split(

        dataframe,

        target,

        train_size: float = 0.80,

    ):

        split = int(

            len(dataframe)

            * train_size

        )

        train_x = dataframe.iloc[:split]

        train_y = target.iloc[:split]

        valid_x = dataframe.iloc[split:]

        valid_y = target.iloc[split:]

        return (

            train_x,

            train_y,

            valid_x,

            valid_y,

        )