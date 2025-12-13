# Script to find printer on network
Write-Host "Scanning network for printer on 192.168.1.0/24..." -ForegroundColor Yellow
Write-Host ""

# Scan common printer ports
$ports = @(9100, 515, 631, 80)
$found = $false

for ($i = 1; $i -le 254; $i++) {
    $ip = "192.168.1.$i"
    Write-Host "Checking $ip..." -NoNewline
    
    $ping = Test-Connection -ComputerName $ip -Count 1 -Quiet -ErrorAction SilentlyContinue
    
    if ($ping) {
        Write-Host " [PING OK]" -ForegroundColor Green -NoNewline
        
        foreach ($port in $ports) {
            $test = Test-NetConnection -ComputerName $ip -Port $port -WarningAction SilentlyContinue -InformationLevel Quiet -ErrorAction SilentlyContinue
            if ($test) {
                Write-Host " [PORT $port OPEN]" -ForegroundColor Green
                Write-Host "  -> Found device at $ip on port $port" -ForegroundColor Cyan
                $found = $true
                break
            }
        }
        
        if (-not $found) {
            Write-Host " [No printer ports open]" -ForegroundColor Yellow
        }
    } else {
        Write-Host " [No response]" -ForegroundColor Gray
    }
}

if (-not $found) {
    Write-Host ""
    Write-Host "No printer found on network." -ForegroundColor Red
    Write-Host "Please check:" -ForegroundColor Yellow
    Write-Host "  1. Printer is powered on" -ForegroundColor White
    Write-Host "  2. Ethernet cable is connected" -ForegroundColor White
    Write-Host "  3. Printer network is enabled" -ForegroundColor White
    Write-Host "  4. Printer IP configuration" -ForegroundColor White
}

