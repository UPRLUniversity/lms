@php $select = 'block w-full rounded-xl border-line bg-card text-ink shadow-sm focus:border-crimson focus:ring-crimson'; @endphp

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <x-ui.field name="user_id" label="Student">
        <select name="user_id" id="user_id" class="{{ $select }}">
            <option value="">All students</option>
            @foreach ($options['users'] as $user)
                <option value="{{ $user->id }}" @selected((string) request('user_id') === (string) $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
    </x-ui.field>

    <x-ui.field name="course_id" label="Course">
        <select name="course_id" id="course_id" class="{{ $select }}">
            <option value="">All courses</option>
            @foreach ($options['courses'] as $course)
                <option value="{{ $course->id }}" @selected((string) request('course_id') === (string) $course->id)>{{ $course->code }} — {{ $course->title }}</option>
            @endforeach
        </select>
    </x-ui.field>

    <x-ui.field name="department_id" label="Department">
        <select name="department_id" id="department_id" class="{{ $select }}">
            <option value="">All departments</option>
            @foreach ($options['departments'] as $department)
                <option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>{{ $department->name }}</option>
            @endforeach
        </select>
    </x-ui.field>

    <x-ui.field name="date_from" label="Enrolled from" type="date" :value="request('date_from')" />
    <x-ui.field name="date_to" label="Enrolled to" type="date" :value="request('date_to')" />
</div>
