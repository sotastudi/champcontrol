@extends('layouts.app')
@section('title', $event->name)
@section('content')
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">{{ $event->name }}</h1>
        <div class="text-muted">{{ $event->discipline }} · {{ $event->starts_on->translatedFormat('j \d\e F \d\e Y') }} · {{ $event->venue }}</div>
        <div class="mt-2">
            <span class="badge text-bg-secondary">{{ $event->status->label() }}</span>
            <span class="badge text-bg-{{ $event->is_public ? 'success' : 'light' }}">{{ $event->is_public ? 'Público' : 'Privado' }}</span>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('panel.registrations.index', $event) }}" class="btn btn-primary">Inscripciones</a>
        <form method="POST" action="{{ route('panel.events.bibs', $event) }}"
              onsubmit="return confirm('Se asignarán (o reasignarán) los números de competidor agrupando por club. ¿Continuar?')">
            @csrf
            <button type="submit" class="btn btn-outline-secondary">Asignar números de competidor</button>
        </form>
        <a href="{{ route('panel.exports.standings', $event) }}" class="btn btn-outline-secondary">Exportar clasificación (CSV)</a>
        <a href="{{ route('panel.events.edit', $event) }}" class="btn btn-outline-primary">Editar</a>
        @if ($event->isListed())
            <a href="{{ route('public.events.show', $event) }}" class="btn btn-outline-secondary btn-icon" title="Ver página pública" aria-label="Ver página pública"><svg width="16" height="16" aria-hidden="true"><use href="#eye"/></svg> Ver</a>
        @endif
        <form method="POST" action="{{ route('panel.events.destroy', $event) }}" onsubmit="return confirm('¿Eliminar el evento y todos sus datos?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger">Eliminar</button>
        </form>
    </div>
</div>
@if ($event->description)<p style="white-space: pre-line">{{ $event->description }}</p>@endif

<section id="acceso" class="mb-4">
    <h2 class="h5 mb-2">Código de acceso a los resultados</h2>
    <div class="d-flex align-items-center flex-wrap gap-3">
        <code class="fs-4 user-select-all" aria-label="Código de acceso">{{ $event->access_code }}</code>
        <form method="POST" action="{{ route('panel.events.access-code', $event) }}" onsubmit="return confirm('El código actual dejará de funcionar y quien lo tenga tendrá que pedir el nuevo. ¿Regenerarlo?')">
            @csrf
            <button type="submit" class="btn btn-outline-secondary btn-sm">Regenerar código</button>
        </form>
    </div>
    <p class="text-muted small mt-1 mb-0">Se genera con el evento. El público lo necesita para ver cuadros, resultados y tatamis; tú decides a quién dárselo. Tú y tus controladores de tatami no lo necesitáis.</p>
</section>

<section id="categorias" class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h5 mb-0">Categorías</h2>
        <a href="{{ route('panel.categories.create', $event) }}" class="btn btn-primary btn-sm">Nueva categoría</a>
    </div>
    @if ($event->categories->isEmpty())
        <p class="text-muted">Aún no hay categorías.</p>
    @else
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Nombre</th><th>Criterios</th><th class="text-center">Confirmados</th><th></th></tr></thead>
                <tbody>
                @foreach ($event->categories as $category)
                    <tr>
                        <td>{{ $category->name }}@if ($category->tatami)<div><span class="badge text-bg-secondary">Tatami {{ $category->tatami }}</span></div>@endif</td>
                        <td class="text-muted small">
                            {{ collect([
                                $category->modality,
                                $category->sex?->label(),
                                ($category->min_age !== null || $category->max_age !== null) ? 'Edad '.($category->min_age ?? '…').'–'.($category->max_age ?? '…') : null,
                                ($category->min_weight !== null || $category->max_weight !== null) ? 'Peso '.($category->min_weight ?? '…').'–'.($category->max_weight ?? '…').' kg' : null,
                                $category->level,
                                $category->tatami ? 'Tatami '.$category->tatami.($category->referees ? ' ('.$category->referees.' '.'jueces'.')' : '') : null,
                            ])->filter()->implode(' · ') ?: 'Sin restricciones' }}
                        </td>
                        <td class="text-center"><a href="{{ route('panel.registrations.index', ['event' => $event, 'category_id' => $category->id]) }}">{{ $category->confirmed_count }}</a></td>
                        <td class="text-end">
                            <a href="{{ route('panel.brackets.show', [$event, $category]) }}" class="btn btn-blue btn-sm btn-icon"><svg width="16" height="16" aria-hidden="true"><use href="#sitemap"/></svg> Cuadro</a>
                            @if ($category->is_published && $event->isListed())
                                <a href="{{ route('public.categories.show', [$event, $category]) }}" class="btn btn-outline-secondary btn-sm btn-icon" title="Ver página pública" aria-label="Ver página pública"><svg width="16" height="16" aria-hidden="true"><use href="#eye"/></svg> Ver</a>
                            @endif
                            <form method="POST" action="{{ route('panel.categories.publication', [$event, $category]) }}" class="d-inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-outline-success btn-sm btn-icon"><svg width="16" height="16" aria-hidden="true"><use href="#share"/></svg> {{ $category->is_published ? 'Ocultar' : 'Publicar' }}</button>
                            </form>
                            <a href="{{ route('panel.categories.edit', [$event, $category]) }}" class="btn btn-yellow btn-sm btn-icon"><svg width="16" height="16" aria-hidden="true"><use href="#pencil"/></svg> Editar</a>
                            <form method="POST" action="{{ route('panel.categories.destroy', [$event, $category]) }}" class="d-inline" onsubmit="return confirm('¿Eliminar la categoría? Se borrarán también su cuadro, combates e inscripciones.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm btn-icon"><svg width="16" height="16" aria-hidden="true"><use href="#trash"/></svg> Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

