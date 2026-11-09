@extends('landing.layout', ['title' => 'Контакти · VR-освіта', 'description' => 'Контакти платформи VR-освіта: email, телефон, реквізити. Пробний тиждень і рахунки для казначейства.'])

@section('content')
<section class="section narrow doc">
  <h1>Контакти</h1>
  <div class="card doc-grid">
    <div><div class="muted small-text">Email</div><a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></div>
    @if($contact['phone'])<div><div class="muted small-text">Телефон</div><a href="tel:{{ preg_replace('/[^+\d]/', '', $contact['phone']) }}">{{ $contact['phone'] }}</a></div>@endif
    <div><div class="muted small-text">Адреса</div>{{ $seller['address'] }}</div>
    <div><div class="muted small-text">Час роботи</div>Пн–Пт, 9:00–18:00</div>
  </div>

  <h2 class="left">Реквізити</h2>
  <div class="card">
    <dl class="req">
      <dt>Назва</dt><dd>{{ $seller['name'] }}</dd>
      <dt>ЄДРПОУ</dt><dd>{{ $seller['edrpou'] }}</dd>
      <dt>IBAN</dt><dd>{{ $seller['iban'] }}</dd>
      <dt>Банк</dt><dd>{{ $seller['bank'] }}</dd>
      <dt>Оподаткування</dt><dd>{{ $seller['tax_note'] }}</dd>
    </dl>
  </div>

  <p class="muted">Для пробного тижня, презентації директору чи рахунку для казначейства заповніть <a href="{{ route('landing') }}#contact">форму на головній</a>, і ми відповімо на email.</p>
</section>
@endsection
