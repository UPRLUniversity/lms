@extends('errors.layout')

@section('code', '500')
@section('title', 'Something went wrong on our side')
@section('message', "This one is ours, not yours — the problem has been logged and someone will look at it. Please try again in a moment.")

@section('extra')
    {{-- The support address is settable at runtime (Settings → General), so a
         deployment can route these to whoever is actually on call. --}}
    @if ($support = config('mail.support'))
        <p class="mt-6 text-sm text-ink/65">
            If it keeps happening, tell us at
            <a href="mailto:{{ $support }}" class="rounded font-medium text-crimson underline-offset-2 hover:underline focus-ring">{{ $support }}</a>.
        </p>
    @endif
@endsection
