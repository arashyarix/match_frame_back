<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['email_confirmed_at'] = ! empty($data['email_verified'])
            ? ($this->record->email_confirmed_at ?? now())
            : null;
        unset($data['email_verified']);
        $data['updated_at'] = now();

        return $data;
    }
}
