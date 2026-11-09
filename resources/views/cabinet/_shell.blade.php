{{-- Шапка кабінету: назва закладу і вкладки --}}
<div class="cab-head section">
  <div>
    <div class="muted small-text">Кабінет закладу освіти</div>
    <div class="cab-title">{{ $user->school_or_org_name ?: $user->contact_person_name }}</div>
  </div>
  <form method="post" action="{{ route('cabinet.logout') }}">@csrf<button class="btn ghost small" type="submit">Вийти</button></form>
</div>
<nav class="cab-tabs section" aria-label="Розділи кабінету">
  <a href="{{ route('cabinet') }}" @class(['active' => request()->routeIs('cabinet')])>Уроки</a>
  <a href="{{ route('cabinet.devices') }}" @class(['active' => request()->routeIs('cabinet.devices')])>Окуляри</a>
  <a href="{{ route('cabinet.payments') }}" @class(['active' => request()->routeIs('cabinet.payments')])>Оплати й документи</a>
  <a href="{{ route('cabinet.settings') }}" @class(['active' => request()->routeIs('cabinet.settings')])>Налаштування</a>
</nav>
@if(session('ok'))<div class="ok">{{ session('ok') }}</div>@endif
