<?php

namespace App\Filament\Resources\FundingSources\Pages;

use App\Filament\Resources\FundingSources\FundingSourceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFundingSource extends EditRecord
{
    protected static string $resource = FundingSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->label(__('common.delete')),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
