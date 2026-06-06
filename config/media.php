<?php

return [
    /*
     * Disk penyimpanan media. Default 'public' (disk lokal, di-suffix per tenant
     * oleh FilesystemTenancyBootstrapper). Untuk tier besar (64-256 GB), ganti ke
     * disk S3-compatible (object storage) lewat env MEDIA_DISK=s3.
     */
    'disk' => env('MEDIA_DISK', 'public'),

    /* Batas ukuran per file (MB). */
    'max_file_mb' => (int) env('MEDIA_MAX_FILE_MB', 100),

    /*
     * Set true bila object storage (S3) sudah dikonfigurasi. Membuka tier kuota
     * 64/128/256 GB di menu Kuota Penyimpanan. Default false → tier itu "Soon".
     */
    'object_storage' => (bool) env('MEDIA_OBJECT_STORAGE', false),

    /*
     * Nama disk (config/filesystems.php) yang dipakai saat object_storage aktif.
     * StorageQuotaService::diskName() otomatis beralih ke sini begitu object
     * storage siap — tak perlu lagi mengubah MEDIA_DISK secara manual.
     */
    'object_storage_disk' => env('MEDIA_OBJECT_STORAGE_DISK', 's3'),
];
