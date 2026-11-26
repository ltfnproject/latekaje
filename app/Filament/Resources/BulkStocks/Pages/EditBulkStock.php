<?php

namespace App\Filament\Resources\BulkStocks\Pages;

use App\Filament\Resources\BulkStocks\BulkStockResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBulkStock extends EditRecord
{
    protected static string $resource = BulkStockResource::class;

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
