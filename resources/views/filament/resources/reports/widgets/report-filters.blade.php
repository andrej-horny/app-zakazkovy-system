<x-filament-widgets::widget>
    <x-filament::section>
        <form wire:submit="apply">
            {{ $this->form }}

            <div class="mt-4 flex justify-end">
                <x-filament::button
                    type="submit"
                    icon="heroicon-m-funnel"
                >
                    Použiť filtre
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>
</x-filament-widgets::widget>