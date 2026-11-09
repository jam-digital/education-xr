@extends('landing.layout', ['title' => 'Оплати · Кабінет · VR-освіта'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
@include('cabinet._shell')

<section class="section cab">
  <div class="row start">
    <a class="btn primary small" href="{{ route('landing') }}#packages">Купити пакет</a>
    <a class="btn ghost small" href="{{ route('landing') }}#contact">Рахунок для казначейства</a>
  </div>

  @if($payments->isEmpty())
    <p class="muted">Оплат ще не було.</p>
  @else
    <div class="tablewrap card flush">
      <table class="tbl">
        <thead><tr><th>Рахунок</th><th>Дата</th><th>Що оплачено</th><th>Сума</th><th>Статус</th><th>Документи</th></tr></thead>
        <tbody>
        @foreach($payments as $p)
          <tr>
            <td class="mono">{{ $p->invoice_number }}</td>
            <td>{{ ($p->paid_at ?? $p->created_at)->timezone('Europe/Kyiv')->format('d.m.Y') }}</td>
            <td>
              {{ $p->grantLabel() }}
            </td>
            <td class="nowrap">{{ number_format((float) $p->amount, 2, ',', ' ') }} {{ $p->currency === 'UAH' ? '₴' : $p->currency }}</td>
            <td><span @class(['badge', 'ok-b' => $p->status->value === 'completed', 'off-b' => in_array($p->status->value, ['failed', 'refunded'])])>{{ $p->status->getLabel() }}</span></td>
            <td class="nowrap">
              @if($p->status->value === 'completed')
                @if($p->invoice_pdf_url)<a href="{{ route('cabinet.document', [$p, 'invoice']) }}">Рахунок</a>@endif
                @if($p->act_pdf_url) · <a href="{{ route('cabinet.document', [$p, 'act']) }}">Акт</a>@endif
              @elseif($p->status->value === 'pending' && $p->payment_method->value === 'card_online')
                <a href="{{ route('pay.show', $p) }}">Оплатити</a>
              @else
                —
              @endif
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  @endif
</section>
@endsection
