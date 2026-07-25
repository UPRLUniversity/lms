@php $select = 'block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson'; @endphp

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <x-ui.field name="course_id" label="Course">
        <select name="course_id" id="course_id" class="{{ $select }}">
            <option value="">All courses</option>
            @foreach ($options['courses'] as $course)
                <option value="{{ $course->id }}" @selected((string) request('course_id') === (string) $course->id)>{{ $course->code }} — {{ $course->title }}</option>
            @endforeach
        </select>
    </x-ui.field>

    <x-ui.field name="status" label="Status">
        <select name="status" id="status" class="{{ $select }}">
            @foreach ($options['statuses'] as $value => $label)
                <option value="{{ $value }}" @selected((request('status') ?? 'all') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </x-ui.field>

    <x-ui.field name="date_from" label="Issued from" type="date" :value="request('date_from')" />
    <x-ui.field name="date_to" label="Issued to" type="date" :value="request('date_to')" />
</div>
