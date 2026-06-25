<?php

namespace Modules\PPDB\Support;

/**
 * Registry tipe dokumen PPDB — sumber kebenaran label + wajib/opsional untuk
 * checklist berkas (DOC-4) & validasi. Selaras dgn kunci yang diterima
 * PpdbDocumentUploadService (ijazah, akta_kelahiran, foto, kartu_keluarga,
 * rapor, surat_keterangan). Aditif & pure — tak menyentuh service/UI tim.
 */
class PpdbDocumentTypes
{
    /** @var array<string,array{label:string,required:bool}> */
    public const TYPES = [
        'akta_kelahiran' => ['label' => 'Akta Kelahiran', 'required' => true],
        'kartu_keluarga' => ['label' => 'Kartu Keluarga', 'required' => true],
        'foto' => ['label' => 'Pas Foto', 'required' => true],
        'ijazah' => ['label' => 'Ijazah / SKL', 'required' => false],
        'rapor' => ['label' => 'Rapor', 'required' => false],
        'surat_keterangan' => ['label' => 'Surat Keterangan', 'required' => false],
    ];

    /** @return array<string,array{label:string,required:bool}> */
    public static function all(): array
    {
        return self::TYPES;
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::TYPES);
    }

    /** @return list<string> kunci dokumen WAJIB */
    public static function required(): array
    {
        return array_values(array_keys(array_filter(self::TYPES, fn (array $m): bool => $m['required'])));
    }

    /** @return list<string> kunci dokumen OPSIONAL */
    public static function optional(): array
    {
        return array_values(array_keys(array_filter(self::TYPES, fn (array $m): bool => ! $m['required'])));
    }

    public static function isKnown(string $key): bool
    {
        return isset(self::TYPES[$key]);
    }

    public static function isRequired(string $key): bool
    {
        return (bool) (self::TYPES[$key]['required'] ?? false);
    }

    public static function label(string $key): string
    {
        return self::TYPES[$key]['label'] ?? $key;
    }

    /**
     * Dokumen WAJIB yang belum diunggah, dari daftar jenis yang sudah ada.
     *
     * @param  iterable<string>  $uploadedTypes  jenis_dokumen yang sudah terunggah
     * @return list<string> kunci wajib yang masih kurang
     */
    public static function missingRequired(iterable $uploadedTypes): array
    {
        $have = [];
        foreach ($uploadedTypes as $t) {
            $have[$t] = true;
        }

        return array_values(array_filter(self::required(), fn (string $k): bool => ! isset($have[$k])));
    }
}
