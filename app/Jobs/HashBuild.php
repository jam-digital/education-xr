<?php

namespace App\Jobs;

use App\Models\LessonBuild;
use App\Services\BuildUploads;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Рахує SHA-256 і точний розмір APK/OBB після завантаження — окуляри звіряють їх перед установкою */
class HashBuild implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 2;

    public function __construct(public LessonBuild $build) {}

    public function handle(BuildUploads $uploads): void
    {
        $data = [];
        foreach (['apk', 'obb'] as $kind) {
            $path = $this->build->{"{$kind}_storage_path"};
            if ($path) {
                $data["{$kind}_sha256_hash"] = $uploads->sha256($path);
                $data["{$kind}_size_bytes"] = $uploads->disk()->size($path);
            }
        }
        $this->build->update($data + ['hash_status' => 'ready']);

        // якщо цей білд уже опубліковано — оновити й картку уроку
        $lesson = $this->build->lesson;
        if ($this->build->published_at && $lesson->current_version_code === $this->build->version_code) {
            $lesson->update(array_intersect_key($data, array_flip(['apk_sha256_hash', 'apk_size_bytes', 'obb_sha256_hash', 'obb_size_bytes'])));
        }
    }

    public function failed(Throwable $e): void
    {
        $this->build->update(['hash_status' => 'failed']);
    }
}
