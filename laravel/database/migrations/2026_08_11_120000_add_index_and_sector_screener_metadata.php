<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_indices', function (Blueprint $table) {
            $table->unsignedSmallInteger('global_rank')->nullable()->index();
            $table->string('region', 40)->nullable()->index();
            $table->decimal('rating', 5, 2)->nullable();
            $table->text('assessment')->nullable();
            $table->timestampTz('rating_updated_at')->nullable();
        });

        Schema::create('market_sectors', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->decimal('rating', 5, 2)->nullable();
            $table->text('assessment')->nullable();
            $table->timestampTz('rating_updated_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();
        });

        $now = now();
        $indices = [
            ['^GSPC','S&P 500','US','USD','Nordamerika','US-Leitindex der 500 großen börsennotierten Unternehmen.'],
            ['^IXIC','Nasdaq Composite','US','USD','Nordamerika','Breiter, technologielastiger Index der Nasdaq-Börse.'],
            ['^DJI','Dow Jones Industrial Average','US','USD','Nordamerika','Kursgewichteter US-Leitindex mit 30 etablierten Großunternehmen.'],
            ['^RUT','Russell 2000','US','USD','Nordamerika','Benchmark für kleinere börsennotierte US-Unternehmen.'],
            ['^NYA','NYSE Composite','US','USD','Nordamerika','Breiter Index der an der New York Stock Exchange gelisteten Aktien.'],
            ['^GSPTSE','S&P/TSX Composite','CA','CAD','Nordamerika','Wichtigster kanadischer Aktienindex mit breiter Marktabdeckung.'],
            ['^MXX','S&P/BMV IPC','MX','MXN','Lateinamerika','Leitindex der größten und liquidesten Aktien Mexikos.'],
            ['^BVSP','Bovespa','BR','BRL','Lateinamerika','Bedeutendster brasilianischer Aktienindex der Börse B3.'],
            ['^MERV','S&P MERVAL','AR','ARS','Lateinamerika','Leitindex des argentinischen Aktienmarkts.'],
            ['^IPSA','S&P IPSA','CL','CLP','Lateinamerika','Leitindex der liquidesten chilenischen Aktien.'],
            ['^FTSE','FTSE 100','GB','GBP','Europa','Leitindex der 100 größten Unternehmen an der London Stock Exchange.'],
            ['^GDAXI','DAX','DE','EUR','Europa','Leitindex der 40 größten deutschen Standardwerte.'],
            ['^FCHI','CAC 40','FR','EUR','Europa','Leitindex der 40 wichtigsten französischen Aktien.'],
            ['^STOXX50E','EURO STOXX 50','EU','EUR','Europa','Blue-Chip-Index der Eurozone mit 50 großen Unternehmen.'],
            ['^STOXX','STOXX Europe 600','EU','EUR','Europa','Breiter europäischer Index mit 600 Unternehmen.'],
            ['FTSEMIB.MI','FTSE MIB','IT','EUR','Europa','Leitindex der größten italienischen Aktiengesellschaften.'],
            ['^IBEX','IBEX 35','ES','EUR','Europa','Leitindex der 35 liquidesten spanischen Aktien.'],
            ['^AEX','AEX','NL','EUR','Europa','Leitindex der größten niederländischen Börsenwerte.'],
            ['^BFX','BEL 20','BE','EUR','Europa','Leitindex der wichtigsten belgischen Aktien.'],
            ['^SSMI','SMI','CH','CHF','Europa','Leitindex der größten und liquidesten Schweizer Aktien.'],
            ['^ATX','ATX','AT','EUR','Europa','Leitindex der Wiener Börse.'],
            ['PSI20.LS','PSI','PT','EUR','Europa','Leitindex des portugiesischen Aktienmarkts.'],
            ['^OMX','OMX Stockholm 30','SE','SEK','Europa','Leitindex der 30 meistgehandelten Aktien in Stockholm.'],
            ['^OMXC25','OMX Copenhagen 25','DK','DKK','Europa','Leitindex der 25 größten und liquidesten dänischen Aktien.'],
            ['^OMXH25','OMX Helsinki 25','FI','EUR','Europa','Leitindex der 25 meistgehandelten finnischen Aktien.'],
            ['^OSEAX','Oslo All Share','NO','NOK','Europa','Breiter Index des norwegischen Aktienmarkts.'],
            ['^WIG20','WIG20','PL','PLN','Europa','Leitindex der 20 größten polnischen Aktien.'],
            ['^BUX','BUX','HU','HUF','Europa','Leitindex der Budapester Börse.'],
            ['^N225','Nikkei 225','JP','JPY','Asien-Pazifik','Bekanntester japanischer Leitindex mit 225 Aktien.'],
            ['^TOPX','TOPIX','JP','JPY','Asien-Pazifik','Breiter kapitalisierungsgewichteter Index des japanischen Aktienmarkts.'],
            ['^HSI','Hang Seng Index','HK','HKD','Asien-Pazifik','Leitindex der größten Unternehmen in Hongkong.'],
            ['000001.SS','SSE Composite','CN','CNY','Asien-Pazifik','Breiter Index der Shanghai Stock Exchange.'],
            ['399001.SZ','Shenzhen Component','CN','CNY','Asien-Pazifik','Leitindex bedeutender Aktien der Börse Shenzhen.'],
            ['^KS11','KOSPI','KR','KRW','Asien-Pazifik','Leitindex des südkoreanischen Aktienmarkts.'],
            ['^TWII','Taiwan Weighted','TW','TWD','Asien-Pazifik','Breiter kapitalisierungsgewichteter Index Taiwans.'],
            ['^BSESN','S&P BSE Sensex','IN','INR','Asien-Pazifik','Indischer Blue-Chip-Index mit 30 großen Unternehmen.'],
            ['^NSEI','Nifty 50','IN','INR','Asien-Pazifik','Leitindex der 50 großen und liquiden indischen Aktien.'],
            ['^STI','Straits Times Index','SG','SGD','Asien-Pazifik','Leitindex des Aktienmarkts von Singapur.'],
            ['^AXJO','S&P/ASX 200','AU','AUD','Asien-Pazifik','Leitindex der 200 größten australischen Aktien.'],
            ['^AORD','All Ordinaries','AU','AUD','Asien-Pazifik','Breiter etablierter Index des australischen Aktienmarkts.'],
            ['^NZ50','S&P/NZX 50','NZ','NZD','Asien-Pazifik','Leitindex der 50 größten neuseeländischen Aktien.'],
            ['^JKSE','Jakarta Composite','ID','IDR','Asien-Pazifik','Breiter Leitindex des indonesischen Aktienmarkts.'],
            ['^KLSE','FTSE Bursa Malaysia KLCI','MY','MYR','Asien-Pazifik','Leitindex der größten Aktien Malaysias.'],
            ['^SET.BK','SET Index','TH','THB','Asien-Pazifik','Breiter Leitindex der Börse Thailand.'],
            ['PSEI.PS','PSEi','PH','PHP','Asien-Pazifik','Leitindex der philippinischen Börse.'],
            ['^TA125.TA','TA-125','IL','ILS','Nahost & Afrika','Breiter Leitindex der Börse Tel Aviv.'],
            ['^CASE30','EGX 30','EG','EGP','Nahost & Afrika','Leitindex der 30 liquidesten ägyptischen Aktien.'],
            ['^JN0U.JO','FTSE/JSE Top 40','ZA','ZAR','Nahost & Afrika','Blue-Chip-Index der 40 größten südafrikanischen Aktien.'],
            ['^TASI.SR','Tadawul All Share','SA','SAR','Nahost & Afrika','Breiter Leitindex des saudischen Aktienmarkts.'],
            ['DFMGI.AE','Dubai Financial Market General','AE','AED','Nahost & Afrika','Leitindex der Dubai Financial Market.'],
        ];

        foreach ($indices as $rank => [$symbol, $name, $country, $currency, $region, $description]) {
            DB::table('market_indices')->updateOrInsert(
                ['symbol' => $symbol],
                compact('name', 'country', 'currency', 'region', 'description') + ['global_rank' => $rank + 1, 'is_active' => true, 'updated_at' => $now, 'created_at' => $now]
            );
        }

        $sectors = [
            ['energy','Energy','Energieunternehmen aus Öl, Gas, Kohle sowie zugehörigen Dienstleistungen.'],
            ['materials','Basic Materials','Rohstoffe, Chemie, Baustoffe, Papier, Metalle und Bergbau.'],
            ['industrials','Industrials','Industriegüter, Maschinenbau, Transport, Luftfahrt und Unternehmensdienste.'],
            ['consumer-cyclical','Consumer Cyclical','Konjunkturabhängiger Konsum wie Automobile, Handel, Reisen und Freizeit.'],
            ['consumer-defensive','Consumer Defensive','Basiskonsumgüter, Lebensmittel, Getränke und Haushaltsprodukte.'],
            ['healthcare','Healthcare','Pharma, Biotechnologie, Medizintechnik und Gesundheitsdienstleistungen.'],
            ['financial-services','Financial Services','Banken, Versicherungen, Vermögensverwaltung und Finanzdienstleister.'],
            ['technology','Technology','Software, Halbleiter, IT-Dienste und technologische Hardware.'],
            ['communication-services','Communication Services','Telekommunikation, Medien, Unterhaltung und interaktive Dienste.'],
            ['utilities','Utilities','Strom-, Gas- und Wasserversorger sowie unabhängige Energieerzeuger.'],
            ['real-estate','Real Estate','Immobiliengesellschaften, Projektentwickler und REITs.'],
        ];
        foreach ($sectors as [$slug, $name, $description]) {
            DB::table('market_sectors')->updateOrInsert(
                ['slug' => $slug],
                compact('name', 'description') + ['is_active' => true, 'updated_at' => $now, 'created_at' => $now]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('market_sectors');
        Schema::table('market_indices', function (Blueprint $table) {
            $table->dropColumn(['global_rank', 'region', 'rating', 'assessment', 'rating_updated_at']);
        });
    }
};
