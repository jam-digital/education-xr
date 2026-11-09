@extends('landing.layout', [
    'title' => $lesson->title.' · VR-урок · VR-освіта',
    'description' => \Illuminate\Support\Str::limit(strip_tags((string) $lesson->description) ?: 'VR-урок для шкільних окулярів Meta Quest і Pico з методичкою для вчителя.', 155),
    'ogType' => 'article',
    'ogImage' => ($lesson->cover_image_url ? asset('storage/'.$lesson->cover_image_url) : null),
])

@php
    $file = fn (?string $path) => $path ? (str_starts_with($path, 'http') ? $path : asset('storage/'.$path)) : null;
    $img = $lesson->cover_image_url ? $file($lesson->cover_image_url) : (file_exists(public_path("img/lessons/{$lesson->app_key}.svg")) ? asset("img/lessons/{$lesson->app_key}.svg") : null);
    $guide = $file($lesson->methodical_guide_pdf_url);
    $subject = $lesson->subject_category;
    $lessonPack = $packages['lesson'] ?? null;
@endphp

@push('head')
<script type="application/ld+json">{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'LearningResource',
    'name' => $lesson->title,
    'description' => strip_tags((string) $lesson->description),
    'educationalLevel' => $lesson->recommended_grades,
    'about' => $subject->getLabel(),
    'inLanguage' => 'uk',
    'timeRequired' => 'PT'.$lesson->max_session_duration_minutes.'M',
    'url' => route('lesson', $lesson->app_key),
    'provider' => ['@type' => 'Organization', 'name' => 'VR-освіта', 'url' => route('landing')],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@section('content')
<nav class="crumbs section" aria-label="Навігація">
  <a href="{{ route('landing') }}">Головна</a> <span aria-hidden="true">/</span>
  <a href="{{ route('landing') }}#lessons">VR-уроки</a> <span aria-hidden="true">/</span>
  <span>{{ $subject->getLabel() }}</span>
</nav>

<article class="lesson section">
  <div class="lesson-art" @if($img) style="background-image:url('{{ $img }}')" @endif>
    @unless($img)<span>{{ mb_substr($lesson->title, 0, 1) }}</span>@endunless
    <span class="subj" style="--c: {{ $subject->hex() }}">{{ $subject->getLabel() }}</span>
  </div>

  <div class="lesson-main">
    <div>
      <h1 class="lesson-title">{{ $lesson->title }}</h1>
      <div class="meta">
        @if($lesson->recommended_grades)<span class="pill">{{ $lesson->recommended_grades }}</span>@endif
        <span class="pill">{{ $lesson->max_session_duration_minutes }} хв</span>
        <span class="pill">Meta Quest · Pico</span>
        @if($lesson->current_version_name)<span class="pill">версія {{ $lesson->current_version_name }}</span>@endif
      </div>
      @if($lesson->description)
        <div class="lesson-text">{!! nl2br(e($lesson->description)) !!}</div>
      @endif

      @if($lesson->preview_video_url)
        <p><a class="btn ghost" href="{{ $lesson->preview_video_url }}" target="_blank" rel="noopener">Дивитися відео уроку</a></p>
      @endif

      <h2 class="left">Як проходить урок</h2>
      <ul class="checks">
        <li>Вчитель обирає урок у меню VR-освіти на окулярах, учні бачать лише освітнє меню.</li>
        <li>Урок триває до {{ $lesson->max_session_duration_minutes }} хвилин і працює без Wi-Fi за офлайн-сертифікатом.</li>
        <li>Повторний запуск протягом {{ $lesson->grace_period_seconds }} секунд і повернення після паузи не списують запуск.</li>
        <li>До уроку є PDF-методичка: мета, хід уроку, запитання для обговорення.</li>
      </ul>
    </div>

    <aside class="card buybox">
      @if($lessonPack)
        <div class="muted small-text">{{ $lessonPack['title'] }}</div>
        <div class="price">{{ \App\Services\Storefront::priceLabel($lessonPack) }}</div>
        <div class="muted">{{ $lessonPack['tagline'] }}</div>
        <button class="btn primary" type="button" data-buy="lesson" data-app="{{ $lesson->id }}">Купити запуски</button>
      @endif
      @if(isset($packages['subject']))
        <button class="btn ghost" type="button" data-buy="subject" data-subject="{{ $subject->value }}">Увесь курс «{{ $subject->getLabel() }}» на рік</button>
      @endif
      @if($guide)
        <a class="btn ghost" href="{{ $guide }}" target="_blank" rel="noopener">Методичка для вчителя (PDF)</a>
      @endif
      <a class="link small-text" href="{{ route('landing') }}#contact">Потрібен пробний тиждень або рахунок для казначейства?</a>
    </aside>
  </div>
</article>

@if($related->isNotEmpty())
<section class="section">
  <h2>Інші уроки: {{ $subject->getLabel() }}</h2>
  <div class="apps">
    @foreach($related as $r)
      @php($rImg = $r->cover_image_url ? asset('storage/'.$r->cover_image_url) : (file_exists(public_path("img/lessons/{$r->app_key}.svg")) ? asset("img/lessons/{$r->app_key}.svg") : null))
      <a class="app plain" href="{{ route('lesson', $r->app_key) }}">
        <div class="art" @if($rImg) style="background-image:url('{{ $rImg }}')" @endif>@unless($rImg)<span>{{ mb_substr($r->title, 0, 1) }}</span>@endunless</div>
        <div class="body"><h3>{{ $r->title }}</h3>@if($r->recommended_grades)<div class="meta"><span class="pill">{{ $r->recommended_grades }}</span></div>@endif</div>
      </a>
    @endforeach
  </div>
</section>
@endif

@include('landing._buy')
@endsection
