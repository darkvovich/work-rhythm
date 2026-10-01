$ErrorActionPreference = 'Stop'
# HTTP E2E прогон. Проект КОПИРУЕТСЯ во временную папку, сервер поднимается там же:
# реальные data/ и logs/ проекта не затрагиваются никогда.
# Требования: php в PATH, PowerShell 5.1+, свободный порт 8120-8199.
# Успех: строка "total=N failed=0" и код 0.

$phpCmd = Get-Command php -ErrorAction SilentlyContinue
if (-not $phpCmd) { Write-Output 'FAIL php not found in PATH'; exit 1 }
$php = $phpCmd.Source

$proj = Split-Path -Parent $PSScriptRoot
$tmp = Join-Path ([IO.Path]::GetTempPath()) ('wr-e2e-' + [guid]::NewGuid().ToString('N').Substring(0, 8))

Write-Output 'stage: copy project'
robocopy $proj $tmp /E /XD .git data logs /NFL /NDL /NJH /NJS /NC /NS /NP | Out-Null
if ($LASTEXITCODE -ge 8) { Write-Output "FAIL robocopy code=$LASTEXITCODE"; exit 1 }

$port = 0
for ($p = 8120; $p -lt 8200; $p++) {
    try {
        $probe = New-Object System.Net.Sockets.TcpListener([System.Net.IPAddress]::Loopback, $p)
        $probe.Start(); $probe.Stop(); $port = $p; break
    } catch {}
}
if (-not $port) { Write-Output 'FAIL no free port'; Remove-Item -Recurse -Force $tmp; exit 1 }

$base = "http://127.0.0.1:$port"
$router = Join-Path $tmp 'tests\router.php'
$srvOut = Join-Path $tmp 'server.out.log'
$srvErr = Join-Path $tmp 'server.err.log'
$srv = $null
$script:fail = 0
$script:total = 0
$exitCode = 0

# Чек результатов: печатает сразу, чтобы прогон был виден без тишины.
function Check($name, $ok, $detail) {
    $script:total++
    if (-not $ok) { $script:fail++ }
    Write-Output ('  ' + $(if ($ok) { 'PASS' } else { 'FAIL' }) + '  ' + $name + '  ' + $detail)
}
function Code($r) { if ($r) { return $r.StatusCode } return 'null' }

# Запрос к API с cookie логина. $json = $null для GET.
function Call($method, $path, $json) {
    $p = @{ Uri = $script:base + $path; Method = $method; WebSession = $script:session; UseBasicParsing = $true; SkipHttpErrorCheck = $true; TimeoutSec = 15 }
    if ($null -ne $json) { $p.Body = [System.Text.Encoding]::UTF8.GetBytes($json); $p.ContentType = 'application/json; charset=utf-8' }
    try { return Invoke-WebRequest @p } catch { return $null }
}

