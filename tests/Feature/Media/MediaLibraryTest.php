<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Media\Application\Actions\UploadMediaAction;
use App\Modules\Media\Application\Dto\UploadMediaDto;
use App\Modules\Media\Infrastructure\Persistence\Models\Media;
use App\Modules\Media\Infrastructure\Persistence\Models\MediaUsage;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_library_is_only_available_to_admins(): void
    {
        $this->get('/dashboard/media')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/dashboard/media')->assertNotFound();
        $this->postJson('/dashboard/media', [])->assertNotFound();
        $this->actingAs(User::factory()->admin()->create())->get('/dashboard/media')->assertOk()->assertSee('کتابخانه');
    }

    public function test_upload_search_edit_and_delete_keep_files_and_rows_consistent(): void
    {
        $this->withoutExceptionHandling();
        $this->actingAs(User::factory()->admin()->create());
        $response = $this->postJson('/dashboard/media', [
            'file' => UploadedFile::fake()->image('logo.png', 800, 400),
            'alt' => 'لوگوی منبع',
        ])->assertCreated()->assertJsonPath('data.width', 800)->assertJsonPath('data.dimensions', '800×400');
        $id = $response->json('data.id');
        $media = Media::findOrFail($id);
        self::assertNotNull($media->derivatives);
        Storage::disk('public')->assertExists($media->allPaths());
        $this->getJson('/dashboard/media/table?q=logo')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        $this->putJson('/dashboard/media/'.$id, ['alt' => 'لوگوی تازه', 'title' => 'Test logo'])
            ->assertOk()->assertJsonPath('data.alt', 'لوگوی تازه');
        $this->assertDatabaseHas('media', ['id' => $id, 'alt' => 'لوگوی تازه']);
        $this->deleteJson('/dashboard/media/'.$id)->assertOk();
        Storage::disk('public')->assertMissing($media->allPaths());
        $this->assertDatabaseMissing('media', ['id' => $id]);
        $this->withExceptionHandling();
        $this->putJson('/dashboard/media/'.$id, ['alt' => 'Missing'])->assertNotFound();
    }

    public function test_svg_is_sanitized_before_becoming_public(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" onload="alert(1)"><script>alert(1)</script><rect width="20" height="20"/></svg>';
        $response = $this->actingAs(User::factory()->admin()->create())->postJson('/dashboard/media', [
            'file' => UploadedFile::fake()->createWithContent('logo.svg', $svg), 'alt' => 'نشان',
        ])->assertCreated();
        $media = Media::findOrFail($response->json('data.id'));
        $saved = Storage::disk('public')->get($media->path);
        self::assertStringNotContainsString('script', $saved);
        self::assertStringNotContainsString('onload', $saved);
        self::assertStringContainsString('<rect', $saved);
        self::assertNull($media->derivatives);
    }

    public function test_invalid_files_and_missing_alt_leave_no_files(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->postJson('/dashboard/media', ['file' => UploadedFile::fake()->image('image.png')])->assertUnprocessable()->assertJsonValidationErrors('alt');
        $this->postJson('/dashboard/media', ['file' => UploadedFile::fake()->createWithContent('bad.png', '<?php echo "no";'), 'alt' => 'بد'])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson('/dashboard/media', ['file' => UploadedFile::fake()->image('large.png')->size(5121), 'alt' => 'بزرگ'])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        self::assertSame([], Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('media', 0);
    }

    public function test_a_used_image_cannot_be_deleted(): void
    {
        $owner = User::factory()->admin()->create();
        $media = Media::factory()->create();
        MediaUsage::create(['media_id' => $media->id, 'usable_type' => $owner->getMorphClass(), 'usable_id' => $owner->id, 'field' => 'logo']);
        Storage::disk('public')->put($media->path, 'kept');
        $this->actingAs($owner)->deleteJson('/dashboard/media/'.$media->id)->assertUnprocessable();
        $this->assertDatabaseHas('media', ['id' => $media->id]);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_failed_disk_write_does_not_create_a_media_row(): void
    {
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andReturnFalse();
        $factory = Mockery::mock(Factory::class);
        $factory->shouldReceive('disk')->with('public')->andReturn($disk);
        try {
            (new UploadMediaAction($factory))->handle(new UploadMediaDto(UploadedFile::fake()->image('logo.png'), 'لوگو'));
            self::fail('Neveshtan e namovafagh bayad exception bedahad.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('zakhire', $exception->getMessage());
        }
        $this->assertDatabaseCount('media', 0);
    }

    public function test_failed_database_insert_cleans_up_the_uploaded_files(): void
    {
        try {
            app(UploadMediaAction::class)->handle(new UploadMediaDto(UploadedFile::fake()->image('logo.png', 800, 400), 'لوگو', uploadedBy: 999999));
            self::fail('Foreign key e karbar bayad rad shavad.');
        } catch (QueryException) {
            self::assertSame([], Storage::disk('public')->allFiles());
        }
        $this->assertDatabaseCount('media', 0);
    }

    public function test_unknown_sort_and_filter_values_return_validation_errors(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->getJson('/dashboard/media/table?sort=secret')->assertUnprocessable()->assertJsonValidationErrors('sort');
        $this->getJson('/dashboard/media/table?filters[mime]=application/pdf')->assertUnprocessable()->assertJsonValidationErrors('filters.mime');
    }
}
