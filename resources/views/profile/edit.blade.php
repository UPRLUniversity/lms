<x-app-layout title="Profile">
    <div class="mx-auto max-w-3xl space-y-6">
        <x-ui.card>
            <div class="max-w-xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </x-ui.card>

        <x-ui.card>
            <div class="max-w-xl">
                @include('profile.partials.update-password-form')
            </div>
        </x-ui.card>

        <x-ui.card>
            <div class="max-w-xl">
                @include('profile.partials.delete-user-form')
            </div>
        </x-ui.card>
    </div>
</x-app-layout>
