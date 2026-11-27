# Deploy produksi melalui VPS

## Jalur yang aktif

Pemeriksaan Hostinger pada 27 November 2026 menemukan aplikasi di `/opt/pesantren-platform`, branch produksi `main`, dan script `/opt/deploy.sh`. VPS menarik perubahan dari `origin/main`; GitHub Actions tidak mendorong source ke server.

Cron khusus `/etc/cron.d/pesantren-platform-autodeploy` memanggil script itu setiap lima menit. Perintah dijalankan dengan `flock` agar dua proses deploy tidak berjalan bersamaan. Hasil proses tercatat di `/var/log/pesantren-platform-autodeploy.log`.

Script memeriksa apakah `HEAD` sudah sama dengan `origin/main`. Jika sama, ia berhenti tanpa membangun ulang aplikasi. Jika berbeda, ia menyamakan working tree dengan `origin/main`, membangun image aplikasi, memulai ulang container, dan menjalankan migrasi serta langkah pemulihan cache/perizinan yang ada di script.

## Alur pembaruan

1. Perubahan harus sudah masuk ke branch `main` di GitHub. Commit pada branch lokal atau branch fitur tidak akan dipasang di produksi.
2. Cron VPS memeriksa `main` pada jadwal berikutnya, paling lambat sekitar lima menit setelah perubahan tersedia.
3. VPS menjalankan script deploy dan mencatat hasilnya di log.
4. Operator memeriksa log deploy dan status container aplikasi setelah perubahan penting.

GitHub workflow [deploy.yml](../.github/workflows/deploy.yml) hanya memiliki `workflow_dispatch`. Workflow itu bukan pemicu deploy otomatis produksi. Dokumentasi ini mencatat jalur yang terverifikasi pada tanggal di atas; periksa VPS lagi sebelum mengubah jalur deploy.

## Dampak operasional

Script memakai `git reset --hard origin/main`, sehingga perubahan berkas yang dilacak Git dan dibuat langsung di VPS dapat tertimpa. Proses juga membangun ulang lalu memulai ulang container aplikasi, jadi pengguna dapat mengalami jeda singkat. Migrasi production berjalan otomatis saat ada commit baru.

Simpan `.env`, data tenant, dan berkas persisten di luar perubahan kode. Jangan mencetak rahasia ke log. Jangan menjalankan deploy manual sebagai cara uji; gunakan alur perubahan yang sudah disetujui dan pantau hasilnya.

## Diagnostik

Di VPS, operator dapat melihat log deploy dan status aplikasi dengan perintah berikut:

```bash
tail -n 30 /var/log/pesantren-platform-autodeploy.log
docker compose -f /opt/pesantren-platform/docker-compose.production.yml ps app
```

Perintah ini hanya untuk pemeriksaan. Jangan menjalankan `git reset`, migrasi, atau `docker compose up` manual sebagai uji coba.
