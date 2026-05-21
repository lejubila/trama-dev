<section class="cover">
    <h1>{{ $document->title }}</h1>
    @if ($document->description)
        <p class="subtitle">{{ \Illuminate\Support\Str::limit($document->description, 120) }}</p>
    @endif
    <p class="meta">
        Data: <strong>{{ $document->document_date->format('d/m/Y') }}</strong>
        @if ($document->tenant)
            <br>Cliente: <strong>{{ $document->tenant->name }}</strong>
        @endif
        @if ($document->creator)
            <br>Autore: {{ $document->creator->name }}
        @endif
    </p>
    @if ($document->description)
        <div class="description">{{ $document->description }}</div>
    @endif
</section>
