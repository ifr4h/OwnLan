<?php

declare(strict_types=1);

namespace app\components;

use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

/**
 * Secure receipt storage — org-isolated, non-public paths.
 */
final class ReceiptStorage
{
    private const MAX_BYTES = 10 * 1024 * 1024;

    /**
     * @return array{path: string, original_name: string}
     */
    public function store(int $organisationId, int $expenseId, UploadedFile $file): array
    {
        if ($file->hasError) {
            throw new BadRequestHttpException('The receipt did not upload correctly.');
        }
        if ($file->size > self::MAX_BYTES) {
            throw new BadRequestHttpException('Receipt must be 10 MB or smaller.');
        }

        $ext = strtolower((string) $file->extension);
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'heic'];
        if (!in_array($ext, $allowed, true)) {
            throw new BadRequestHttpException('Receipt must be an image or PDF.');
        }

        $dir = $this->directoryFor($organisationId, $expenseId);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new BadRequestHttpException('Could not store receipt.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $fullPath = $dir . DIRECTORY_SEPARATOR . $filename;
        if (!$file->saveAs($fullPath)) {
            throw new BadRequestHttpException('Could not store receipt.');
        }

        $relative = 'receipts/' . $organisationId . '/' . $expenseId . '/' . $filename;

        return [
            'path' => $relative,
            'original_name' => (string) $file->name,
        ];
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
            throw new NotFoundHttpException('Receipt not found.');
        }
        $contents = file_get_contents($full);
        if ($contents === false) {
            throw new NotFoundHttpException('Receipt not found.');
        }

        return $contents;
    }

    public function mimeType(string $relativePath): string
    {
        $full = $this->absolutePath($relativePath);
        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'heic' => 'image/heic',
            default => 'image/jpeg',
        };
    }

    private function directoryFor(int $organisationId, int $expenseId): string
    {
        return Yii::getAlias('@runtime/storage/receipts/' . $organisationId . '/' . $expenseId);
    }
}
