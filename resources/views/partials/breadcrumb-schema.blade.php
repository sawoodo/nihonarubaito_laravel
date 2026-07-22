@if (isset($breadcrumbItems) && count($breadcrumbItems) > 0)
@push('structured-data')
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => collect($breadcrumbItems)->map(fn($item, $i) => array_filter([
        '@type' => 'ListItem',
        'position' => $i + 1,
        'name' => $item['name'],
        'item' => $item['url'] ?? null,
    ]))->values()->toArray(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
@endpush
@endif
