param(
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]] $TestArguments
)

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$workspaceRoot = Split-Path $projectRoot -Parent
$runtimeRoot = Join-Path $workspaceRoot '.runtime'
$php = Join-Path $runtimeRoot 'php-8.3.37/php.exe'
$postgresRoot = Join-Path $runtimeRoot 'postgresql-16.16/pgsql'
$pgCtl = Join-Path $postgresRoot 'bin/pg_ctl.exe'
$psql = Join-Path $postgresRoot 'bin/psql.exe'
$pgReady = Join-Path $postgresRoot 'bin/pg_isready.exe'
$pgData = Join-Path $runtimeRoot 'postgresql-16.16/data'
$pgLog = Join-Path $runtimeRoot 'postgresql-16.16/postgres-test.log'
$databaseName = 'pesantren_test_asrama_solo'
$databasePassword = 'secret'

if (-not (Test-Path -LiteralPath $php)) {
    $phpCommand = Get-Command php -ErrorAction SilentlyContinue
    if ($null -eq $phpCommand) {
        throw "PHP 8.3 belum tersedia. Letakkan runtime PHP pada $php atau tambahkan php ke PATH."
    }
    $php = $phpCommand.Source
}

if (-not (Test-Path -LiteralPath $psql) -or -not (Test-Path -LiteralPath $pgReady)) {
    throw "Binary PostgreSQL lokal tidak ditemukan di $postgresRoot."
}

$serverReady = $false
& $pgReady -h 127.0.0.1 -p 5432 *> $null
$serverReady = $LASTEXITCODE -eq 0

if (-not $serverReady -and (Test-Path -LiteralPath $pgCtl) -and (Test-Path -LiteralPath (Join-Path $pgData 'PG_VERSION'))) {
    & $pgCtl -D $pgData -l $pgLog -o '-p 5432 -h 127.0.0.1' start
    if ($LASTEXITCODE -ne 0) {
        throw 'PostgreSQL lokal tidak dapat dijalankan. Jalankan skrip ini dari PowerShell di luar sandbox jika Windows menolak restricted token.'
    }
}

$previousPassword = $env:PGPASSWORD
try {
    $env:PGPASSWORD = $databasePassword
    & $pgReady -h 127.0.0.1 -p 5432 -U postgres
    if ($LASTEXITCODE -ne 0) {
        throw 'PostgreSQL belum siap pada 127.0.0.1:5432.'
    }

    $databaseExists = (& $psql -h 127.0.0.1 -p 5432 -U postgres -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname = '$databaseName'").Trim()
    if ($LASTEXITCODE -ne 0) {
        throw 'Tidak dapat memeriksa database pengujian PostgreSQL.'
    }
    if ($databaseExists -ne '1') {
        & (Join-Path $postgresRoot 'bin/createdb.exe') -h 127.0.0.1 -p 5432 -U postgres $databaseName
        if ($LASTEXITCODE -ne 0) {
            throw "Tidak dapat membuat database khusus pengujian $databaseName."
        }
    }
}
finally {
    if ($null -eq $previousPassword) {
        Remove-Item Env:PGPASSWORD -ErrorAction SilentlyContinue
    } else {
        $env:PGPASSWORD = $previousPassword
    }
}

$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'pgsql'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '5432'
$env:DB_DATABASE = $databaseName
$env:DB_USERNAME = 'postgres'
$env:DB_PASSWORD = $databasePassword
$env:TEST_DB_DATABASE = $databaseName

Push-Location $projectRoot
try {
    & $php -d memory_limit=512M artisan test @TestArguments
    exit $LASTEXITCODE
}
finally {
    Pop-Location
}
