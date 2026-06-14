<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class PublicPhoto
{
    public const FIELDS = ['photo', 'image', 'avatar', 'file'];

    public static function fromRequest(Request $request): ?UploadedFile
    {
        foreach (self::FIELDS as $field) {
            if ($request->hasFile($field)) {
                return $request->file($field);
            }
        }

        return null;
    }

    public static function store(UploadedFile $file, string $directory): string
    {
        $directory = trim($directory, '/');
        $publicDirectory = public_path($directory);

        if (!is_dir($publicDirectory)) {
            mkdir($publicDirectory, 0755, true);
        }

        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move($publicDirectory, $filename);

        return $directory . '/' . $filename;
    }

    public static function delete(?string $path): void
    {
        if (!$path || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        $publicPath = public_path($path);

        if (file_exists($publicPath)) {
            unlink($publicPath);
        }
    }

    public static function url(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset($path);
    }
}
