<?php
namespace App\Enums;
enum InstrumentType:string {
case Stock='stock';
case ETF='etf';
case Index='index';
case Forex='forex';
case Crypto='crypto';
}
