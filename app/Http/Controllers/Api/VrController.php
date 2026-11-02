<?php

namespace App\Http\Controllers\Api;

use App\Enums\DeviceStatus;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\VrLesson;
use App\Services\VrApiService;
use App\Services\VrSigner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/** REST API клієнта «VR-освіта» в шкільних окулярах: /api/v1/vr (ТЗ розділ 7) */
class VrController extends Controller
{
    public function __construct(private VrApiService $vr) {}

    private const TELEMETRY = [
        'hardware_serial' => 'required|string|max:120',
        'model' => 'nullable|string|max:80',
        'os_version' => 'nullable|string|max:80',
        'client_version' => 'nullable|string|max:40',
        'battery_level_percent' => 'nullable|integer|min:0|max:100',
        'free_storage_bytes' => 'nullable|integer|min:0',
        'monotonic_clock_ms' => 'nullable|integer|min:0',
        'clock_tamper' => 'nullable|boolean',
        'installed_lessons' => 'nullable|array',
    ];

    /** Автоматичний вхід за серійним номером; новий шолом отримує 6-значний код прив’язки */
    public function auth(Request $request): JsonResponse
    {
        $data = $request->validate(self::TELEMETRY);
        $device = $this->vr->findBySerial($data['hardware_serial']);

        if ($device?->status === DeviceStatus::Blocked) {
            return $this->fail('device_blocked', 'Окуляри заблоковано. Зверніться до методиста VR-освіти.', 403);
        }
        if ($device?->status === DeviceStatus::Replaced) {
            return $this->fail('device_replaced', 'Ці окуляри списані після заміни.', 403);
        }
        if (! $device || ! $device->user_id || $device->status === DeviceStatus::PendingPairing) {
            $device = $this->vr->pendingDevice($data['hardware_serial'], $data);

            return response()->json([
                'status' => 'pairing_required',
                'hardware_serial' => $device->hardware_serial,
                'pairing_code' => $device->pairing_code,
                'pairing_expires_at' => $device->pairing_expires_at->toIso8601String(),
                'message' => 'Увійдіть email і паролем закладу або введіть код прив’язки від методиста.',
            ], 202);
        }

        $device->fill($this->vr->telemetry($data))->save();

        return $this->authorized($device);
    }

    /** Вхід закладу email + паролем: окуляри закріплюються за акаунтом школи */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(self::TELEMETRY + ['email' => 'required|email', 'password' => 'required|string']);

        try {
            $device = $this->vr->loginSchool($data['hardware_serial'], $data['email'], $data['password'], $data);
        } catch (InvalidArgumentException $e) {
            return $this->fail('login_failed', $e->getMessage(), 422);
        }

        return $this->authorized($device);
    }

    /** Прив’язка за 6-значним кодом з адмінки («Згенерувати PIN прив’язки») */
    public function pair(Request $request): JsonResponse
    {
        $data = $request->validate(self::TELEMETRY + ['pairing_code' => 'required|digits:6']);

        try {
            $device = $this->vr->pairByCode($data['hardware_serial'], $data['pairing_code'], $data);
        } catch (InvalidArgumentException $e) {
            return $this->fail('pairing_failed', $e->getMessage(), 422);
        }

        return $this->authorized($device);
    }

    /** Телеметрія без перевидачі токена (заряд, пам’ять, встановлені уроки) */
    public function heartbeat(Request $request): JsonResponse
    {
        $device = $this->device($request);
        $data = $request->validate(collect(self::TELEMETRY)->except('hardware_serial')->all());
        $device->fill($this->vr->telemetry($data))->save();

        return response()->json(['ok' => true, 'server_time' => now()->toIso8601String()]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->device($request)->forceFill(['api_token_hash' => null])->save();

        return response()->json(['ok' => true]);
    }

    public function library(Request $request): JsonResponse
    {
        return response()->json($this->vr->library($this->device($request)));
    }

    public function download(Request $request, VrLesson $lesson): JsonResponse
    {
        try {
            return response()->json($this->vr->downloads($this->device($request), $lesson));
        } catch (InvalidArgumentException $e) {
            return $this->fail('no_access', $e->getMessage(), 403);
        }
    }

    /** Файл білда з локального диска за підписаним посиланням; підтримує Range (дозавантаження) */
    public function file(Request $request): Response
    {
        $path = (string) base64_decode((string) $request->query('path'), true);
        $device = Device::find($request->query('device'));
        if (! $path || str_contains($path, '..') || ! $device || $device->status !== DeviceStatus::Active) {
            abort(404);
        }
        $disk = Storage::disk(config('vrosvita.builds_disk'));
        abort_unless($disk->exists($path), 404);

        if (config('vrosvita.vr.x_accel')) {
            return response('', 200, [
                'X-Accel-Redirect' => '/_builds/'.ltrim($path, '/'),
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="'.basename($path).'"',
            ]);
        }

        return response()->file($disk->path($path), ['Content-Type' => 'application/octet-stream']);
    }

    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lesson' => 'required|string',
            'resume_session_uuid' => 'nullable|uuid',
        ]);
        $device = $this->device($request);
        $lesson = VrLesson::where('is_active', true)
            ->where(fn ($query) => $query->where('app_key', $data['lesson'])->orWhere('android_package_name', $data['lesson'])
                ->when(preg_match('/^[0-9a-f-]{36}$/i', $data['lesson']), fn ($query) => $query->orWhere('id', $data['lesson'])))
            ->first();
        if (! $lesson) {
            return $this->fail('unknown_lesson', 'Урок не знайдено.', 404);
        }

        try {
            return response()->json($this->vr->startSession($device, $lesson, $data['resume_session_uuid'] ?? null));
        } catch (InvalidArgumentException $e) {
            return $this->fail('no_tickets', $e->getMessage(), 402);
        }
    }

    public function end(Request $request): JsonResponse
    {
        $data = $request->validate(['session_uuid' => 'required|uuid', 'duration_sec' => 'nullable|integer|min:0']);
        $log = $this->vr->endSession($this->device($request), $data['session_uuid'], $data['duration_sec'] ?? null);

        return $log ? response()->json(['ok' => true, 'status' => $log->status->value]) : $this->fail('unknown_session', 'Сесію не знайдено.', 404);
    }

    public function offlineSync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sessions' => 'present|array|max:1000',
            'sessions.*.session_uuid' => 'required|uuid|distinct',
            'sessions.*.app_key' => 'required|string',
            'sessions.*.started_at' => 'required|date',
            'sessions.*.duration_sec' => 'nullable|integer|min:0',
            'clock_tamper' => 'nullable|boolean',
            'monotonic_clock_ms' => 'nullable|integer|min:0',
        ]);
        $device = $this->device($request);
        $device->fill($this->vr->telemetry($data))->save();

        return response()->json($this->vr->offlineSync($device, $data['sessions']));
    }

    public function publicKey(VrSigner $signer): JsonResponse
    {
        return response()->json(['alg' => 'Ed25519', 'public_key' => $signer->publicKeyBase64()]);
    }

    private function authorized(Device $device): JsonResponse
    {
        return response()->json([
            'status' => 'authorized',
            'token' => $this->vr->issueToken($device),
            'token_type' => 'Bearer',
        ] + $this->vr->deviceInfo($device->load('user')));
    }

    private function device(Request $request): Device
    {
        return $request->attributes->get('device');
    }

    private function fail(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => $code, 'message' => $message], $status);
    }
}
