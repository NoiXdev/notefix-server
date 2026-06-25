<x-filament-panels::page>
    <form wire:submit="save" class="grid gap-6">
        <label class="flex items-start gap-3">
            <input type="checkbox" wire:model="registrationEnabled" class="mt-1">
            <span>
                <span class="font-medium">Allow self-registration</span>
                <span class="block text-sm text-gray-500">When off, the public registration page is disabled.</span>
            </span>
        </label>

        <label class="flex items-start gap-3">
            <input type="checkbox" wire:model="mfaEnforced" class="mt-1">
            <span>
                <span class="font-medium">Require two-factor for everyone</span>
                <span class="block text-sm text-gray-500">Users without two-factor are sent to set it up before continuing.</span>
            </span>
        </label>

        <div>
            <x-filament::button type="submit">Save</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
