param([string]$Output = (Join-Path (Split-Path $PSScriptRoot -Parent) ('storage\backups\enfermeria_' + (Get-Date -Format 'yyyyMMdd_HHmmss') + '.sql')))
$app = Split-Path $PSScriptRoot -Parent
$envFile = Join-Path $app '.env'
if(!(Test-Path -LiteralPath $envFile)){throw 'No existe .env local; no se puede generar respaldo.'}
$values=@{}; Get-Content $envFile | ForEach-Object { if($_ -match '^\s*([^#=]+)=(.*)$'){$values[$Matches[1].Trim()]=$Matches[2].Trim().Trim('"')}}
$dir=Split-Path $Output -Parent; New-Item -ItemType Directory -Force -Path $dir | Out-Null
& 'C:\xampp\mysql\bin\mysqldump.exe' ('--host=' + $values.DB_HOST) ('--port=' + $values.DB_PORT) ('--user=' + $values.DB_USERNAME) ('--password=' + $values.DB_PASSWORD) --single-transaction --routines --triggers $values.DB_DATABASE | Set-Content -LiteralPath $Output -Encoding UTF8
if($LASTEXITCODE -ne 0){throw 'mysqldump no pudo completar el respaldo.'}; Write-Output "Respaldo creado: $Output"
