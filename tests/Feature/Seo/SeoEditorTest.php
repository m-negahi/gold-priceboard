<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Modules\Access\Application\Actions\ManageAccessAction;
use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SeoEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_edit_public_metadata_and_blank_values_restore_defaults(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/dashboard/seo')->assertOk();
        $this->put('/dashboard/seo/home', ['title' => 'عنوان ویژه', 'description' => 'توضیح تازه', 'robots' => 'noindex,follow'])->assertRedirect();
        $this->get('/')->assertOk()->assertSee('عنوان ویژه')->assertSee('noindex,follow', false);
        $this->get('/sitemaps/pages.xml')->assertDontSee('<loc>', false);
        $this->put('/dashboard/seo/home', ['title' => '', 'description' => '', 'robots' => ''])->assertRedirect();
        $this->get('/')->assertOk()->assertSee(config('seo.home.title'));
    }

    public function test_customer_cannot_edit_seo_and_unsafe_urls_are_rejected(): void
    {
        $this->actingAs(User::factory()->create())->put('/dashboard/seo/home', ['title' => 'x'])->assertNotFound();
        $this->actingAs(User::factory()->admin()->create())->put('/dashboard/seo/home', ['canonical_url' => 'javascript:alert(1)', 'og_image' => 'data:image/svg+xml,evil'])->assertSessionHasErrors(['canonical_url', 'og_image']);
        $this->put('/dashboard/seo/nonexistent', [])->assertNotFound();
        $this->assertDatabaseCount('seo_meta', 0);
    }

    public function test_seo_reader_can_view_but_cannot_save_metadata(): void
    {
        $owner = User::factory()->admin()->create();
        $reader = User::factory()->admin()->create();
        $action = app(ManageAccessAction::class);
        $role = $action->saveRole($owner, null, ['name' => 'seo-reader', 'permissions' => ['seo.view']]);
        $action->assign($owner, $reader->id, [$role->id], false);
        $this->actingAs($reader)->get('/dashboard/seo')->assertOk()->assertDontSee('ذخیره تنظیمات');
        $this->put('/dashboard/seo/home', ['title' => 'تغییر غیرمجاز'])->assertForbidden();
        $this->assertDatabaseCount('seo_meta', 0);
    }

    public function test_metadata_and_json_ld_escape_script_closing_tags(): void
    {
        $payload = '</script><script>alert("xss")</script>';
        $this->actingAs(User::factory()->admin()->create())->put('/dashboard/seo/home', ['title' => $payload, 'description' => $payload])->assertRedirect();
        $this->get('/')->assertOk()->assertSee(e($payload), false)->assertDontSee($payload, false)
            ->assertSee('\\u003C/script\\u003E\\u003Cscript\\u003E', false);
    }
}
