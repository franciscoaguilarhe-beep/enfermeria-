param([string]$Output = (Join-Path (Split-Path $PSScriptRoot -Parent) 'release\enfermeria-srae.zip'))
$app = Split-Path $PSScriptRoot -Parent
$parent = Split-Path $Output -Parent
New-Item -ItemType Directory -Force -Path $parent | Out-Null
$items = @('README.md','.env.example','.gitignore','composer.json','composer.lock','config','database','docs','public','scripts','src','templates','tests','vendor') | ForEach-Object { Join-Path $app $_ }
Compress-Archive -LiteralPath $items -DestinationPath $Output -CompressionLevel Optimal -Force
Write-Output "Paquete limpio creado: $Output"
Write-Output "No incluye .env, storage, tmp, logs, sesiones ni backups porque no forman parte de la whitelist."
