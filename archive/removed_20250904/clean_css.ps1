# Script untuk membersihkan semua CSS inline dari file PHP
Get-ChildItem "c:\xampp\htdocs\randis\pages\*.php" | ForEach-Object {
    $content = Get-Content $_.FullName -Raw -Encoding UTF8
    
    # Hapus semua blok <style>...</style>
    while ($content -match '(?s)<style[^>]*>.*?</style>') {
        $content = $content -replace '(?s)<style[^>]*>.*?</style>', ''
    }
    
    # Hapus baris kosong berlebihan
    $content = $content -replace '(?m)^\s*\r?\n\s*\r?\n', "`n"
    
    Set-Content $_.FullName $content -Encoding UTF8
    Write-Host "Cleaned: $($_.Name)"
}

Write-Host "All CSS cleaned from PHP files"
