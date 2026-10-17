{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($urls as $url)
@foreach ($url['alternates'] as $locale => $loc)
    <url>
        <loc>{{ $loc }}</loc>
@foreach ($url['alternates'] as $altLocale => $altLoc)
        <xhtml:link rel="alternate" hreflang="{{ $altLocale }}" href="{{ $altLoc }}"/>
@endforeach
        <xhtml:link rel="alternate" hreflang="x-default" href="{{ $url['alternates'][\App\Support\Locales::DEFAULT] }}"/>
@if ($url['updated'])
        <lastmod>{{ $url['updated']->toAtomString() }}</lastmod>
@endif
    </url>
@endforeach
@endforeach
</urlset>
