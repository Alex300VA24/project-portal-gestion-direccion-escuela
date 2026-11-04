param(
    [switch] $KeepEnvironment
)

$ErrorActionPreference = 'Stop'

$validationProject = 'portal_modular_validation'
$validationVolume = 'portal_modular_validation_postgresql_data'
$validationAppPort = '18000'
$validationPostgresPort = '15433'
$validationDatabase = if ($env:POSTGRES_DB) { $env:POSTGRES_DB } else { 'portal_gestion_direccion' }
$validationUser = if ($env:POSTGRES_USER) { $env:POSTGRES_USER } else { 'postgres' }

$env:APP_HTTP_PORT = $validationAppPort
$env:POSTGRES_HOST_PORT = $validationPostgresPort
$env:POSTGRES_VOLUME_NAME = $validationVolume

try {
    docker compose -p $validationProject up -d postgresql
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo iniciar PostgreSQL.' }

    docker compose -p $validationProject build app
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo construir la aplicación.' }

    docker compose -p $validationProject run --rm app php artisan module:migrate all --force
    if ($LASTEXITCODE -ne 0) { throw 'Falló la primera migración modular.' }

    docker compose -p $validationProject run --rm app php artisan module:migrate all --force
    if ($LASTEXITCODE -ne 0) { throw 'La segunda migración modular no fue idempotente.' }

    docker compose -p $validationProject run --rm app php artisan module:migrate:status all
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo consultar el estado modular.' }

    Get-Content -Raw tests\Database\modular_database_smoke.sql |
        docker compose -p $validationProject exec -T postgresql `
            psql -v ON_ERROR_STOP=1 -U $validationUser -d $validationDatabase
    if ($LASTEXITCODE -ne 0) { throw 'Fallaron las pruebas SQL modulares.' }

    $migrationPaths = @(
        'app/Modules/SeguimientoEgresado/Database/Migrations',
        'app/Modules/WebInstitucional/Database/Migrations',
        'app/Modules/SeguimientoEstudiante/Database/Migrations',
        'app/Modules/InvestigacionFormativa/Database/Migrations',
        'app/Modules/Bienestar/Database/Migrations',
        'app/Modules/GestionMatriculas/Database/Migrations',
        'app/Modules/TesisProyectoTesis/Database/Migrations',
        'app/Modules/GestionPracticas/Database/Migrations',
        'app/Modules/Cotecu/Database/Migrations',
        'app/Modules/GestionHorarios/Database/Migrations',
        'app/Modules/Calidad/Database/Migrations',
        'app/Modules/PreMatricula/Database/Migrations',
        'app/Shared/Database/Migrations',
        'database/migrations'
    )

    foreach ($migrationPath in $migrationPaths) {
        docker compose -p $validationProject run --rm app `
            php artisan migrate:rollback --path=$migrationPath --force --no-interaction
        if ($LASTEXITCODE -ne 0) { throw "Falló rollback: $migrationPath" }
    }

    docker compose -p $validationProject run --rm app php artisan module:migrate all --force
    if ($LASTEXITCODE -ne 0) { throw 'Falló la reconstrucción posterior al rollback.' }

    Get-Content -Raw tests\Database\modular_database_smoke.sql |
        docker compose -p $validationProject exec -T postgresql `
            psql -v ON_ERROR_STOP=1 -U $validationUser -d $validationDatabase
    if ($LASTEXITCODE -ne 0) { throw 'Falló la verificación posterior al rollback.' }

    docker compose -p $validationProject up -d app
    if ($LASTEXITCODE -ne 0) { throw 'La aplicación no inició después de migrar.' }

    docker compose -p $validationProject ps
    docker compose -p $validationProject logs --no-color --tail 100 app postgresql
}
finally {
    if (-not $KeepEnvironment) {
        docker compose -p $validationProject down --volumes --remove-orphans
    }
}
