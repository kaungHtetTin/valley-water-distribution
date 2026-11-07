param([ValidateSet('All', 'Android', 'Office')][string]$Target = 'All')
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing
$projectDirectory = Split-Path -Parent $PSScriptRoot

function New-LogoBitmap {
    param([System.Drawing.Image]$Source, [int]$Size, [int]$ContentSize = $Size)
    $bitmap = New-Object System.Drawing.Bitmap $Size,$Size
    $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
    try {
        $graphics.Clear([System.Drawing.Color]::Transparent)
        $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
        $graphics.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
        $graphics.CompositingQuality = [System.Drawing.Drawing2D.CompositingQuality]::HighQuality
        $scale = [Math]::Min($ContentSize / $Source.Width, $ContentSize / $Source.Height)
        $width = [int][Math]::Round($Source.Width * $scale)
        $height = [int][Math]::Round($Source.Height * $scale)
        $bounds = New-Object System.Drawing.Rectangle ([int](($Size - $width) / 2)),([int](($Size - $height) / 2)),$width,$height
        $attributes = New-Object System.Drawing.Imaging.ImageAttributes
        try {
            $attributes.SetWrapMode([System.Drawing.Drawing2D.WrapMode]::TileFlipXY)
            $graphics.DrawImage($Source, $bounds, 0, 0, $Source.Width, $Source.Height, [System.Drawing.GraphicsUnit]::Pixel, $attributes)
        } finally { $attributes.Dispose() }
    } finally { $graphics.Dispose() }
    return $bitmap
}

function Save-LogoPng {
    param([System.Drawing.Image]$Source, [string]$Destination, [int]$Size, [int]$ContentSize = $Size)
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $Destination) | Out-Null
    $bitmap = New-LogoBitmap -Source $Source -Size $Size -ContentSize $ContentSize
    try { $bitmap.Save($Destination, [System.Drawing.Imaging.ImageFormat]::Png) }
    finally { $bitmap.Dispose() }
}

if ($Target -in @('All', 'Android')) {
    $logos = [ordered]@{ sales = 'logo_sale_representative.png'; supervisor = 'logo_sale_supervisor.png'; driver = 'logo_driver.png' }
    $densities = [ordered]@{ mdpi = 48; hdpi = 72; xhdpi = 96; xxhdpi = 144; xxxhdpi = 192 }
    foreach ($appSlug in $logos.Keys) {
        $source = [System.Drawing.Image]::FromFile((Join-Path $projectDirectory "logos\$($logos[$appSlug])"))
        try {
            $resources = Join-Path $projectDirectory "android\app\src\$appSlug\res"
            foreach ($density in $densities.Keys) {
                foreach ($name in @('ic_launcher', 'ic_launcher_round')) {
                    Save-LogoPng -Source $source -Destination "$resources\mipmap-$density\$name.png" -Size $densities[$density]
                }
            }
            # Keep lettering inside the adaptive icon's safe region for round and square launchers.
            Save-LogoPng -Source $source -Destination "$resources\drawable-nodpi\valley_launcher_foreground.png" -Size 432 -ContentSize 264
            Save-LogoPng -Source $source -Destination "$resources\drawable-nodpi\valley_splash_logo.png" -Size 432 -ContentSize 264
            $adaptiveDirectory = "$resources\mipmap-anydpi-v26"
            New-Item -ItemType Directory -Force -Path $adaptiveDirectory | Out-Null
            $adaptiveIcon = @'
<?xml version="1.0" encoding="utf-8"?>
<adaptive-icon xmlns:android="http://schemas.android.com/apk/res/android">
    <background android:drawable="@android:color/white" />
    <foreground android:drawable="@drawable/valley_launcher_foreground" />
</adaptive-icon>
'@
            foreach ($name in @('ic_launcher', 'ic_launcher_round')) {
                [System.IO.File]::WriteAllText("$adaptiveDirectory\$name.xml", $adaptiveIcon + "`n", (New-Object System.Text.UTF8Encoding $false))
            }
            Write-Output "Prepared $appSlug launcher and splash logos from $($logos[$appSlug])."
        } finally { $source.Dispose() }
    }
}

if ($Target -in @('All', 'Office')) {
    $source = [System.Drawing.Image]::FromFile((Join-Path $projectDirectory 'logos\logo_office.png'))
    $images = New-Object 'System.Collections.Generic.List[byte[]]'
    $sizes = @(16, 20, 24, 32, 40, 48, 64, 128, 256)
    try {
        foreach ($size in $sizes) {
            $bitmap = New-LogoBitmap -Source $source -Size $size
            $stream = New-Object System.IO.MemoryStream
            try {
                $bitmap.Save($stream, [System.Drawing.Imaging.ImageFormat]::Png)
                $images.Add($stream.ToArray())
            } finally { $stream.Dispose(); $bitmap.Dispose() }
        }
        $iconPath = Join-Path $projectDirectory 'native\electron\valley.ico'
        $writer = New-Object System.IO.BinaryWriter([System.IO.File]::Create($iconPath))
        try {
            $writer.Write([uint16]0); $writer.Write([uint16]1); $writer.Write([uint16]$sizes.Count)
            $offset = 6 + (16 * $sizes.Count)
            for ($index = 0; $index -lt $sizes.Count; $index++) {
                $dimension = if ($sizes[$index] -eq 256) { 0 } else { $sizes[$index] }
                $writer.Write([byte]$dimension); $writer.Write([byte]$dimension)
                $writer.Write([byte]0); $writer.Write([byte]0)
                $writer.Write([uint16]1); $writer.Write([uint16]32)
                $writer.Write([uint32]$images[$index].Length); $writer.Write([uint32]$offset)
                $offset += $images[$index].Length
            }
            foreach ($imageBytes in $images) { $writer.Write($imageBytes) }
        } finally { $writer.Dispose() }
        Write-Output 'Prepared multi-resolution Office ICO from logo_office.png.'
    } finally { $source.Dispose() }
}
