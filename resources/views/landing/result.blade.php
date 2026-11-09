@extends('landing.layout', ['title' => 'Результат оплати · VR-освіта'])

@section('content')
<section class="section narrow">
  <div class="card center">
    @if($payment->status === \App\Enums\PaymentStatus::Completed)
      <div class="ok-badge">✓</div>
      <h2>Оплату отримано</h2>
      <p class="lead">{{ $payment->grantLabel() }} уже активовано для {{ $payment->user->email }}.</p>
      <p class="muted">Менеджер VR-освіти надішле пароль закладу на {{ $payment->user->email }}. З ним школа входить у застосунок «VR-освіта» на окулярах і в <a href="{{ route('cabinet.login') }}">кабінет закладу</a>, де лежать рахунок і акт.</p>
    @elseif($payment->status === \App\Enums\PaymentStatus::Failed)
      <h2>Оплата не пройшла</h2>
      <p class="lead">Платіж {{ $payment->invoice_number }} відхилено банком або скасовано.</p>
      <a class="btn primary" href="{{ route('landing') }}#packages">Спробувати ще раз</a>
    @else
      <h2>Очікуємо підтвердження</h2>
      <p class="lead">Платіж {{ $payment->invoice_number }} обробляється. Сторінка оновиться автоматично.</p>
      <p class="muted">Якщо оплата пройшла, доступ з'явиться протягом хвилини.</p>
    @endif
    <a class="btn ghost" href="{{ route('landing') }}">На головну</a>
  </div>
</section>
@endsection

@if($payment->status === \App\Enums\PaymentStatus::Pending)
@push('scripts')
<script>setTimeout(function () { location.reload(); }, 5000);</script>
@endpush
@endif
