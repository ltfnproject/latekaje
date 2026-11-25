<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Imports\MemberImporter;
use App\Filament\Resources\Members\MemberResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('members.create_action')),
            ImportAction::make()
                ->importer(MemberImporter::class)
                ->label(__('common.import')),
            Action::make('downloadTemplateCsv')
                ->label(__('common.template_csv'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function () {
                    $headers = ['code', 'name', 'group', 'member_type', 'birth_date'];
                    $rows = [
                        ['1001', 'Budi Santoso', 'X TJKT 1', 'student', '2009-05-14'],
                        ['1002', 'Siti Aminah', 'X TJKT 1', 'student', '2009-08-21'],
                        ['G001', 'Ahmad Fauzi', 'GURU / STAF', 'teacher', '1986-04-12'],
                    ];

                    return response()->streamDownload(function () use ($headers, $rows) {
                        $out = fopen('php://output', 'w');
                        fwrite($out, "\xEF\xBB\xBF");
                        fputcsv($out, $headers, ';');

                        foreach ($rows as $row) {
                            fputcsv($out, $row, ';');
                        }

                        fclose($out);
                    }, 'template-import-member.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
                }),
        ];
    }
}
