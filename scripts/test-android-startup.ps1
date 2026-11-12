param([string]$Serial = 'emulator-5556', [string]$Version = '1.0.4')
$ErrorActionPreference = 'Stop'
if ($Serial -notmatch '^emulator-\d+$') { throw 'This automated test only runs on an emulator, never on a connected phone.' }
$projectDirectory = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $projectDirectory
$adb = Join-Path $env:LOCALAPPDATA 'Android\Sdk\platform-tools\adb.exe'
$outputDirectory = Join-Path $projectDirectory "artifacts\verification\android-$Version"
New-Item -ItemType Directory -Force -Path $outputDirectory | Out-Null
function Invoke-Adb {
    param([string[]]$Arguments)
    $previousPreference = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try { $result = & $adb -s $Serial @Arguments 2>&1; $exit = $LASTEXITCODE }
    finally { $ErrorActionPreference = $previousPreference }
    if ($exit -ne 0) { throw "ADB failed: $($Arguments -join ' ') $result" }
    return $result
}
function Get-AppTree {
    Invoke-Adb -Arguments @('shell','uiautomator','dump','/sdcard/valley-runtime.xml') | Out-Null
    Invoke-Adb -Arguments @('pull','/sdcard/valley-runtime.xml',"$outputDirectory\current.xml") | Out-Null
    return [xml](Get-Content -LiteralPath "$outputDirectory\current.xml" -Raw)
}
function Wait-AppText {
    param([string]$Text, [int]$TimeoutSeconds = 45)
    $deadline = [DateTime]::UtcNow.AddSeconds($TimeoutSeconds)
    while ([DateTime]::UtcNow -lt $deadline) {
        $tree = Get-AppTree
        if ($Text -eq 'Sign in to continue' -and $tree.SelectNodes('//node[contains(@resource-id, ":id/connection_title")]').Count -gt 0) { continue }
        if ($tree.SelectNodes('//node') | Where-Object { $_.text -eq $Text -or $_.'content-desc' -eq $Text }) { return $tree }
    }
    throw "Android screen did not show '$Text' within $TimeoutSeconds seconds."
}
function Save-AppScreen {
    param([string]$Name)
    Invoke-Adb -Arguments @('shell','screencap','-p','/sdcard/valley-runtime.png') | Out-Null
    Invoke-Adb -Arguments @('pull','/sdcard/valley-runtime.png',"$outputDirectory\$Name.png") | Out-Null
    Copy-Item -LiteralPath "$outputDirectory\current.xml" -Destination "$outputDirectory\$Name.xml" -Force
}
$results = @()
foreach ($slug in @('sales','supervisor','driver')) {
    $name = (Get-Culture).TextInfo.ToTitleCase($slug)
    $package = "com.valleymyanmar.$slug"
    $apk = Join-Path $projectDirectory "artifacts\android\Valley-$name-$Version-release.apk"
    Invoke-Adb -Arguments @('install','-r',$apk) | Out-Null
    Invoke-Adb -Arguments @('shell','am','force-stop',$package) | Out-Null
    Invoke-Adb -Arguments @('shell','am','start','-W','-n',"$package/com.valleymyanmar.sales.MainActivity") | Out-Null
    $tree = Wait-AppText -Text 'Sign in to continue'
    $fields = @($tree.SelectNodes('//node[@class="android.widget.EditText"]'))
    if ($fields.Count -ne 2) { throw "$name is missing its email/password inputs." }
    Save-AppScreen -Name "$slug-login"
    $results += "$name release APK: real login page and both inputs visible."
    Write-Output $results[-1]
}
# A disconnected emulator must show a native recovery screen instead of an empty WebView.
try {
    Invoke-Adb -Arguments @('shell','svc','wifi','disable') | Out-Null
    Invoke-Adb -Arguments @('shell','svc','data','disable') | Out-Null
    Invoke-Adb -Arguments @('shell','am','force-stop','com.valleymyanmar.driver') | Out-Null
    Invoke-Adb -Arguments @('shell','am','start','-W','-n','com.valleymyanmar.driver/com.valleymyanmar.sales.MainActivity') | Out-Null
    $tree = Wait-AppText -Text 'Unable to open Valley'
    Save-AppScreen -Name 'driver-offline-recovery'
    $results += 'Disconnected launch shows native recovery UI.'
} finally {
    Invoke-Adb -Arguments @('shell','svc','wifi','enable') | Out-Null
    Invoke-Adb -Arguments @('shell','svc','data','enable') | Out-Null
}
# Enabling the radio returns before Android reconnects; wait for a validated default network.
$networkDeadline = [DateTime]::UtcNow.AddSeconds(30)
do {
    $connectivity = (Invoke-Adb -Arguments @('shell','dumpsys','connectivity')) -join "`n"
    $networkReady = $connectivity -match 'Active default network: \d+' -and $connectivity -match 'VALIDATED'
    if (-not $networkReady) { Start-Sleep -Milliseconds 500 }
} while (-not $networkReady -and [DateTime]::UtcNow -lt $networkDeadline)
if (-not $networkReady) { throw 'The emulator did not reconnect to a validated network.' }
$retry = $tree.SelectNodes('//node') | Where-Object { $_.'resource-id' -eq 'com.valleymyanmar.driver:id/connection_retry' } | Select-Object -First 1
if (-not $retry) { throw 'Missing native retry button.' }
$bounds = [regex]::Match($retry.bounds, '\[(\d+),(\d+)\]\[(\d+),(\d+)\]')
$x = [int](([int]$bounds.Groups[1].Value + [int]$bounds.Groups[3].Value) / 2)
$y = [int](([int]$bounds.Groups[2].Value + [int]$bounds.Groups[4].Value) / 2)
Invoke-Adb -Arguments @('shell','input','tap',"$x","$y") | Out-Null
$null = Wait-AppText -Text 'Sign in to continue' -TimeoutSeconds 60
Save-AppScreen -Name 'driver-recovered-login'
$results += 'Retry after reconnecting returns to the live login screen.'
[ordered]@{ passed = $true; version = $Version; serial = $Serial; android = ((Invoke-Adb -Arguments @('shell','getprop','ro.build.version.release')) -join ''); checks = $results; signedInTestsPerformed = $false } | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath "$outputDirectory\report.json" -Encoding UTF8
$results | ForEach-Object { Write-Output $_ }
