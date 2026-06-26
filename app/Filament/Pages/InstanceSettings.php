<?php

namespace App\Filament\Pages;

use App\Jobs\MigrateImageStorage;
use App\Mail\TestMail;
use App\Models\Image;
use App\Models\Setting;
use App\Support\InstanceConfig;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * @property-read Schema $form
 */
class InstanceSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $title = 'Instance settings';

    protected static ?string $navigationLabel = 'Instance';

    protected string $view = 'filament.pages.instance-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'registrationEnabled' => Setting::get('registrationEnabled', 'true') === 'true',
            'mfaEnforced' => Setting::get('mfaEnforced', 'false') === 'true',
            'mail_transport' => InstanceConfig::get('mail.transport', 'smtp'),
            'mail_from_address' => InstanceConfig::get('mail.from_address'),
            'mail_from_name' => InstanceConfig::get('mail.from_name'),
            'mail_smtp_host' => InstanceConfig::get('mail.smtp.host'),
            'mail_smtp_port' => InstanceConfig::get('mail.smtp.port'),
            'mail_smtp_username' => InstanceConfig::get('mail.smtp.username'),
            'mail_smtp_password' => null, // never echo the secret
            'mail_smtp_encryption' => InstanceConfig::get('mail.smtp.encryption', 'tls'),
            'mail_postal_endpoint' => InstanceConfig::get('mail.postal.endpoint'),
            'mail_postal_key' => null, // never echo the secret
            'storage_disk' => InstanceConfig::storageDisk(),
            'storage_s3_key' => InstanceConfig::get('storage.s3.key'),
            'storage_s3_secret' => null, // never echo the secret
            'storage_s3_region' => InstanceConfig::get('storage.s3.region'),
            'storage_s3_bucket' => InstanceConfig::get('storage.s3.bucket'),
            'storage_s3_endpoint' => InstanceConfig::get('storage.s3.endpoint'),
            'storage_s3_path_style' => InstanceConfig::get('storage.s3.path_style') === 'true',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Access')->components([
                    Toggle::make('registrationEnabled')->label('Allow self-registration'),
                    Toggle::make('mfaEnforced')->label('Require two-factor for everyone'),
                ]),
                Section::make('Mail')->components([
                    TextInput::make('mail_from_name')->label('From name'),
                    TextInput::make('mail_from_address')->label('From address')->email(),
                    Select::make('mail_transport')->label('Transport')
                        ->options(['smtp' => 'SMTP', 'postal' => 'Postal (HTTP API)'])
                        ->default('smtp')->live()->required(),
                    TextInput::make('mail_smtp_host')->label('SMTP host')
                        ->visible(fn ($get) => $get('mail_transport') === 'smtp'),
                    TextInput::make('mail_smtp_port')->label('SMTP port')->numeric()
                        ->visible(fn ($get) => $get('mail_transport') === 'smtp'),
                    TextInput::make('mail_smtp_username')->label('SMTP username')
                        ->visible(fn ($get) => $get('mail_transport') === 'smtp'),
                    TextInput::make('mail_smtp_password')->label('SMTP password')
                        ->password()->revealable()->placeholder('•••••• (leave blank to keep)')
                        ->visible(fn ($get) => $get('mail_transport') === 'smtp'),
                    Select::make('mail_smtp_encryption')->label('Encryption')
                        ->options(['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'])->default('tls')
                        ->visible(fn ($get) => $get('mail_transport') === 'smtp'),
                    TextInput::make('mail_postal_endpoint')->label('Postal endpoint')->url()
                        ->visible(fn ($get) => $get('mail_transport') === 'postal'),
                    TextInput::make('mail_postal_key')->label('Postal API key')
                        ->password()->revealable()->placeholder('•••••• (leave blank to keep)')
                        ->visible(fn ($get) => $get('mail_transport') === 'postal'),
                ]),
                Section::make('Storage')
                    ->description('Switching the disk only affects new files; run the migration to move existing ones.')
                    ->components([
                        Select::make('storage_disk')->label('Disk')
                            ->options(['local' => 'Local', 's3' => 'S3'])
                            ->default('local')->live()->required(),
                        TextInput::make('storage_s3_key')->label('Access key')
                            ->visible(fn ($get) => $get('storage_disk') === 's3'),
                        TextInput::make('storage_s3_secret')->label('Secret')
                            ->password()->revealable()->placeholder('•••••• (leave blank to keep)')
                            ->visible(fn ($get) => $get('storage_disk') === 's3'),
                        TextInput::make('storage_s3_region')->label('Region')
                            ->visible(fn ($get) => $get('storage_disk') === 's3'),
                        TextInput::make('storage_s3_bucket')->label('Bucket')
                            ->visible(fn ($get) => $get('storage_disk') === 's3'),
                        TextInput::make('storage_s3_endpoint')->label('Endpoint (optional)')->url()
                            ->visible(fn ($get) => $get('storage_disk') === 's3'),
                        Toggle::make('storage_s3_path_style')->label('Use path-style endpoint')
                            ->visible(fn ($get) => $get('storage_disk') === 's3'),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTestEmail')
                ->label('Send test email')
                ->action(function (): void {
                    // Saved settings are applied at boot; re-apply so an unsaved
                    // session still uses the persisted config.
                    InstanceConfig::applyMail();
                    try {
                        Mail::to(auth()->user()->email)->send(new TestMail);
                        Notification::make()->title('Test email sent')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Test email failed')
                            ->body($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('testStorage')
                ->label('Test storage connection')
                ->action(function (): void {
                    InstanceConfig::applyFilesystem();
                    try {
                        $disk = Storage::disk(InstanceConfig::storageDisk());
                        $probe = 'healthcheck/probe.txt';
                        $disk->put($probe, 'ok');
                        $ok = $disk->get($probe) === 'ok';
                        $disk->delete($probe);
                        if (! $ok) {
                            throw new RuntimeException('probe read mismatch');
                        }
                        Notification::make()->title('Storage connection OK')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Storage test failed')
                            ->body($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('migrateStorage')
                ->label('Migrate files')
                ->form([
                    Select::make('target')->label('Target disk')
                        ->options(['local' => 'Local', 's3' => 'S3'])
                        ->default(InstanceConfig::storageDisk() === 's3' ? 'local' : 's3')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $source = InstanceConfig::storageDisk();
                    $target = (string) $data['target'];
                    if ($source === $target) {
                        Notification::make()->title('Source and target are the same')->warning()->send();

                        return;
                    }

                    $jobs = Image::all()
                        ->map(fn (Image $i) => new MigrateImageStorage($i->workspace_id, $i->path, $source, $target))
                        ->all();
                    if ($jobs === []) {
                        Notification::make()->title('No files to migrate')->info()->send();

                        return;
                    }

                    $batch = Bus::batch($jobs)
                        ->name('image-storage-migration')
                        ->then(fn () => Setting::put('storage.migration.status', 'completed'))
                        ->catch(fn () => Setting::put('storage.migration.status', 'failed'))
                        ->dispatch();

                    Setting::put('storage.migration.batch_id', $batch->id);
                    Setting::put('storage.migration.status', 'running');
                    Notification::make()->title('Migration started')
                        ->body('Moving '.count($jobs)." file(s) to {$target}.")->success()->send();
                }),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::put('registrationEnabled', ($data['registrationEnabled'] ?? false) ? 'true' : 'false');
        Setting::put('mfaEnforced', ($data['mfaEnforced'] ?? false) ? 'true' : 'false');

        InstanceConfig::set('mail.transport', $data['mail_transport'] ?? 'smtp');
        InstanceConfig::set('mail.from_address', $data['mail_from_address'] ?? null);
        InstanceConfig::set('mail.from_name', $data['mail_from_name'] ?? null);
        InstanceConfig::set('mail.smtp.host', $data['mail_smtp_host'] ?? null);
        InstanceConfig::set('mail.smtp.port', $data['mail_smtp_port'] ?? null);
        InstanceConfig::set('mail.smtp.username', $data['mail_smtp_username'] ?? null);
        InstanceConfig::set('mail.smtp.password', $data['mail_smtp_password'] ?? null); // blank = keep
        InstanceConfig::set('mail.smtp.encryption', $data['mail_smtp_encryption'] ?? null);
        InstanceConfig::set('mail.postal.endpoint', $data['mail_postal_endpoint'] ?? null);
        InstanceConfig::set('mail.postal.key', $data['mail_postal_key'] ?? null); // blank = keep

        InstanceConfig::set('storage.disk', $data['storage_disk'] ?? 'local');
        InstanceConfig::set('storage.s3.key', $data['storage_s3_key'] ?? null);
        InstanceConfig::set('storage.s3.secret', $data['storage_s3_secret'] ?? null); // blank = keep
        InstanceConfig::set('storage.s3.region', $data['storage_s3_region'] ?? null);
        InstanceConfig::set('storage.s3.bucket', $data['storage_s3_bucket'] ?? null);
        InstanceConfig::set('storage.s3.endpoint', $data['storage_s3_endpoint'] ?? null);
        InstanceConfig::set('storage.s3.path_style', ($data['storage_s3_path_style'] ?? false) ? 'true' : 'false');

        Notification::make()->title('Settings saved')->success()->send();
    }
}
