@extends('landing.layout', ['title' => 'Налаштування · Кабінет · VR-освіта'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
@include('cabinet._shell')

<section class="section cab cab-grid">
  <form class="form card" method="post" action="{{ route('cabinet.pin') }}">
    @csrf
    <h2 class="left top">PIN вчителя</h2>
    <p class="muted small-text">PIN потрібен, щоб вийти з навчального меню окулярів у налаштування. Учні його не знають.</p>
    @error('kiosk_pin_code')<div class="err">{{ $message }}</div>@enderror
    <label>Новий PIN (4 цифри)<input name="kiosk_pin_code" inputmode="numeric" pattern="\d{4}" maxlength="4" required value="{{ old('kiosk_pin_code') }}" autocomplete="off"></label>
    <button class="btn primary" type="submit">Зберегти PIN</button>
  </form>

  <form class="form card" method="post" action="{{ route('cabinet.password') }}">
    @csrf
    <h2 class="left top">Пароль закладу</h2>
    <p class="muted small-text">Цим паролем заклад входить і в кабінет, і на нових окулярах.</p>
    @if($errors->hasAny(['current_password', 'password']))<div class="err">{{ $errors->first('current_password') ?: $errors->first('password') }}</div>@endif
    <label>Поточний пароль<input name="current_password" type="password" required autocomplete="current-password"></label>
    <label>Новий пароль<input name="password" type="password" required minlength="8" autocomplete="new-password"></label>
    <label>Повторіть новий пароль<input name="password_confirmation" type="password" required minlength="8" autocomplete="new-password"></label>
    <button class="btn primary" type="submit">Змінити пароль</button>
  </form>

  <div class="card">
    <h2 class="left top">Дані закладу</h2>
    <dl class="req">
      <dt>Заклад</dt><dd>{{ $user->school_or_org_name ?: '—' }}</dd>
      <dt>Контактна особа</dt><dd>{{ $user->contact_person_name }}</dd>
      <dt>Email</dt><dd>{{ $user->email }}</dd>
      <dt>Телефон</dt><dd>{{ $user->phone ?: '—' }}</dd>
      <dt>Місто</dt><dd>{{ $user->city ?: '—' }}</dd>
      <dt>ЄДРПОУ</dt><dd>{{ $user->edrpou_tax_id ?: '—' }}</dd>
    </dl>
    <p class="muted small-text">Щоб змінити реквізити для рахунків, напишіть на <a href="mailto:{{ config('vrosvita.contact.email') }}">{{ config('vrosvita.contact.email') }}</a>.</p>
  </div>
</section>
@endsection
