<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_switch_the_login_page_to_khmer(): void
    {
        $this->post(route('locale.switch', 'km'))->assertRedirect();

        $this->get(route('login'))->assertOk()->assertSee('សូមស្វាគមន៍ការត្រឡប់មកវិញ')->assertSee('lang="km"', false);
    }

    public function test_signed_in_users_keep_their_language(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)->post(route('locale.switch', 'km'))->assertRedirect();

        $this->assertSame('km', $user->fresh()->locale);
        $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk()->assertSee('ផ្ទាំងគ្រប់គ្រង');
    }

    public function test_unsupported_languages_are_rejected(): void
    {
        $this->post(route('locale.switch', 'fr'))->assertNotFound();
    }

    public function test_validation_messages_are_translated(): void
    {
        $user = User::factory()->create(['locale' => 'km']);

        $this->actingAs($user)->from(route('borrowers.create'))->post(route('borrowers.store'), [])
            ->assertSessionHasErrors(['first_name' => 'សូមបំពេញ នាមខ្លួន។']);
    }

    public function test_every_interface_string_has_a_khmer_translation(): void
    {
        $khmer = json_decode(File::get(lang_path('km.json')), true);
        $missing = [];

        $files = collect(File::allFiles(resource_path('views')))->merge(File::allFiles(app_path()));

        foreach ($files as $file) {
            preg_match_all("/__\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", $file->getContents(), $matches);

            foreach ($matches[1] as $key) {
                $key = str_replace("\\'", "'", $key);

                if (! str_contains($key, '.') || str_contains($key, ' ')) {
                    if (! array_key_exists($key, $khmer)) {
                        $missing[] = $key;
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)), 'Add these strings to lang/km.json');
    }
}
