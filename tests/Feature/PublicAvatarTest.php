<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicAvatarTest extends TestCase
{
    public function test_public_avatar_is_an_image_with_cors_and_can_be_revalidated(): void
    {
        config(['cache.default' => 'array']);
        Storage::fake('public');
        $file = UploadedFile::fake()->image('photo.png');
        Storage::disk('public')->put('avatars/photo.png', file_get_contents($file->getPathname()));
        $headers = ['Origin' => 'http://localhost:5173'];
        $response = $this->get('/api/media/avatars/photo.png', $headers)
            ->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/api/media/avatars/photo.png', $headers + ['If-None-Match' => $response->headers->get('ETag')])
            ->assertStatus(304)->assertHeader('Access-Control-Allow-Origin', '*');
    }

    public function test_avatar_route_does_not_serve_missing_non_image_or_private_files(): void
    {
        config(['cache.default' => 'array']);
        Storage::fake('public');
        Storage::disk('public')->put('avatars/script.png', '<script>private text</script>');
        Storage::disk('public')->put('verification_documents/proof.png', 'private proof');
        foreach (['missing.png', 'script.png', 'script.php', '..%2Fverification_documents%2Fproof.png'] as $filename) {
            $this->get('/api/media/avatars/'.$filename, ['Accept' => 'application/json'])->assertNotFound();
        }
    }
}
