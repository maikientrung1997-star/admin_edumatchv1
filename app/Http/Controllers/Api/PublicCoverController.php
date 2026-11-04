<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicCoverController extends Controller
{
    public function __invoke(Request $request, string $filename)
    {
        abort_unless(
            preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\.(?:png|jpe?g|webp|gif)\z/iD', $filename),
            404
        );

        $disk = Storage::disk('public');
        $relative = 'covers/'.$filename;
        abort_unless($disk->exists($relative), 404);

        $path = $disk->path($relative);
        $image = @getimagesize($path);
        abort_unless($image && in_array($image['mime'], ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true), 404);

        $response = response()->file($path, [
            'Content-Type' => $image['mime'],
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=86400',
        ]);

        $response->setAutoEtag();
        $response->isNotModified($request);

        return $response;
    }
}
