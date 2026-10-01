<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfilePhotoController extends Controller
{
    public function __invoke(Request $request, string $filename): BinaryFileResponse
    {
        $storedFilename = basename((string) $request->user()->profile_photo_path);
        abort_unless($storedFilename !== '' && hash_equals($storedFilename, $filename), 404);

        $path = rtrim((string) config('uploads.profile_photos_path'), '\\/').DIRECTORY_SEPARATOR.$storedFilename;
        abort_unless(is_file($path) && is_readable($path), 404);

        $response = response()->file($path, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setPrivate();
        $response->setMaxAge(86400);
        $response->setImmutable();

        return $response;
    }
}
