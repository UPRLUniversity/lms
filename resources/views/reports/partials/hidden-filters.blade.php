{{--
    Re-emits the currently applied filters as hidden inputs so the export POST carries the
    exact same filter set the preview used. Arrays (e.g. compliance course_ids) render as
    repeated name[] inputs.
--}}
@foreach ($filters as $name => $value)
    @if (is_array($value))
        @foreach ($value as $item)
            <input type="hidden" name="{{ $name }}[]" value="{{ $item }}">
        @endforeach
    @elseif ($value !== null && $value !== '')
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endif
@endforeach
