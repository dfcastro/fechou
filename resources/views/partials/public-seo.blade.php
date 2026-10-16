<meta name="description" content="{{ $seoDescription }}">
<link rel="canonical" href="{{ $seoUrl }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="NegoZia">
<meta property="og:locale" content="pt_BR">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:url" content="{{ $seoUrl }}">
<meta property="og:image" content="{{ asset('icon-192.png') }}">
<meta property="og:image:width" content="192">
<meta property="og:image:height" content="192">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">
<meta name="twitter:image" content="{{ asset('icon-192.png') }}">
@php
    $structuredData = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                '@id' => route('home').'#organization',
                'name' => 'NegoZia',
                'url' => route('home'),
                'logo' => asset('icon-192.png'),
            ],
            [
                '@type' => 'WebSite',
                '@id' => route('home').'#website',
                'name' => 'NegoZia',
                'url' => route('home'),
                'inLanguage' => 'pt-BR',
                'publisher' => ['@id' => route('home').'#organization'],
            ],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
