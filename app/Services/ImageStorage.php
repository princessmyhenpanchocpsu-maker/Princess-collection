<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImageStorage
{
    // Extension is chosen from the detected content type, never from the uploaded filename.
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Validation rules for a single uploaded image.
     *
     * @return list<string>
     */
    public static function rules(): array
    {
        return [
            'file',
            'image',
            'mimes:'.implode(',', config('store.images.mimes')),
            'max:'.config('store.images.max_kilobytes'),
            'dimensions:min_width=200,min_height=200,max_width=6000,max_height=6000',
        ];
    }

    /**
     * Store an image on the public disk under a random name and return its path.
     */
    public function store(UploadedFile $file, string $directory): string
    {
        $extension = self::EXTENSIONS[$file->getMimeType()] ?? null;

        if ($extension === null || @getimagesize($file->getRealPath()) === false) {
            throw ValidationException::withMessages(['image' => 'The file is not a valid JPEG, PNG or WebP image.']);
        }

        $path = trim($directory, '/').'/'.Str::uuid().'.'.$extension;

        Storage::disk('public')->putFileAs(dirname($path), $file, basename($path));

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
