param(
    [ValidateRange(1024, 65535)]
    [int] $Port = 8000,
    [switch] $CreateSuperAdmin
)

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$workspaceRoot = Split-Path $projectRoot -Parent
$php = Join-Path $workspaceRoot '.runtime/php-8.3.37/php.exe'
$postgresRoot = Join-Path $workspaceRoot '.runtime/postgresql-16.16/pgsql'
$pgReady = Join-Path $postgresRoot 'bin/pg_isready.exe'
$databaseName = 'pesantren_test_asrama_solo'

if (-not (Test-Path -LiteralPath $php)) {
    $phpCommand = Get-Command php -ErrorAction SilentlyContinue
    if ($null -eq $phpCommand) {
        throw "PHP 8.3 belum tersedia. Letakkan runtime PHP pada $php atau tambahkan php ke PATH."
    }
    $php = $phpCommand.Source
}

if (-not (Test-Path -LiteralPath $pgReady)) {
    throw "Binary PostgreSQL lokal tidak ditemukan di $postgresRoot."
}

$listener = [System.Net.Sockets.TcpClient]::new()
try {
    $listener.Connect('127.0.0.1', $Port)
    throw "Port $Port sudah digunakan. Pilih port lain dengan -Port."
} catch [System.Net.Sockets.SocketException] {
    # Port tidak memiliki listener; aplikasi dapat dimulai.
} finally {
    $listener.Dispose()
}

$processVariables = @{
    APP_KEY = ''
    APP_ENV = 'local'
    APP_DEBUG = 'true'
    APP_URL = "http://127.0.0.1:$Port"
    DB_CONNECTION = 'pgsql'
    DB_HOST = '127.0.0.1'
    DB_PORT = '5432'
    DB_DATABASE = $databaseName
    DB_USERNAME = 'postgres'
    DB_PASSWORD = 'secret'
    TEST_DB_DATABASE = $databaseName
    CACHE_STORE = 'array'
    SESSION_DRIVER = 'file'
    QUEUE_CONNECTION = 'sync'
    BROADCAST_CONNECTION = 'log'
    MAIL_MAILER = 'log'
    PGPASSWORD = 'secret'
}
$previousVariables = @{}
foreach ($name in $processVariables.Keys) {
    $previousVariables[$name] = [Environment]::GetEnvironmentVariable($name, 'Process')
    [Environment]::SetEnvironmentVariable($name, $processVariables[$name], 'Process')
}

try {
    & $pgReady -h 127.0.0.1 -p 5432 -U postgres -d $databaseName
    if ($LASTEXITCODE -ne 0) {
        throw "Database khusus uji $databaseName belum siap. Jalankan scripts/test-local.ps1 lebih dahulu."
    }

    $randomBytes = New-Object byte[] 32
    $randomGenerator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try { $randomGenerator.GetBytes($randomBytes) } finally { $randomGenerator.Dispose() }
    $env:APP_KEY = 'base64:' + [Convert]::ToBase64String($randomBytes)

    Push-Location $projectRoot
    try {
        & $php artisan migrate --force --no-interaction
        if ($LASTEXITCODE -ne 0) {
            throw 'Migrasi database uji gagal.'
        }

        if ($CreateSuperAdmin) {
            Write-Host 'Akun hanya dibuat pada database uji lokal. Sandi diminta secara tersembunyi.'
            & $php artisan platform:super-admin 'superadmin@pesantren.test' --name='Superadmin Uji Lokal' --reset-password
            if ($LASTEXITCODE -ne 0) {
                throw 'Pembuatan akun superadmin uji lokal gagal.'
            }
        }

        Write-Host "Panel lokal: http://127.0.0.1:$Port/platform"
        Write-Host 'Email MFA lokal dikirim ke storage/logs/laravel.log.'
        Write-Host "Database terikat ke: $databaseName"
        Write-Host 'PHP request timeout lokal: 120 detik.'

        # Run PHP's local server directly so the request timeout is applied to
        # the web SAPI process (artisan serve starts a separate PHP process).
        $router = Join-Path $projectRoot 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'
        Push-Location (Join-Path $projectRoot 'public')
        try {
            & $php -d max_execution_time=120 -S "127.0.0.1:$Port" $router
        } finally {
            Pop-Location
        }
        if ($LASTEXITCODE -ne 0) {
            throw 'Server Laravel berhenti dengan galat.'
        }
    } finally {
        Pop-Location
    }
} finally {
    foreach ($name in $processVariables.Keys) {
        [Environment]::SetEnvironmentVariable($name, $previousVariables[$name], 'Process')
    }
}
