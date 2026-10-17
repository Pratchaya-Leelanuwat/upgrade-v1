$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot

Write-Host 'Rebuilding and restarting only the web service. The database volume is preserved.' -ForegroundColor Cyan
docker compose up -d --build --force-recreate web
if ($LASTEXITCODE -ne 0) {
    throw 'Docker Compose could not rebuild the web service. Check that Docker Desktop is running.'
}

Write-Host ''
Write-Host 'Running the HTTP smoke checks...' -ForegroundColor Cyan
& (Join-Path $PSScriptRoot 'tests\security-smoke.ps1')
if ($LASTEXITCODE -ne 0) {
    throw 'The smoke checks failed.'
}

Write-Host ''
Write-Host 'Update and smoke checks completed.' -ForegroundColor Green
