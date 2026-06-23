<?php

namespace Modules\Ledger\Filament\Resources\Pages;

use App\Filament\Concerns\HasImportAction;
use Modules\Ledger\Filament\Resources\AkunResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Modules\Ledger\Services\CoaTemplateService;
use Modules\Ledger\Services\LedgerSaldoAwalImportService;

class ListAkun extends ListRecords
{
    use HasImportAction;

    protected static string $resource = AkunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ...$this->importActions([
                'entitas' => 'saldo awal',
                'template' => ['kode_akun', 'debit', 'kredit', 'keterangan'],
                'template_contoh' => ['1-1000', '5000000', '', 'Kas awal'],
                'label' => 'Import Saldo Awal',
                'service' => LedgerSaldoAwalImportService::class,
                'directory' => 'imports/ledger/saldo-awal/uploads',
                'ability' => 'manage_ledger',
                'petunjuk' => 'Unggah .xlsx/.csv/.ods kolom: kode_akun (wajib), debit, kredit (isi salah satu), keterangan. SELURUH file = SATU jurnal pembuka yang HARUS seimbang (total debit = total kredit). Diposting via buku besar; idempoten (sekali per pesantren). WAJIB dry-run dulu.',
                'extraSummary' => fn (array $s): string => sprintf('Total: Rp %s  •  Seimbang: %s', number_format((float) ($s['total'] ?? 0), 0, ',', '.'), ($s['seimbang'] ?? false) ? 'ya' : 'TIDAK'),
            ]),
            Action::make('terapkan_coa_standar')
                ->label('Terapkan COA Standar')
                ->icon('heroicon-o-sparkles')
                ->color('info')
                ->requiresConfirmation()
                ->modalDescription('Pastikan Bagan Akun standar pesantren tersedia (idempoten — tak menimpa saldo/akun kustom).')
                ->visible(fn (): bool => auth()->user()?->can('manage_ledger') ?? false)
                ->action(function (): void {
                    $n = app(CoaTemplateService::class)->terapkanStandar();
                    Notification::make()->title("COA standar diterapkan ({$n} akun tersedia)")->success()->send();
                }),
        ];
    }
}
