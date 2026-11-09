@extends('landing.layout', ['title' => 'Кабінет · VR-освіта'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
@include('cabinet._shell')

<section class="section cab">
  <ul class="facts left">
    <li><b>{{ $stats['lessons'] }}</b><span>уроків доступно</span></li>
    <li><b>{{ $stats['tickets'] }}</b><span>запусків у пакетах</span></li>
    <li><b>{{ $stats['devices'] }}</b><span>окулярів підключено</span></li>
    <li><b>{{ $stats['month'] }}</b><span>уроків цього місяця</span></li>
  </ul>

  <h2 class="left">Ваші VR-уроки</h2>
  @if($lessons->isEmpty())
    <div class="card empty">
      <p>Поки що жоден урок не відкрито для вашого закладу.</p>
      <a class="btn primary" href="{{ route('landing') }}#packages">Обрати пакет</a>
    </div>
  @else
    <div class="tablewrap card flush">
      <table class="tbl">
        <thead><tr><th>Урок</th><th>Предмет</th><th>Доступ</th><th></th></tr></thead>
        <tbody>
        @foreach($lessons as $row)
          @php($l = $row['lesson'])
          <tr @class(['off' => ! $row['access']['can_start']])>
            <td><a href="{{ route('lesson', $l->app_key) }}">{{ $l->title }}</a>@if($row['devices_limited'])<div class="muted small-text">лише на окремих окулярах</div>@endif</td>
            <td>{{ $l->subject_category->getLabel() }}</td>
            <td><span @class(['badge', 'ok-b' => $row['access']['can_start'], 'off-b' => ! $row['access']['can_start']])>{{ $row['access']['label'] }}</span></td>
            <td class="r">
              @if(! $row['access']['can_start'] || ($row['access']['type'] === 'ticket_pool' && $row['access']['tickets_remaining'] <= $user->low_balance_threshold))
                <a class="btn ghost small" href="{{ route('lesson', $l->app_key) }}">Поповнити</a>
              @endif
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  @endif

  <h2 class="left">Останні проведені уроки</h2>
  @if($recent->isEmpty())
    <p class="muted">Тут з'явиться журнал уроків, щойно окуляри проведуть перший урок.</p>
  @else
    <div class="tablewrap card flush">
      <table class="tbl">
        <thead><tr><th>Дата</th><th>Урок</th><th>Окуляри</th><th>Тривалість</th><th>Списано</th></tr></thead>
        <tbody>
        @foreach($recent as $log)
          <tr>
            <td>{{ $log->started_at?->timezone('Europe/Kyiv')->format('d.m.Y H:i') }}</td>
            <td>{{ $log->lesson?->title }}</td>
            <td>{{ $log->device?->device_name ?: $log->device?->hardware_serial ?: '—' }}</td>
            <td>{{ $log->session_duration_sec ? round($log->session_duration_sec / 60).' хв' : '—' }}</td>
            <td>{{ $log->tickets_delta < 0 ? abs($log->tickets_delta).' запуск' : 'без списання' }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  @endif
</section>
@endsection
