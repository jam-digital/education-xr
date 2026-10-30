<?php

namespace App\Services;

use Aws\S3\S3Client;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Завантаження важких білдів (APK до ~1 ГБ, OBB до 5–10 ГБ) частинами.
 * R2/S3: браузер шле частини напряму в бакет за presigned URL, сервер лише підписує й збирає.
 * Локальний диск (розробка, сервер без R2): частини приходять на сервер і дописуються у файл.
 */
class BuildUploads
{
    public function diskName(): string
    {
        return config('vrosvita.builds_disk');
    }

    public function disk(): FilesystemAdapter
    {
        return Storage::disk($this->diskName());
    }

    public function isS3(): bool
    {
        return config("filesystems.disks.{$this->diskName()}.driver") === 's3';
    }

    public function key(string $appKey, string $kind, string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (! in_array($ext, $kind === 'apk' ? ['apk'] : ['obb'], true)) {
            throw new InvalidArgumentException($kind === 'apk' ? 'Потрібен файл .apk' : 'Потрібен файл .obb');
        }
        $base = Str::slug(pathinfo($filename, PATHINFO_FILENAME)) ?: $kind;

        return "builds/{$appKey}/".now()->format('Ymd-His')."-{$base}.{$ext}";
    }

    /** @return array{mode: string, key: string, upload_id: string, part_size: int} */
    public function init(string $key): array
    {
        $partSize = max(5 * 1024 * 1024, config('vrosvita.upload_part_size'));
        if ($this->isS3()) {
            $res = $this->client()->createMultipartUpload(['Bucket' => $this->bucket(), 'Key' => $key, 'ContentType' => 'application/octet-stream']);

            return ['mode' => 's3', 'key' => $key, 'upload_id' => $res['UploadId'], 'part_size' => $partSize];
        }
        $id = (string) Str::uuid();
        $tmp = $this->tmpPath($id);
        @mkdir(dirname($tmp), 0775, true);
        touch($tmp);

        // частина йде через PHP, тому має бути меншою за post_max_size (типово 8 МБ)
        return ['mode' => 'local', 'key' => $key, 'upload_id' => $id, 'part_size' => (int) config('vrosvita.upload_local_part_size')];
    }

    public function partUrl(string $key, string $uploadId, int $part): string
    {
        $cmd = $this->client()->getCommand('UploadPart', ['Bucket' => $this->bucket(), 'Key' => $key, 'UploadId' => $uploadId, 'PartNumber' => $part]);

        return (string) $this->client()->createPresignedRequest($cmd, '+2 hours')->getUri();
    }

    /** Локальний режим: частина дописується в кінець тимчасового файлу; offset захищає від дублю при повторі */
    public function appendLocal(string $uploadId, int $offset, $stream): int
    {
        $tmp = $this->tmpPath($uploadId);
        if (! is_file($tmp)) {
            throw new InvalidArgumentException('Завантаження не знайдено.');
        }
        clearstatcache(true, $tmp);
        $size = filesize($tmp);
        if ($offset < $size) {
            // частина вже прийнята раніше (повтор після обриву) — обрізаємо до offset і пишемо знову
            $h = fopen($tmp, 'r+');
            ftruncate($h, $offset);
            fclose($h);
        } elseif ($offset > $size) {
            throw new InvalidArgumentException("Пропущено частину: на сервері {$size} байт, прийшов offset {$offset}.");
        }
        $out = fopen($tmp, 'ab');
        stream_copy_to_stream($stream, $out);
        fclose($out);
        clearstatcache(true, $tmp);

        return filesize($tmp);
    }

    /** @param  array<int, array{PartNumber: int, ETag: string}>  $parts */
    public function complete(string $key, string $uploadId, array $parts = []): int
    {
        if ($this->isS3()) {
            usort($parts, fn ($a, $b) => $a['PartNumber'] <=> $b['PartNumber']);
            $this->client()->completeMultipartUpload([
                'Bucket' => $this->bucket(), 'Key' => $key, 'UploadId' => $uploadId,
                'MultipartUpload' => ['Parts' => array_map(fn ($p) => ['PartNumber' => (int) $p['PartNumber'], 'ETag' => $p['ETag']], $parts)],
            ]);

            return (int) $this->disk()->size($key);
        }
        $tmp = $this->tmpPath($uploadId);
        $dest = $this->disk()->path($key);
        @mkdir(dirname($dest), 0775, true);
        rename($tmp, $dest);

        return filesize($dest);
    }

    public function abort(string $key, string $uploadId): void
    {
        if ($this->isS3()) {
            $this->client()->abortMultipartUpload(['Bucket' => $this->bucket(), 'Key' => $key, 'UploadId' => $uploadId]);

            return;
        }
        @unlink($this->tmpPath($uploadId));
    }

    /** SHA-256 потоком, без завантаження файлу в пам’ять */
    public function sha256(string $key): string
    {
        $stream = $this->disk()->readStream($key);
        $ctx = hash_init('sha256');
        hash_update_stream($ctx, $stream);
        fclose($stream);

        return hash_final($ctx);
    }

    private function tmpPath(string $uploadId): string
    {
        if (! Str::isUuid($uploadId)) {
            throw new InvalidArgumentException('Невірний ідентифікатор завантаження.');
        }

        return storage_path("app/uploads-tmp/{$uploadId}.part");
    }

    private function client(): S3Client
    {
        return $this->disk()->getClient();
    }

    private function bucket(): string
    {
        return config("filesystems.disks.{$this->diskName()}.bucket");
    }
}
