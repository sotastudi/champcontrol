@extends('layouts.app')
@section('title', 'Ayuda')
@section('content')
@php
    // Resalta los términos buscados dentro de un texto ya escapado.
    $highlight = function (string $text) use ($query) {
        $escaped = e($text);
        foreach (array_filter(preg_split('/\s+/', e($query))) as $term) {
            $escaped = preg_replace('/('.preg_quote($term, '/').')/iu', '<mark>$1</mark>', $escaped);
        }

        return new \Illuminate\Support\HtmlString($escaped);
    };
@endphp
<div class="col-lg-9 mx-auto">
    <h1 class="h3 mb-1">Ayuda</h1>
    <p class="text-muted">Cómo funciona KumiteControl: guías paso a paso según tu perfil, preguntas frecuentes y un buscador.</p>

    <form method="GET" action="{{ route('help') }}" class="input-group mb-4" role="search">
        <input type="search" name="q" value="{{ $query }}" class="form-control" placeholder="Busca una respuesta: cuadro, números, menores, invitar club…" aria-label="Buscar en la ayuda" maxlength="100">
        <button class="btn btn-primary" type="submit">Buscar</button>
        @if ($results !== null)<a href="{{ route('help') }}" class="btn btn-outline-secondary">Limpiar</a>@endif
    </form>

    @if ($results !== null)
        <h2 class="h5">{{ count($results) === 0 ? 'Sin resultados' : count($results).(count($results) === 1 ? ' resultado' : ' resultados') }} para «{{ $query }}»</h2>
        @forelse ($results as $result)
            <div class="card mb-2">
                <div class="card-body py-2">
                    <div class="small text-muted">{{ $result['label'] }}</div>
                    <h3 class="h6 mb-1"><a href="{{ route('help') }}#{{ $result['anchor'] }}" class="text-decoration-none">{{ $highlight($result['title']) }}</a></h3>
                    <p class="small mb-0">{{ $highlight($result['text']) }}</p>
                </div>
            </div>
        @empty
            <p class="text-muted">No hemos encontrado nada con esa búsqueda. Prueba con otras palabras o <a href="{{ route('help') }}">consulta toda la ayuda</a>.</p>
        @endforelse
        <hr class="my-4">
    @endif

    <nav class="mb-4" aria-label="Contenido de la ayuda">
        <div class="d-flex flex-wrap gap-2">
            @foreach ($guides as $key => $guide)
                <a href="#{{ $key }}" class="btn btn-sm {{ $mine === $key ? 'btn-primary' : 'btn-outline-primary' }}">{{ $guide['title'] }}</a>
            @endforeach
            <a href="#faq" class="btn btn-sm btn-outline-secondary">Preguntas frecuentes</a>
        </div>
    </nav>

    @foreach ($guides as $key => $guide)
        <section id="{{ $key }}" class="mb-5">
            <h2 class="h4">{{ $guide['title'] }} @if ($mine === $key)<span class="badge text-bg-primary align-middle fs-6">Tu perfil</span>@endif</h2>
            <p class="text-muted">{{ $guide['intro'] }}</p>
            <ol class="list-group list-group-numbered">
                @foreach ($guide['steps'] as $i => $step)
                    <li class="list-group-item d-flex" id="{{ $key }}-{{ $i + 1 }}">
                        <div class="ms-2 me-auto">
                            <div class="fw-semibold">{{ $step['title'] }}</div>
                            <div class="small">{{ $step['text'] }}</div>
                            @if (! empty($step['link']) && \Illuminate\Support\Facades\Route::has($step['link'][1]))
                                <a href="{{ route($step['link'][1]) }}" class="small">{{ $step['link'][0] }} &rarr;</a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>
    @endforeach

    <section id="faq" class="mb-5">
        <h2 class="h4">Preguntas frecuentes</h2>
        <div class="accordion" id="faq-list">
            @foreach ($faqs as $i => $faq)
                <div class="accordion-item" id="faq-{{ $i + 1 }}">
                    <h3 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq-body-{{ $i + 1 }}" aria-expanded="false" aria-controls="faq-body-{{ $i + 1 }}">{{ $faq['q'] }}</button>
                    </h3>
                    <div id="faq-body-{{ $i + 1 }}" class="accordion-collapse collapse" data-bs-parent="#faq-list">
                        <div class="accordion-body">{{ $faq['a'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
</div>
@endsection