<section id="tatamis" class="mb-4">
    <h2 class="h5 mb-2">Tatamis</h2>
    <p class="text-muted small">Combate actual de cada tatami: el siguiente con los dos participantes definidos de sus categorías (en el orden de creación de las categorías y por número de combate).</p>
    <div class="row g-3">
        @foreach ($tatamis as $tatami)
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card h-100 tatami-block">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong><a href="{{ route('panel.tatamis.show', [$event, $tatami['number']]) }}" class="stretched-link text-decoration-none text-body" title="Abrir el tatami y puntuar el combate actual">Tatami {{ $tatami['number'] }}</a></strong>
                        <span class="small text-muted">{{ $tatami['categories']->count() }} {{ $tatami['categories']->count() === 1 ? 'categoría' : 'categorías' }}@if ($tatami['categories']->count()) · {{ $tatami['pending'] }} {{ $tatami['pending'] === 1 ? 'combate listo' : 'combates listos' }}@endif</span>
                    </div>
                    <div class="card-body">
                        @if ($tatami['categories']->isEmpty())
                            <p class="text-muted mb-0">Sin categorías asignadas.</p>
                        @else
                            {{-- Orden de las categorías en este tatami (la primera es la que compite antes). «Reordenar» activa arrastrar y soltar. --}}
                            <div class="tatami-order">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small text-uppercase text-muted">Orden</span>
                                    @if ($tatami['categories']->count() > 1)
                                        <button type="button" class="btn btn-outline-secondary btn-sm py-0" data-reorder-toggle data-label-on="Hecho" data-label-off="Reordenar" aria-pressed="false">Reordenar</button>
                                    @endif
                                </div>
                                <ol class="small mb-2 ps-3" data-tatami-order data-reorder-url="{{ route('panel.tatamis.reorder', [$event, $tatami['number']]) }}">
                                    @foreach ($tatami['categories'] as $category)
                                        <li class="mb-1" data-category-id="{{ $category->id }}">
                                            <span class="d-inline-flex align-items-center gap-1 flex-wrap">
                                                <span class="reorder-handle text-muted d-none" aria-hidden="true" title="Arrastra para reordenar">&#8942;&#8942;</span>
                                                <a href="{{ route('panel.brackets.show', [$event, $category]) }}">{{ $category->name }}</a>
                                                @if ($tatami['categories']->count() > 1)
                                                    <form method="POST" action="{{ route('panel.categories.move', [$event, $category]) }}" class="reorder-buttons d-none gap-1">
                                                        @csrf
                                                        <button type="submit" name="direction" value="up" class="btn btn-outline-secondary btn-sm py-0 px-1" @disabled($loop->first) aria-label="Subir {{ $category->name }}" title="Subir">&uarr;</button>
                                                        <button type="submit" name="direction" value="down" class="btn btn-outline-secondary btn-sm py-0 px-1" @disabled($loop->last) aria-label="Bajar {{ $category->name }}" title="Bajar">&darr;</button>
                                                    </form>
                                                @endif
                                            </span>
                                        </li>
                                    @endforeach
                                </ol>
                                <div class="small" data-reorder-status aria-live="polite"></div>
                            </div>
                            @if ($tatami['now'])
                                @php $bout = $tatami['now']; @endphp
                                <div class="small text-uppercase text-muted">Ahora</div>
                                <div class="mb-3 tatami-now">
                                    <a href="{{ route('panel.brackets.show', [$event, $bout->category]) }}" class="text-decoration-none text-body">
                                        <div class="fw-semibold">{{ $bout->category->name }} · Combate {{ $bout->number }}</div>
                                        <div><span class="side-chip side-aka" role="img" aria-label="AKA (rojo)"></span>{{ $bout->slotA->competitor->displayName() }}@if ($bout->slotA->competitor->bib_number) <small class="text-muted">(Nº {{ $bout->slotA->competitor->bib_number }})</small>@endif</div>
                                        <div><span class="side-chip side-shiro" role="img" aria-label="SHIRO (blanco)"></span>{{ $bout->slotB->competitor->displayName() }}@if ($bout->slotB->competitor->bib_number) <small class="text-muted">(Nº {{ $bout->slotB->competitor->bib_number }})</small>@endif</div>
                                    </a>
                                </div>
                                @if ($tatami['next']->isNotEmpty())
                                    <div class="small text-uppercase text-muted">A continuación</div>
                                    <ul class="list-unstyled small mb-0">
                                        @foreach ($tatami['next'] as $next)
                                            <li>{{ $next->category->name }} · Combate {{ $next->number }}: {{ $next->slotA->competitor->displayName() }} <span class="text-muted">vs</span> {{ $next->slotB->competitor->displayName() }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            @else
                                <p class="text-muted mb-0">Sin combates listos (sin cuadro, a la espera de resultados o ya finalizado).</p>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>

<section id="controladores" class="mb-4">
    <h2 class="h5 mb-2">Controladores de tatami</h2>
    <p class="text-muted small">Pueden puntuar el combate actual de los tatamis que se les asignen y no ven nada más del evento. Les creas un usuario y una contraseña (el correo es opcional) y se lo entregas tú; pueden tener acceso a varios tatamis.</p>
    @php $operatorsByUser = $event->operators->groupBy('user_id'); @endphp
    @if ($operatorsByUser->isNotEmpty())
        <ul class="list-group mb-3">
            @foreach ($operatorsByUser as $rows)
                @php $user = $rows->first()->user; @endphp
                <li class="list-group-item">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <strong>{{ $user->name }}</strong>
                            <span class="text-muted">{{ $user->username ? '@'.$user->username : '' }}{{ $user->email ? ' · '.$user->email : '' }}</span>
                            <div class="mt-1 d-flex flex-wrap gap-1">
                                @foreach ($rows->sortBy('tatami') as $operator)
                                    <form method="POST" action="{{ route('panel.operators.destroy', [$event, $operator]) }}" class="d-inline" onsubmit="return confirm('¿Retirar el acceso al tatami {{ $operator->tatami }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <span class="badge text-bg-secondary d-inline-flex align-items-center gap-1">Tatami {{ $operator->tatami }}
                                            <button type="submit" class="btn-close btn-close-white" style="font-size: .55rem" aria-label="Retirar el tatami {{ $operator->tatami }}"></button>
                                        </span>
                                    </form>
                                @endforeach
                            </div>
                        </div>
                        @if ($user->isTatamiAccount() && (int) $user->created_by_user_id === (int) auth()->id())
                            <form method="POST" action="{{ route('panel.operators.password', [$event, $rows->first()]) }}" class="d-flex gap-2 align-items-center">
                                @csrf
                                @method('PATCH')
                                <input type="text" name="password" class="form-control form-control-sm" placeholder="Nueva contraseña" minlength="6" required autocomplete="new-password" aria-label="Nueva contraseña de {{ $user->name }}">
                                <button type="submit" class="btn btn-outline-secondary btn-sm text-nowrap">Cambiar contraseña</button>
                            </form>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
    <form method="POST" action="{{ route('panel.operators.store', $event) }}" class="row g-2 align-items-start">
        @csrf
        <div class="col-12 col-md-3"><x-field name="username" label="Usuario" :value="old('username')" required autocomplete="off" help="Letras minúsculas, números, . - _" /></div>
        <div class="col-12 col-md-3"><x-field name="password" type="text" label="Contraseña" :value="old('password')" autocomplete="new-password" help="Mínimo 6. Solo para cuentas nuevas." /></div>
        <div class="col-12 col-md-3"><x-field name="name" label="Nombre (opcional)" :value="old('name')" /></div>
        <div class="col-12 col-md-3"><x-field name="email" type="email" label="Correo (opcional)" :value="old('email')" /></div>
        <div class="col-12">
            <div class="form-label">Tatamis con acceso <span class="text-danger">*</span></div>
            <div class="d-flex flex-wrap gap-3 mb-2">
                @foreach (range(1, $event->tatamiCount()) as $n)
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tatamis[]" value="{{ $n }}" id="op-tatami-{{ $n }}" @checked(in_array($n, (array) old('tatamis', [])))>
                        <label class="form-check-label" for="op-tatami-{{ $n }}">Tatami {{ $n }}</label>
                    </div>
                @endforeach
            </div>
            @error('tatamis')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
            <button type="submit" class="btn btn-outline-primary">Añadir controlador</button>
        </div>
    </form>
</section>
@endsection
