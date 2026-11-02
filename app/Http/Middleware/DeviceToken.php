<?php

namespace App\Http\Middleware;

use App\Enums\DeviceStatus;
use App\Enums\UserStatus;
use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Bearer-токен окулярів з /api/v1/vr/device/auth|login|pair. Окуляри потрапляють у $request->attributes 'device' */
class DeviceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $device = $token ? Device::where('api_token_hash', hash('sha256', $token))->with('user')->first() : null;

        if (! $device) {
            return response()->json(['error' => 'unauthorized', 'message' => 'Окуляри не авторизовані. Виконайте /device/auth.'], 401);
        }
        if ($device->status !== DeviceStatus::Active || ! $device->user || $device->user->status !== UserStatus::Active) {
            return response()->json(['error' => 'device_blocked', 'message' => 'Окуляри або акаунт закладу заблоковано.'], 403);
        }

        $device->forceFill(['last_sync_at' => now(), 'last_ip' => $request->ip()])->save();
        $request->attributes->set('device', $device);

        return $next($request);
    }
}
