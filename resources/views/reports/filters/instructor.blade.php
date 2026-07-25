@php $select = 'block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson'; @endphp

<div class="grid gap-4 sm:grid-cols-3">
    <x-ui.field name="department_id" label="Department">
        <select name="department_id" id="department_id" class="{{ $select }}">
            <option value="">All departments</option>
            @foreach ($options['departments'] as $department)
                <option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>{{ $department->name }}</option>
            @endforeach
        </select>
    </x-ui.field>

    <x-ui.field name="date_from" label="Activity from" type="date" :value="request('date_from')" hint="Scopes enrolments &amp; grading" />
    <x-ui.field name="date_to" label="Activity to" type="date" :value="request('date_to')" />
</div>
