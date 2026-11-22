param([Parameter(Mandatory=$true)][string]$InputFile)
$app = Split-Path $PSScriptRoot -Parent; $envFile=Join-Path $app '.env'; if(!(Test-Path -LiteralPath $InputFile)){throw 'No existe el archivo de respaldo.'}; if(!(Test-Path -LiteralPath $envFile)){throw 'No existe .env local.'}
$values=@{}; Get-Content $envFile | ForEach-Object { if($_ -match '^\s*([^#=]+)=(.*)$'){$values[$Matches[1].Trim()]=$Matches[2].Trim().Trim('"')}}
Write-Warning 'La restauración sobrescribe el contenido de la base indicada. Verifica el archivo y usa una ventana de mantenimiento.'
Get-Content -LiteralPath $InputFile -Raw | & 'C:\xampp\mysql\bin\mysql.exe' ('--host=' + $values.DB_HOST) ('--port=' + $values.DB_PORT) ('--user=' + $values.DB_USERNAME) ('--password=' + $values.DB_PASSWORD) $values.DB_DATABASE
if($LASTEXITCODE -ne 0){throw 'mysql no pudo completar la restauración.'}; Write-Output 'Restauración finalizada.'
