<!doctype html>
<html lang="uk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title ?? 'VR-освіта · VR-уроки з фізики, хімії та математики для шкіл' }}</title>
<meta name="description" content="{{ $description ?? 'Бібліотека інтерактивних VR-уроків і віртуальних лабораторій для шкіл: фізика, хімія, математика, біологія. Пакети запусків, річна підписка, офлайн-режим на Meta Quest і Pico.' }}">
@php($ogTitle = $title ?? 'VR-освіта · VR-уроки з фізики, хімії та математики для шкіл')
<link rel="canonical" href="{{ $canonical ?? url()->current() }}">
<meta property="og:type" content="{{ $ogType ?? 'website' }}">
<meta property="og:site_name" content="VR-освіта">
<meta property="og:locale" content="uk_UA">
<meta property="og:title" content="{{ $ogTitle }}">
<meta property="og:description" content="{{ $description ?? 'Бібліотека інтерактивних VR-уроків і віртуальних лабораторій для шкіл.' }}">
<meta property="og:url" content="{{ $canonical ?? url()->current() }}">
<meta property="og:image" content="{{ $ogImage ?? asset('img/og.png') }}">
<meta name="twitter:card" content="summary_large_image">
@stack('head')
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ filemtime(public_path('css/landing.css')) }}">
</head>
<body>
<div class="bg" aria-hidden="true"></div>
<header class="nav">
  <a class="brand" href="{{ route('landing') }}"><img src="{{ asset('img/logo.svg') }}" alt="VR-освіта"></a>
  <nav class="links">
    <a href="{{ route('landing') }}#lessons">Уроки</a>
    <a href="{{ route('landing') }}#how">Як це працює</a>
    <a href="{{ route('landing') }}#packages">Пакети</a>
    <a href="{{ route('page.contacts') }}">Контакти</a>
  </nav>
  <a class="btn ghost small" href="{{ url('/admin/login') }}">Вхід</a>
</header>
<main>
@yield('content')
</main>
<footer class="footer">
  <div><img src="{{ asset('img/logo.svg') }}" alt="VR-освіта" class="footlogo"><div class="muted">Освітня VR-платформа · {{ parse_url(config('app.url'), PHP_URL_HOST) }}</div></div>
  <div class="muted">
    <a href="mailto:{{ config('vrosvita.contact.email') }}">{{ config('vrosvita.contact.email') }}</a>
    @if(config('vrosvita.contact.phone')) · <a href="tel:{{ preg_replace('/[^+\d]/', '', config('vrosvita.contact.phone')) }}">{{ config('vrosvita.contact.phone') }}</a>@endif
    <div>© {{ date('Y') }} {{ config('vrosvita.seller.name') }}</div>
  </div>
  <nav class="footlinks" aria-label="Документи">
    <a href="{{ route('page.contacts') }}">Контакти</a>
    <a href="{{ route('page.offer') }}">Публічна оферта</a>
    <a href="{{ route('page.privacy') }}">Політика конфіденційності</a>
  </nav>
</footer>
@stack('scripts')
</body>
</html>
