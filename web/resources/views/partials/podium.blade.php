@if (count($placements))
    <ul class="list-group mb-3">
        @foreach ($placements as $p)
            <li class="list-group-item d-flex justify-content-between">
                <span>{{ $p->place }}.º — <strong>{{ $p->registration->competitor->displayName() }}</strong>@if (($editable ?? false) && $p->withdrawn) <small class="text-danger">(retirado)</small>@endif</span>
                <span class="text-muted">{{ $p->registration->competitor->club }}</span>
            </li>
        @endforeach
    </ul>
@endif
