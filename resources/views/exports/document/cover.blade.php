<section class="cover">
    <h1>{{ $document->title }}</h1>
    <p class="meta">
        {{ __('exports.cover_date') }} <strong>{{ $document->document_date->translatedFormat(__('exports.date_format')) }}</strong>
        @if ($document->tenant)
            <br>{{ __('exports.cover_customer') }} <strong>{{ $document->tenant->name }}</strong>
        @endif
        @if ($document->creator)
            <br>{{ __('exports.cover_author') }} {{ $document->creator->name }}
        @endif
    </p>
    @if ($document->description)
        <div class="description">{{ $document->description }}</div>
    @endif
</section>
