<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['id'] = (string) Str::uuid();
        $data['created_at'] = now();
        $data['updated_at'] = now();
        $data['email_confirmed_at'] = ! empty($data['email_verified']) ? now() : null;
        unset($data['email_verified']);

        return $data;
    }
}
