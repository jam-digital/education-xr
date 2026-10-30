<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VrLesson;
use App\Services\BuildUploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** JSON-ендпоінти для менеджера білдів (resources/views/filament/forms/build-file-upload.blade.php) */
class BuildUploadController extends Controller
{
    public function __construct(private BuildUploads $uploads) {}

    public function init(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lesson_id' => 'required|exists:vr_lessons,id',
            'kind' => 'required|in:apk,obb',
            'filename' => 'required|string|max:255',
            'size' => 'required|integer|min:1|max:'.(20 * 1024 ** 3),
        ]);
        $lesson = VrLesson::findOrFail($data['lesson_id']);

        return $this->guard(fn () => $this->uploads->init($this->uploads->key($lesson->app_key, $data['kind'], $data['filename'])));
    }

    public function partUrl(Request $request): JsonResponse
    {
        $data = $request->validate(['key' => 'required|string|starts_with:builds/', 'upload_id' => 'required|string', 'part' => 'required|integer|min:1|max:10000']);

        return $this->guard(fn () => ['url' => $this->uploads->partUrl($data['key'], $data['upload_id'], $data['part'])]);
    }

    public function chunk(Request $request): JsonResponse
    {
        $data = $request->validate(['upload_id' => 'required|uuid', 'offset' => 'required|integer|min:0']);

        return $this->guard(fn () => ['received' => $this->uploads->appendLocal($data['upload_id'], (int) $data['offset'], $request->getContent(true))]);
    }

    public function complete(Request $request): JsonResponse
    {
        $data = $request->validate(['key' => 'required|string|starts_with:builds/', 'upload_id' => 'required|string', 'parts' => 'array']);

        return $this->guard(fn () => ['path' => $data['key'], 'size' => $this->uploads->complete($data['key'], $data['upload_id'], $data['parts'] ?? [])]);
    }

    public function abort(Request $request): JsonResponse
    {
        $data = $request->validate(['key' => 'required|string|starts_with:builds/', 'upload_id' => 'required|string']);

        return $this->guard(fn () => tap(['ok' => true], fn () => $this->uploads->abort($data['key'], $data['upload_id'])));
    }

    private function guard(callable $fn): JsonResponse
    {
        try {
            return response()->json($fn());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
