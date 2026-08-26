<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EtfCertificatePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_beta_user_can_open_etf_and_certificate_page(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_beta_tester' => true,
        ]);

        $this->actingAs($user)->get('/etfs-zertifikate')
            ->assertOk()
            ->assertSee('ETFs &amp; Zertifikate', false);

        $this->actingAs($user)->get('/etfs-zertifikate?tab=certificates')
            ->assertOk()
            ->assertSee('Zertifikate &amp; Anleihen', false);
    }

    public function test_certificates_route_opens_certificate_tab(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_beta_tester' => true,
        ]);

        $this->actingAs($user)->get('/zertifikate')
            ->assertOk()
            ->assertSee('Aktuelle Discount- und Bonuszertifikate')
            ->assertSee('Discountzertifikate');
    }

    public function test_certificates_can_be_filtered_by_underlying_instrument(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'is_beta_tester' => true]);
        $instrumentId = DB::table('instruments')->insertGetId([
            'type' => 'stock', 'symbol' => 'FILTER', 'name' => 'Filter AG', 'currency' => 'EUR',
            'is_active' => true, 'is_tradeable' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $otherId = DB::table('instruments')->insertGetId([
            'type' => 'stock', 'symbol' => 'OTHER', 'name' => 'Other AG', 'currency' => 'EUR',
            'is_active' => true, 'is_tradeable' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([[$instrumentId, 'DE000TEST001', 'Filter-Zertifikat'], [$otherId, 'DE000TEST002', 'Other-Zertifikat']] as [$id, $isin, $name]) {
            DB::table('linked_securities')->insert([
                'underlying_instrument_id' => $id, 'type' => 'discount_certificate', 'isin' => $isin,
                'name' => $name, 'currency' => 'EUR', 'exchange' => 'Test', 'mic_code' => 'XSTU',
                'german_tradeability_verified_at' => now(), 'source_provider' => 'test', 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->actingAs($user)->get(route('certificates.index', ['underlying' => $instrumentId]))
            ->assertOk()->assertSee('Filter-Zertifikat')->assertDontSee('Other-Zertifikat');
    }

    public function test_certificates_with_a_cap_more_than_five_percent_below_the_stock_price_are_hidden(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'is_beta_tester' => true]);
        $instrumentId = DB::table('instruments')->insertGetId([
            'type' => 'stock', 'symbol' => 'CAP', 'name' => 'Cap AG', 'currency' => 'EUR',
            'is_active' => true, 'is_tradeable' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('price_bars')->insert([
            'instrument_id' => $instrumentId, 'interval' => '1d', 'bar_time' => now(),
            'open' => 100, 'high' => 101, 'low' => 99, 'close' => 100,
            'source' => 'test', 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([
            ['DE000CAP0094', 'Cap 94 versteckt', 94],
            ['DE000CAP0095', 'Cap 95 sichtbar', 95],
            ['DE000CAPNULL', 'Ohne Cap sichtbar', null],
        ] as [$isin, $name, $cap]) {
            DB::table('linked_securities')->insert([
                'underlying_instrument_id' => $instrumentId, 'type' => 'discount_certificate',
                'isin' => $isin, 'name' => $name, 'cap' => $cap, 'currency' => 'EUR',
                'exchange' => 'Test', 'mic_code' => 'XSTU', 'german_tradeability_verified_at' => now(),
                'source_provider' => 'test', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->actingAs($user)->get(route('certificates.index', ['underlying' => $instrumentId]))
            ->assertOk()
            ->assertDontSee('Cap 94 versteckt')
            ->assertSee('Cap 95 sichtbar')
            ->assertSee('Ohne Cap sichtbar');
    }
}
