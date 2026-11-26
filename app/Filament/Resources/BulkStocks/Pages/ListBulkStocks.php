<?php

namespace App\Filament\Resources\BulkStocks\Pages;

use App\Filament\Resources\BulkStocks\BulkStockResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBulkStocks extends ListRecords
{
    protected static string $resource = BulkStockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Wadah Massal'),
        ];
    }
}
