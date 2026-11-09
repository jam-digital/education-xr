@extends('landing.layout', ['title' => 'Окуляри · Кабінет · VR-освіта'])

@push('head')<meta name="robots" content="noindex">@endpush

@section('content')
@include('cabinet._shell')

<section class="section cab">
  <div class="card note">
    <b>Як підключити нові окуляри.</b> Встановіть на окуляри застосунок «VR-освіта», відкрийте його й увійдіть email <b>{{ $user->email }}</b> і паролем закладу. Окуляри з'являться в цьому списку автоматично. Якщо в окулярах показано 6-значний код, його можна передати менеджеру VR-освіти замість пароля.
  </div>

  @if($devices->isEmpty())
    <p class="muted">Окулярів ще немає.</p>
  @else
    <div class="tablewrap card flush">
      <table class="tbl">
        <thead><tr><th>Окуляри</th><th>Серійний номер</th><th>Стан</th><th>Заряд</th><th>Пам'ять</th><th>На зв'язку</th><th>Уроки на окулярах</th></tr></thead>
        <tbody>
        @foreach($devices as $d)
          @php($offline = ! $d->last_sync_at || $d->last_sync_at->lt(now()->subDays(5)))
          <tr @class(['off' => $d->status->value !== 'active'])>
            <td>{{ $d->device_name ?: 'Без назви' }}<div class="muted small-text">{{ $d->model }}</div></td>
            <td class="mono">{{ $d->hardware_serial }}</td>
            <td><span @class(['badge', 'ok-b' => $d->status->value === 'active', 'off-b' => $d->status->value !== 'active'])>{{ $d->status->getLabel() }}</span></td>
            <td>{{ $d->battery_level_percent !== null ? $d->battery_level_percent.'%' : '—' }}</td>
            <td>{{ $d->free_storage_bytes ? \App\Filament\Support\Fmt::bytes($d->free_storage_bytes) : '—' }}</td>
            <td @class(['warn' => $offline && $d->status->value === 'active'])>
              {{ $d->last_sync_at ? $d->last_sync_at->timezone('Europe/Kyiv')->format('d.m.Y H:i') : 'ще не виходили' }}
              @if($offline && $d->status->value === 'active')<div class="small-text">Підключіть до Wi-Fi: без мережі понад 7 днів уроки заблокуються</div>@endif
            </td>
            <td>{{ $d->installed_lessons_state ? count($d->installed_lessons_state) : 0 }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  @endif
</section>
@endsection