try {
    Write-Output "stage: starting server on $base"
    $srv = Start-Process -FilePath $php -ArgumentList "-S 127.0.0.1:$port -t `"$tmp`" `"$router`"" -WindowStyle Hidden -PassThru -RedirectStandardOutput $srvOut -RedirectStandardError $srvErr
    Write-Output ('stage: pid=' + $srv.Id)

    $up = $false
    for ($i = 0; $i -lt 40; $i++) {
        Start-Sleep -Milliseconds 300
        Write-Output "stage: wait $i"
        try { $r = Invoke-WebRequest "$base/login" -UseBasicParsing -TimeoutSec 2; if ($r.StatusCode -eq 200) { $up = $true; break } } catch {}
    }
    if (-not $up) {
        Write-Output 'FAIL server not up'
        $exitCode = 1
    }

    if ($up) {
        Write-Output 'stage: server up'
        $script:session = $null

        Write-Output 'stage: login'
        $login = Invoke-WebRequest -Uri "$base/login" -Method Post -Body @{ username = 'admin'; password = 'password' } -SessionVariable session -UseBasicParsing -SkipHttpErrorCheck -TimeoutSec 10
        $script:session = $session
        Check 'login' ($session.Cookies.GetCookies([Uri]$base).Count -gt 0) ("cookies=" + $session.Cookies.GetCookies([Uri]$base).Count)

        $date = '2026-09-20'
        # Полный день: вечер заполнен, качество фокуса/спад НЕ заполнены (опциональны).
        $json = '{"date":"' + $date + '","day_type":"work","bed_time":"23:00","wake_time":"07:00","sleep_hours":"7.5","sleep_quality":"4","morning_energy":"4","day_readiness":"4","left_home":"0","exercise":"1","exercise_type":"Бег, Йога","exercise_duration":"45","exercise_intensity":"3","focused_work_hours":"4","work_result":"4","evening_energy":"4","tomorrow_motivation":"4","total_work_hours":""}'
        # Послабление: вечерних полей НЕТ, фокус/спад заполнены.
        $jsonNoEvening = '{"date":"' + $date + '","day_type":"work","bed_time":"23:00","wake_time":"07:00","sleep_hours":"7.5","sleep_quality":"4","morning_energy":"4","day_readiness":"4","left_home":"0","exercise":"1","exercise_type":"Бег, Йога","exercise_duration":"45","exercise_intensity":"3","focused_work_hours":"4","work_result":"4","focus_quality":"4","had_dip":"0","total_work_hours":""}'

        Write-Output 'stage: draft + chips + export'
        $r = Call Post '/api/save-day' $json
        Check 'save draft (200)' ($r -and $r.StatusCode -eq 200) ("status=" + (Code $r))

        $r = Call Get ('/day/' + $date) $null
        $html = if ($r) { $r.Content } else { '' }
        Check 'chips rendered: Бег' ($html -match 'name="exercise_type" value="Бег" checked') 'checked'
        Check 'chips rendered: Йога' ($html -match 'name="exercise_type" value="Йога" checked') 'checked'
        Check 'no single select' ($html -notmatch '<select name="exercise_type"') 'select удалён'
        Check 'unchecked chip' ($html -match 'name="exercise_type" value="Силовая"(\s|>)') 'Силовая не отмечена'

        $r = Call Get '/export' $null
        Check 'export comma value' ($r -and $r.Content -match 'Бег,Йога') 'CSV: Бег,Йога'

        Write-Output 'stage: complete + protection'
        $r = Call Post '/api/complete-day' $json
        Check 'complete (200; focus/dip optional)' ($r -and $r.StatusCode -eq 200) ("status=" + (Code $r))

        $r = Call Get ('/day/' + $date) $null
        $html = if ($r) { $r.Content } else { '' }
        Check 'readonly form' ($html -match 'id="day-form"[^>]*class="readonly"') 'форма read-only'
        Check 'reopen button shown' ($html -match 'id="reopen-btn"') 'кнопка Редактировать'

        $r = Call Post '/api/save-day' $json
        Check 'save on completed -> 409' ($r -and $r.StatusCode -eq 409) ("status=" + (Code $r))

        $r = Call Post '/api/complete-day' $json
        Check 're-complete on completed -> 409' ($r -and $r.StatusCode -eq 409) ("status=" + (Code $r))

        Write-Output 'stage: reopen'
        $r = Call Post '/api/reopen-day' ('{"date":"' + $date + '"}')
        Check 'reopen (200)' ($r -and $r.StatusCode -eq 200) ("status=" + (Code $r))

        $r = Call Get ('/day/' + $date) $null
        $html = if ($r) { $r.Content } else { '' }
        Check 'editable after reopen' ($html -notmatch 'id="reopen-btn"' -and $html -notmatch 'id="day-form"[^>]*class="readonly"') 'форма активна'
        Check 'chips persist after reopen' ($html -match 'name="exercise_type" value="Бег" checked' -and $html -match 'name="exercise_type" value="Йога" checked') 'выбор спорта на месте'

        Write-Output 'stage: required fields relaxation'
        $r = Call Post '/api/complete-day' $jsonNoEvening
        Check 'complete without evening fields (200)' ($r -and $r.StatusCode -eq 200) ("status=" + (Code $r))

        $r = Call Post '/api/reopen-day' ('{"date":"' + $date + '"}')
        Check 'reopen after no-evening complete (200)' ($r -and $r.StatusCode -eq 200) ("status=" + (Code $r))

        $r = Call Post '/api/reopen-day' ('{"date":"' + $date + '"}')
        Check 'reopen draft -> 409' ($r -and $r.StatusCode -eq 409) ("status=" + (Code $r))

        $r = Call Post '/api/reopen-day' ('{"date":"2026-12-01"}')
        Check 'reopen future -> 403' ($r -and $r.StatusCode -eq 403) ("status=" + (Code $r))

        $r = Call Post '/api/reopen-day' ('{"date":"bad"}')
        Check 'reopen bad date -> 400' ($r -and $r.StatusCode -eq 400) ("status=" + (Code $r))

        $r = Call Post '/api/reopen-day' ('{"date":"2000-01-01"}')
        Check 'reopen missing day -> 409' ($r -and $r.StatusCode -eq 409) ("status=" + (Code $r))

        Write-Output ("total=" + $script:total + " failed=" + $script:fail)
        if ($script:fail -gt 0) { $exitCode = 1 }
    }
} finally {
    if ($srv -and -not $srv.HasExited) { Stop-Process -Id $srv.Id -Force -ErrorAction SilentlyContinue }
    if ($exitCode -ne 0 -and (Test-Path $srvErr)) {
        Get-Content $srvErr -Tail 30 | ForEach-Object { Write-Output ("server-err: " + $_) }
    }
    Remove-Item -Recurse -Force $tmp -ErrorAction SilentlyContinue
}
exit $exitCode
