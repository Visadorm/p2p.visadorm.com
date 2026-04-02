<?php

namespace App\Filament\Clusters\Profile\Pages;

use App\Filament\Clusters\Profile;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ChangePasswordPage extends Page
{
    protected static ?string $cluster = Profile::class;

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.pages.change-password';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('settings.password.title');
    }

    public function getTitle(): string
    {
        return __('settings.password.title');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('settings.password.section'))
                    ->schema([
                        TextInput::make('current_password')
                            ->label(__('settings.password.current_password'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->rule('current_password'),
                        TextInput::make('password')
                            ->label(__('settings.password.new_password'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->rule(Password::default())
                            ->confirmed(),
                        TextInput::make('password_confirmation')
                            ->label(__('settings.password.confirm_password'))
                            ->password()
                            ->revealable()
                            ->required(),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        auth()->user()->update([
            'password' => Hash::make($data['password']),
        ]);

        $this->form->fill();

        Notification::make()
            ->title(__('settings.password.updated'))
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('settings.password.save'))
                ->submit('save'),
        ];
    }
}
