param([switch]$DebugBuild, [ValidateSet('all', 'sales', 'supervisor', 'driver')][string]$App = 'all')
$ErrorActionPreference = 'Stop'
$env:DEBUG = $null
$projectDirectory = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $projectDirectory
$javaCandidates = @($env:JAVA_HOME, "$env:ProgramFiles\Android\Android Studio2\jbr", "$env:ProgramFiles\Android\Android Studio\jbr")
$selectedJavaHome = $null
foreach ($candidate in $javaCandidates) {
    if (-not $candidate -or -not (Test-Path -LiteralPath "$candidate\release")) { continue }
    $javaRelease = Get-Content -LiteralPath "$candidate\release" -Raw
    $javaMajor = [regex]::Match($javaRelease, 'JAVA_VERSION="(\d+)')
    if ($javaMajor.Success -and [int]$javaMajor.Groups[1].Value -ge 21 -and (Test-Path -LiteralPath "$candidate\bin\javac.exe")) {
        $selectedJavaHome = $candidate
        break
    }
}
if (-not $selectedJavaHome) { throw 'Set JAVA_HOME to a JDK 21 or newer (JDK 25 is supported by this Gradle wrapper).' }
$env:JAVA_HOME = $selectedJavaHome
if (-not $env:ANDROID_HOME) { $env:ANDROID_HOME = Join-Path $env:LOCALAPPDATA 'Android\Sdk' }
if (-not (Test-Path -LiteralPath $env:ANDROID_HOME)) { throw 'Set ANDROID_HOME to your Android SDK directory.' }
& (Join-Path $PSScriptRoot 'prepare-app-icons.ps1') -Target Android
& npx.cmd cap sync android
if ($LASTEXITCODE -ne 0) { throw 'Capacitor sync failed.' }
& node scripts/prepare-native.mjs
if ($LASTEXITCODE -ne 0) { throw 'Android flavor preparation failed.' }
if (-not $DebugBuild) {
    if (-not $env:VALLEY_ANDROID_KEYSTORE) {
        & node scripts/prepare-android-signing.mjs
        if ($LASTEXITCODE -ne 0) { throw 'Signing key preparation failed.' }
        $signing = Get-Content -LiteralPath 'native\secrets\android-signing.json' -Raw | ConvertFrom-Json
        $env:VALLEY_ANDROID_KEYSTORE = $signing.keystore
        $env:VALLEY_ANDROID_KEY_ALIAS = $signing.alias
        $env:VALLEY_ANDROID_STORE_PASSWORD = $signing.password
        $env:VALLEY_ANDROID_KEY_PASSWORD = $signing.password
    }
}
$variant = if ($DebugBuild) { 'Debug' } else { 'Release' }
$appSlugs = if ($App -eq 'all') { @('sales', 'supervisor', 'driver') } else { @($App) }
$buildTasks = @($appSlugs | ForEach-Object { ":app:assemble$((Get-Culture).TextInfo.ToTitleCase($_))$variant" })
Push-Location -LiteralPath 'android'
try {
    & .\gradlew.bat @buildTasks --console plain --no-daemon
    if ($LASTEXITCODE -ne 0) { throw 'Android build failed.' }
} finally { Pop-Location }
$outputDirectory = Join-Path $projectDirectory 'artifacts\android'
New-Item -ItemType Directory -Force -Path $outputDirectory | Out-Null
foreach ($appSlug in $appSlugs) {
    $variantFolder = $variant.ToLowerInvariant()
    $apk = "android\app\build\outputs\apk\$appSlug\$variantFolder\app-$appSlug-$variantFolder.apk"
    $appName = (Get-Culture).TextInfo.ToTitleCase($appSlug)
    $metadata = Get-Content -LiteralPath "android\app\build\outputs\apk\$appSlug\$variantFolder\output-metadata.json" -Raw | ConvertFrom-Json
    $appVersion = $metadata.elements[0].versionName
    if (-not $appVersion) { throw "Missing built version for $appSlug." }
    $destination = Join-Path $outputDirectory "Valley-$appName-$appVersion-$variantFolder.apk"
    Copy-Item -LiteralPath $apk -Destination $destination -Force
    Write-Output $destination
}
Remove-Item Env:VALLEY_ANDROID_STORE_PASSWORD,Env:VALLEY_ANDROID_KEY_PASSWORD -ErrorAction SilentlyContinue
