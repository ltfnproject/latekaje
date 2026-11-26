<?php

namespace App\Filament\Resources\BulkStocks\Pages;

use App\Filament\Resources\BulkStocks\BulkStockResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBulkStock extends CreateRecord
{
    protected static string $resource = BulkStockResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
