@php($categoryRules = isset($categoryModels) ? $categoryModels->mapWithKeys(fn ($c) => [$c->id => $c->fitRules()])->all() : [])
data-registration-form data-event-date="{{ $event->starts_on->format('Y-m-d') }}" data-adult-age="{{ config('champcontrol.adult_age') }}" data-categories="{{ json_encode($categoryRules) }}"
