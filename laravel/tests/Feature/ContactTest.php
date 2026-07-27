<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_page_can_be_rendered_in_both_languages(): void
    {
        $this->actingAs(User::factory()->create());

        $this->withSession(['locale' => 'de'])->get('/kontakt')
            ->assertOk()
            ->assertSee('Wir freuen uns auf deine Nachricht.');

        $this->withSession(['locale' => 'en'])->get('/kontakt')
            ->assertOk()
            ->assertSee('We look forward to hearing from you.');
    }

    public function test_contact_message_can_be_submitted(): void
    {
        $response = $this->actingAs(User::factory()->create())->post('/kontakt', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'subject' => 'Frage zu AktienKI',
            'message' => 'Dies ist eine ausreichend lange Testnachricht.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('contact_success');
        $this->assertDatabaseHas('contact_messages', [
            'email' => 'test@example.com',
            'subject' => 'Frage zu AktienKI',
            'status' => 'new',
        ]);
    }

    public function test_contact_form_validates_required_fields(): void
    {
        $this->actingAs(User::factory()->create())->post('/kontakt', [])->assertSessionHasErrors([
            'name', 'email', 'subject', 'message',
        ]);
    }

    public function test_guests_cannot_view_or_submit_the_contact_form(): void
    {
        $this->get('/kontakt')->assertRedirect(route('login'));
        $this->post('/kontakt', [])->assertRedirect(route('login'));
    }
}
