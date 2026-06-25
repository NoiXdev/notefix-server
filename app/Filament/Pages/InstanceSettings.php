<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class InstanceSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $title = 'Instance settings';

    protected static ?string $navigationLabel = 'Instance';

    protected string $view = 'filament.pages.instance-settings';

    public bool $registrationEnabled = true;

    public bool $mfaEnforced = false;

    public function mount(): void
    {
        $this->registrationEnabled = Setting::get('registrationEnabled', 'true') === 'true';
        $this->mfaEnforced = Setting::get('mfaEnforced', 'false') === 'true';
    }

    public function save(): void
    {
        Setting::put('registrationEnabled', $this->registrationEnabled ? 'true' : 'false');
        Setting::put('mfaEnforced', $this->mfaEnforced ? 'true' : 'false');

        Notification::make()->title('Settings saved')->success()->send();
    }
}
