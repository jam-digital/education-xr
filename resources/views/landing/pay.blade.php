@extends('landing.layout', ['title' => 'Оплата · VR-освіта'])

@section('content')
<section class="section narrow">
  <div class="card center">
    <h2>Оплата {{ $payment->invoice_number }}</h2>
    <p class="lead">{{ $payment->grantLabel() }}</p>
    <div class="price">{{ number_format((float) $payment->amount, 2, ',', ' ') }} {{ $payment->currency }}</div>
    @if($form)
      <p class="muted">Переходимо на захищену сторінку LiqPay…</p>
      <form id="liqpay" method="post" action="{{ $checkoutUrl }}" accept-charset="utf-8">
        <input type="hidden" name="data" value="{{ $form['data'] }}">
        <input type="hidden" name="signature" value="{{ $form['signature'] }}">
        <button class="btn primary" type="submit">Оплатити через LiqPay</button>
      </form>
    @else
      <div class="err">Онлайн-оплата ще не підключена: платіжний шлюз LiqPay налаштовується. Замовлення {{ $payment->invoice_number }} збережено, менеджер зв'яжеться з вами за адресою {{ $payment->user->email }}.</div>
      <a class="btn ghost" href="{{ route('landing') }}">На головну</a>
    @endif
  </div>
</section>
@endsection

@if($form)
@push('scripts')
<script>setTimeout(function () { document.getElementById('liqpay').submit(); }, 800);</script>
@endpush
@endif
