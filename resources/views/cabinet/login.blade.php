@extends('landing.layout', ['title' => 'Вхід у кабінет закладу · VR-освіта', 'description' => 'Кабінет закладу освіти VR-освіта: уроки, окуляри, оплати й документи.'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
<section class="section narrow login">
  <h1 class="doc-h1">Кабінет закладу освіти</h1>
  <p class="muted">Увійдіть тим самим email і паролем, з якими школа входить в окулярах.</p>
  <form class="form card" method="post" action="{{ route('cabinet.login.submit') }}">
    @csrf
    @if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
    <label>Email закладу<input name="email" type="email" required autocomplete="username" value="{{ old('email') }}" autofocus></label>
    <label>Пароль<input name="password" type="password" required autocomplete="current-password"></label>
    <label class="inline"><input type="checkbox" name="remember" value="1"> Запам'ятати мене</label>
    <button class="btn primary" type="submit">Увійти</button>
  </form>
  <p class="muted small-text">Немає пароля або забули його? Напишіть на <a href="mailto:{{ config('vrosvita.contact.email') }}">{{ config('vrosvita.contact.email') }}</a>, і менеджер VR-освіти надішле новий. Для нового закладу <a href="{{ route('landing') }}#contact">залиште заявку</a>.</p>
</section>
@endsection
