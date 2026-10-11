@php
    $editable ??= false;
    $canRecord ??= true;
    $names = [
        'a' => $bout->slotA?->competitor->displayName(),
        'b' => $bout->slotB?->competitor->displayName(),
    ];
    $empty = $bout->isBye() ? 'Pase' : 'Por determinar';
@endphp
<div class="bout-card mb-2">
    <div class="small text-muted px-2 pt-1">
        {{ $bout->number ? 'Combate '.$bout->number : 'Pase' }}@if ($bout->is_bronze) · Bronce @endif
    </div>
    @foreach (['a' => $bout->slot_a_id, 'b' => $bout->slot_b_id] as $slot => $registrationId)
        @php $registration = $slot === 'a' ? $bout->slotA : $bout->slotB; @endphp
        <div class="slot d-flex justify-content-between gap-2 {{ $registrationId && $registrationId === $bout->winner_id ? 'winner' : '' }}">
            <span class="{{ $registrationId ? '' : 'text-muted fst-italic' }}">
                {{ $names[$slot] ?? $empty }}
                @if ($editable && $registration?->isWithdrawn())
                    <small class="text-danger">({{ $registration->isDisqualified() ? 'descalificado' : 'retirado' }})</small>
                @endif
                @if ($registration?->competitor->club)
                    <small class="text-muted">({{ $registration->competitor->club }})</small>
                @endif
            </span>
            @if ($editable && $canRecord && $bout->filledSlots() === 2 && ! $bout->isBye() && ! $bout->isWalkover())
                <form method="POST" action="{{ route('panel.bouts.result', [$event, $category, $bout]) }}" class="no-print">
                    @csrf @method('PUT')
                    <input type="hidden" name="winner_id" value="{{ $registrationId }}">
                    <button class="btn btn-sm {{ $registrationId === $bout->winner_id ? 'btn-success' : 'btn-outline-primary' }}">Gana</button>
                </form>
            @endif
        </div>
    @endforeach
    @if ($bout->isWalkover())
        <div class="small text-muted px-2 pb-1">Gana por incomparecencia</div>
    @endif
</div>
