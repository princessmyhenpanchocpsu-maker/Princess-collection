<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->admin = User::factory()->admin()->create();
        $this->product = Product::factory()->create();
    }

    private function upload(array $files, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->admin)->post(
            "/api/v1/admin/products/{$this->product->slug}/images",
            ['images' => $files],
            ['Accept' => 'application/json'],
        );
    }

    public function test_an_admin_can_upload_images_which_are_stored_under_generated_names(): void
    {
        $this->upload([$this->pngUpload('../../evil name.php.png'), $this->pngUpload('second.png')])
            ->assertCreated()
            ->assertJsonCount(2, 'data');

        $images = $this->product->images()->get();
        $this->assertCount(2, $images);
        $this->assertSame([1, 2], $images->pluck('sort_order')->all());

        foreach ($images as $image) {
            $this->assertMatchesRegularExpression("#^products/{$this->product->id}/[0-9a-f-]{36}\.png$#", $image->image_path);
            Storage::disk('public')->assertExists($image->image_path);
        }
    }

    public function test_uploads_that_are_not_real_images_are_rejected(): void
    {
        $cases = [
            'script disguised as a jpeg' => UploadedFile::fake()->createWithContent('shell.jpg', '<?php echo "owned"; ?>'),
            'svg' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            'pdf' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
            'too small' => $this->pngUpload('tiny.png', 50, 50),
            'too large' => UploadedFile::fake()->create('huge.png', 5000, 'image/png'),
        ];

        foreach ($cases as $label => $file) {
            $this->upload([$file])->assertUnprocessable()->assertJsonValidationErrors('images.0');
        }

        $this->assertSame(0, ProductImage::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_product_cannot_exceed_the_image_limit(): void
    {
        config(['store.images.max_per_product' => 2]);

        $this->upload([$this->pngUpload(), $this->pngUpload()])->assertCreated();
        $this->upload([$this->pngUpload()])->assertUnprocessable()->assertJsonValidationErrors('images');

        $this->assertSame(2, ProductImage::count());
    }

    public function test_an_admin_can_reorder_and_delete_images(): void
    {
        $this->upload([$this->pngUpload(), $this->pngUpload()])->assertCreated();
        [$first, $second] = $this->product->images()->get()->all();

        $this->actingAs($this->admin)
            ->patchJson("/api/v1/admin/products/{$this->product->slug}/images/reorder", ['order' => [$second->id, $first->id]])
            ->assertOk()
            ->assertJsonPath('data.0.id', $second->id);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/admin/products/{$this->product->slug}/images/{$first->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('product_images', ['id' => $first->id]);
        Storage::disk('public')->assertMissing($first->image_path);
        Storage::disk('public')->assertExists($second->image_path);
    }

    public function test_an_image_cannot_be_deleted_through_another_product(): void
    {
        $this->upload([$this->pngUpload()])->assertCreated();
        $image = $this->product->images()->firstOrFail();
        $other = Product::factory()->create();

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/admin/products/{$other->slug}/images/{$image->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('product_images', ['id' => $image->id]);
    }

    public function test_customers_and_guests_cannot_manage_images(): void
    {
        $this->post("/api/v1/admin/products/{$this->product->slug}/images", ['images' => [$this->pngUpload()]], ['Accept' => 'application/json'])
            ->assertUnauthorized();

        $this->upload([$this->pngUpload()], User::factory()->create())->assertForbidden();

        $this->assertSame(0, ProductImage::count());
    }
}
