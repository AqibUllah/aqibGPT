<x-layouts.app.sidebar :title="$title ?? null">
    <flux:main>
    <x-layouts.app.header></x-layouts.app.header>
        {{ $slot }}
    </flux:main>
</x-layouts.app.sidebar>
