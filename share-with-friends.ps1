$ErrorActionPreference = 'Stop'

Set-Location -LiteralPath $PSScriptRoot

Write-Host 'Starting the Cloudflare Quick Tunnel...' -ForegroundColor Cyan
docker compose --profile internet-share up -d cloudflared
if ($LASTEXITCODE -ne 0) {
    throw 'Could not start the tunnel. Check that Docker Desktop is running.'
}

$deadline = (Get-Date).AddSeconds(60)
$publicUrl = $null
while ((Get-Date) -lt $deadline -and -not $publicUrl) {
    $logText = docker compose logs --no-color --tail=100 cloudflared 2>&1 | Out-String
    if ($logText -match 'https://[a-zA-Z0-9-]+\.trycloudflare\.com') {
        $publicUrl = $Matches[0]
        break
    }
    Start-Sleep -Seconds 2
}

if (-not $publicUrl) {
    Write-Host 'Tunnel URL was not found in the logs. Recent tunnel logs:' -ForegroundColor Yellow
    docker compose logs --no-color --tail=30 cloudflared
    throw 'Tunnel did not provide a public URL within 60 seconds.'
}

Write-Host ''
Write-Host "Public URL: $publicUrl" -ForegroundColor Green
Write-Host 'Send this URL to your friends. Keep Docker Desktop and this PC running.'
Write-Host 'This is a temporary URL; it can change if the tunnel is recreated.'
Write-Host 'To stop sharing, run: docker compose stop cloudflared'
