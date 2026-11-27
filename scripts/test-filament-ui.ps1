param(
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]] $TestArguments
)

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$runtimeRoot = Join-Path (Split-Path $projectRoot -Parent) '.runtime'
$php = Join-Path $runtimeRoot 'php-8.3.37/php.exe'
$postgresRoot = Join-Path $runtimeRoot 'postgresql-16.16/pgsql'
$pgReady = Join-Path $postgresRoot 'bin/pg_isready.exe'
$psql = Join-Path $postgresRoot 'bin/psql.exe'
$createDb = Join-Path $postgresRoot 'bin/createdb.exe'
$databaseName = 'pesantren_test_filament_ui_20261127'
$databasePassword = 'secret'

if (-not (Test-Path -LiteralPath $php)) {
    $phpCommand = Get-Command php -ErrorAction SilentlyContinue
    if ($null -eq $phpCommand) {
        throw "PHP 8.3 belum tersedia. Letakkan runtime PHP pada $php atau tambahkan php ke PATH."
    }
    $php = $phpCommand.Source
}

if (-not (Test-Path -LiteralPath $pgReady) -or -not (Test-Path -LiteralPath $psql) -or -not (Test-Path -LiteralPath $createDb)) {
    throw "Binary PostgreSQL lokal tidak ditemukan di $postgresRoot."
}

$environmentNames = @(
    'APP_ENV', 'APP_KEY', 'APP_DEBUG', 'DB_CONNECTION', 'DB_HOST', 'DB_PORT',
    'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'TEST_DB_DATABASE',
    'CACHE_STORE', 'SESSION_DRIVER', 'QUEUE_CONNECTION', 'MAIL_MAILER', 'PGPASSWORD'
)
$previousEnvironment = @{}
foreach ($name in $environmentNames) {
    $previousEnvironment[$name] = [Environment]::GetEnvironmentVariable($name, 'Process')
}

try {
    $env:PGPASSWORD = $databasePassword
    & $pgReady -h 127.0.0.1 -p 5432 -U postgres *> $null
    if ($LASTEXITCODE -ne 0) {
        throw 'PostgreSQL lokal belum siap pada 127.0.0.1:5432.'
    }

    $exists = (& $psql -h 127.0.0.1 -p 5432 -U postgres -d postgres -tAc "SELECT EXISTS (SELECT 1 FROM pg_database WHERE datname = '$databaseName')").Trim()
    if ($LASTEXITCODE -ne 0) {
        throw 'Tidak dapat memeriksa database khusus uji UI Filament.'
    }
    if ($exists -ne 't') {
        & $createDb -h 127.0.0.1 -p 5432 -U postgres $databaseName
        if ($LASTEXITCODE -ne 0) {
            throw 'Tidak dapat membuat database khusus uji UI Filament.'
        }
    }

    $env:APP_ENV = 'testing'
    $env:APP_KEY = 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA='
    $env:APP_DEBUG = 'true'
    $env:DB_CONNECTION = 'pgsql'
    $env:DB_HOST = '127.0.0.1'
    $env:DB_PORT = '5432'
    $env:DB_DATABASE = $databaseName
    $env:DB_USERNAME = 'postgres'
    $env:DB_PASSWORD = $databasePassword
    $env:TEST_DB_DATABASE = $databaseName
    $env:CACHE_STORE = 'array'
    $env:SESSION_DRIVER = 'array'
    $env:QUEUE_CONNECTION = 'sync'
    $env:MAIL_MAILER = 'array'

    Push-Location $projectRoot
    try {
        & $php artisan test @TestArguments
        if ($LASTEXITCODE -ne 0) {
            exit $LASTEXITCODE
        }
        Write-Output 'FILAMENT_UI_TEST_PASSED'
    } finally {
        Pop-Location
    }
} finally {
    foreach ($name in $environmentNames) {
        if ($null -eq $previousEnvironment[$name]) {
            Remove-Item "Env:$name" -ErrorAction SilentlyContinue
        } else {
            [Environment]::SetEnvironmentVariable($name, $previousEnvironment[$name], 'Process')
        }
    }
}
