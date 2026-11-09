<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Device;
use App\Models\LessonLicense;
use App\Models\Payment;
use App\Models\TicketLog;
use App\Models\User;
use App\Services\VrApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Кабінет закладу освіти: доступні уроки, окуляри, оплати й документи, налаштування */
class CabinetController extends Controller
{
    public function __construct(private VrApiService $api) {}

    public function loginForm(Request $request): View|RedirectResponse
    {
        if ($request->user()?->role === Role::Client) {
            return redirect()->route('cabinet');
        }

        return view('cabinet.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::whereRaw('lower(email) = ?', [mb_strtolower(trim($data['email']))])->where('role', Role::Client->value)->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return back()->withErrors(['email' => 'Невірний email або пароль.'])->onlyInput('email');
        }
        if ($user->status !== UserStatus::Active) {
            return back()->withErrors(['email' => 'Акаунт закладу призупинено. Напишіть нам, щоб відновити доступ.'])->onlyInput('email');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('cabinet'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('cabinet.login');
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $licenses = LessonLicense::where('user_id', $user->id)->with('lesson')->get()
            ->filter(fn (LessonLicense $l) => $l->lesson?->is_active);

        $lessons = $licenses->groupBy('lesson_id')->map(fn ($group) => [
            'lesson' => $group->first()->lesson,
            'access' => $this->api->accessSummary($group),
            'devices_limited' => $group->contains(fn (LessonLicense $l) => ! empty($l->allowed_device_ids) && ! in_array('ALL', $l->allowed_device_ids, true)),
        ])->sortBy([fn ($a, $b) => $b['access']['can_start'] <=> $a['access']['can_start'], fn ($a, $b) => $a['lesson']->sort <=> $b['lesson']->sort])->values();

        $monthLessons = TicketLog::where('user_id', $user->id)->where('started_at', '>=', now()->startOfMonth())
            ->whereIn('status', ['active', 'completed', 'crashed_grace_reused'])->where('mode', '!=', 'admin_adjustment')->count();

        return view('cabinet.index', [
            'user' => $user,
            'lessons' => $lessons,
            'stats' => [
                'lessons' => $lessons->where('access.can_start', true)->count(),
                'tickets' => $lessons->sum('access.tickets_remaining'),
                'devices' => Device::where('user_id', $user->id)->where('status', 'active')->count(),
                'month' => $monthLessons,
            ],
            'recent' => TicketLog::where('user_id', $user->id)->where('mode', '!=', 'admin_adjustment')
                ->with(['lesson', 'device'])->latest('started_at')->limit(10)->get(),
        ]);
    }

    public function devices(Request $request): View
    {
        return view('cabinet.devices', [
            'user' => $request->user(),
            'devices' => Device::where('user_id', $request->user()->id)->orderByRaw("case when status = 'active' then 0 else 1 end")
                ->orderBy('device_name')->get(),
        ]);
    }

    public function payments(Request $request): View
    {
        return view('cabinet.payments', [
            'user' => $request->user(),
            'payments' => Payment::where('user_id', $request->user()->id)->with(['lesson', 'promotion'])->latest()->get(),
        ]);
    }

    public function document(Request $request, Payment $payment, string $kind): StreamedResponse
    {
        abort_unless($payment->user_id === $request->user()->id && $payment->status === PaymentStatus::Completed, 404);
        $path = $kind === 'act' ? $payment->act_pdf_url : $payment->invoice_pdf_url;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, basename($path));
    }

    public function settings(Request $request): View
    {
        return view('cabinet.settings', ['user' => $request->user()]);
    }

    public function updatePin(Request $request): RedirectResponse
    {
        $data = $request->validate(['kiosk_pin_code' => ['required', 'digits:4']], ['kiosk_pin_code.digits' => 'PIN має складатися з 4 цифр.']);
        $request->user()->update($data);

        return back()->with('ok', 'PIN вчителя змінено. Окуляри отримають його під час наступного підключення до інтернету.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], ['current_password.current_password' => 'Поточний пароль невірний.', 'password.confirmed' => 'Паролі не збігаються.', 'password.min' => 'Пароль має бути щонайменше 8 символів.']);
        $request->user()->update(['password' => $data['password']]);

        return back()->with('ok', 'Пароль змінено. Окуляри, які вже увійшли, працюють далі; на нових окулярах входьте з новим паролем.');
    }
}
