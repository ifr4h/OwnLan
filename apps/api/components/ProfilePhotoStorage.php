<?php

declare(strict_types=1);

namespace app\components;

use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

/**
 * Secure profile photo storage — org-isolated, served via authenticated/public controller.
 */
final class ProfilePhotoStorage
{
    private const MAX_BYTES = 5 * 1024 * 1024;

    /**
     * @return array{path: string}
     */
    public function store(int $organisationId, UploadedFile $file): array
    {
        if ($file->hasError) {
            throw new BadRequestHttpException('The photo did not upload correctly.');
        }
        if ($file->size > self::MAX_BYTES) {
            throw new BadRequestHttpException('Photo must be 5 MB or smaller.');
        }

        $ext = strtolower((string) $file->extension);
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed, true)) {
            throw new BadRequestHttpException('Photo must be a JPG, PNG or WebP image.');
        }

        $dir = $this->directoryFor($organisationId);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new BadRequestHttpException('Could not store photo.');
        }

        $filename = 'profile.' . $ext;
        $fullPath = $dir . DIRECTORY_SEPARATOR . $filename;
        if (!$file->saveAs($fullPath)) {
            throw new BadRequestHttpException('Could not store photo.');
        }

        return ['path' => 'profile-photos/' . $organisationId . '/' . $filename];
    }

    /**
     * @return array{path: string}
     */
    public function storeCover(int $organisationId, UploadedFile $file): array
    {
        if ($file->hasError) {
            throw new BadRequestHttpException('The cover image did not upload correctly.');
        }
        if ($file->size > self::MAX_BYTES) {
            throw new BadRequestHttpException('Cover image must be 5 MB or smaller.');
        }

        $ext = strtolower((string) $file->extension);
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed, true)) {
            throw new BadRequestHttpException('Cover image must be a JPG, PNG or WebP image.');
        }

        $dir = $this->directoryFor($organisationId);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new BadRequestHttpException('Could not store cover image.');
        }

        $filename = 'cover.' . $ext;
        $fullPath = $dir . DIRECTORY_SEPARATOR . $filename;
        if (!$file->saveAs($fullPath)) {
            throw new BadRequestHttpException('Could not store cover image.');
        }

        return ['path' => 'profile-photos/' . $organisationId . '/' . $filename];
    }

    public function absolutePath(string $relativePath): string
    {
        return Yii::getAlias('@runtime/storage') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }

    public function delete(string $relativePath): void
    {
        $full = $this->absolutePath($relativePath);
        if (is_file($full)) {
            unlink($full);
        }
    }

    public function read(string $relativePath): string
    {
        $full = $this->absolutePath($relativePath);
        if (!is_file($full)) {
            throw new NotFoundHttpException('Photo not found.');
        }
        $contents = file_get_contents($full);
        if ($contents === false) {
            throw new NotFoundHttpException('Photo not found.');
        }

        return $contents;
    }

    public function mimeType(string $relativePath): string
    {
        $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

        return match ($ext) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    private function directoryFor(int $organisationId): string
    {
        return Yii::getAlias('@runtime/storage/profile-photos/' . $organisationId);
    }
}
