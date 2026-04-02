<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div style="margin-top: 1rem; display: flex; justify-content: flex-start;">
            <x-filament::button type="submit" color="primary" icon="heroicon-o-check">
                {{ __('settings.password.save') }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
