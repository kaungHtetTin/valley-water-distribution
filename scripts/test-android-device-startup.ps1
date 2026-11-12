param(
    [Parameter(Mandatory = $true)][string]$Serial,
    [string]$Version = '1.0.4',
    [ValidateSet('sales', 'supervisor', 'driver')][string[]]$Apps = @('sales', 'supervisor', 'driver'),
    [switch]$AllowPhysicalDevice
)
$ErrorActionPreference = 'Stop'
if ($Serial -notmatch '^emulator-\d+$' -and -not $AllowPhysicalDevice) {
    throw 'Physical-device testing requires -AllowPhysicalDevice and authorization from the device owner.'
}
$projectDirectory = Split-Path -Parent $PSScriptRoot
$adb = Join-Path $env:LOCALAPPDATA 'Android\Sdk\platform-tools\adb.exe'
$outputDirectory = Join-Path $projectDirectory "artifacts\verification\android-$Version-device"
New-Item -ItemType Directory -Force -Path $outputDirectory | Out-Null
function Invoke-Adb {
    param([string[]]$Arguments)
    $result = & $adb -s $Serial @Arguments
    if ($LASTEXITCODE -ne 0) { throw "ADB failed: $($Arguments -join ' ')" }
    return $result
}
$results = @()
foreach ($slug in $Apps) {
    $name = (Get-Culture).TextInfo.ToTitleCase($slug)
    $package = "com.valleymyanmar.$slug"
    $apk = Join-Path $projectDirectory "artifacts\android\Valley-$name-$Version-release.apk"
    # Update in place: never uninstall, clear app data, or change device connectivity.
    Invoke-Adb -Arguments @('install', '-r', $apk) | Out-Null
    for ($attempt = 1; $attempt -le 3; $attempt++) {
        Invoke-Adb -Arguments @('shell', 'am', 'force-stop', $package) | Out-Null
        Invoke-Adb -Arguments @('shell', 'am', 'start', '-n', "$package/com.valleymyanmar.sales.MainActivity") | Out-Null
        $deadline = [DateTime]::UtcNow.AddSeconds(50)
        $ready = $false
        do {
            Start-Sleep -Seconds 2
            Invoke-Adb -Arguments @('shell', 'uiautomator', 'dump', '/sdcard/valley-startup.xml') | Out-Null
            $xml = (Invoke-Adb -Arguments @('shell', 'cat', '/sdcard/valley-startup.xml')) -join "`n"
            $tree = [xml]$xml
            # Xiaomi/POCO may put a completed post-install scan over the launched app.
            $open = $tree.SelectSingleNode('//node[@resource-id="com.miui.global.packageinstaller:id/btn_open_normal" and @text="Open" and @enabled="true"]')
            if ($open) {
                $bounds = [regex]::Match($open.bounds, '\[(\d+),(\d+)\]\[(\d+),(\d+)\]')
                $x = [int](([int]$bounds.Groups[1].Value + [int]$bounds.Groups[3].Value) / 2)
                $y = [int](([int]$bounds.Groups[2].Value + [int]$bounds.Groups[4].Value) / 2)
                Invoke-Adb -Arguments @('shell', 'input', 'tap', "$x", "$y") | Out-Null
                continue
            }
            $processId = (Invoke-Adb -Arguments @('shell', 'pidof', $package)) -join ''
            if (-not $processId) { throw "$name exited during startup." }
            $overlay = $tree.SelectNodes('//node[contains(@resource-id, ":id/connection_title")]')
            $inputs = $tree.SelectNodes('//node[@class="android.widget.EditText"]')
            $ready = $overlay.Count -eq 0 -and $inputs.Count -eq 2 -and $xml.Contains('Sign in to continue')
        } while (-not $ready -and [DateTime]::UtcNow -lt $deadline)
        if (-not $ready) { throw "$name did not reach its login page." }
        # The former crash occurs after React calls the native disconnect method.
        Start-Sleep -Seconds 5
        $null = Invoke-Adb -Arguments @('shell', 'pidof', $package)
        $logs = (Invoke-Adb -Arguments @('logcat', '-d', "--pid=$processId", '-v', 'threadtime')) -join "`n"
        $logs | Set-Content -LiteralPath "$outputDirectory\$slug-$attempt-logcat.txt" -Encoding UTF8
        if ($logs -match 'FATAL EXCEPTION|A WebView method was called on thread') {
            throw "$name produced a startup crash; inspect its saved logcat."
        }
        $xml | Set-Content -LiteralPath "$outputDirectory\$slug-$attempt.xml" -Encoding UTF8
        Invoke-Adb -Arguments @('shell', 'screencap', '-p', '/sdcard/valley-startup.png') | Out-Null
        Invoke-Adb -Arguments @('pull', '/sdcard/valley-startup.png', "$outputDirectory\$slug-$attempt.png") | Out-Null
        $results += "$name cold launch $attempt`: login and both inputs visible; process remains alive without fatal exceptions."
        Write-Output $results[-1]
    }
}
$reportName = if ($Apps.Count -eq 3) { 'report.json' } else { "$($Apps -join '-')-report.json" }
[ordered]@{
    passed = $true
    version = $Version
    apps = $Apps
    model = ((Invoke-Adb -Arguments @('shell', 'getprop', 'ro.product.model')) -join '')
    android = ((Invoke-Adb -Arguments @('shell', 'getprop', 'ro.build.version.release')) -join '')
    checks = $results
    signedInTestsPerformed = $false
} | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath "$outputDirectory\$reportName" -Encoding UTF8
