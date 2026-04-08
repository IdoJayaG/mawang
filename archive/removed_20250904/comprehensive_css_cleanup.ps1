# Script komprehensif untuk membersihkan semua CSS inline dan blocks
Get-ChildItem "c:\xampp\htdocs\randis\pages\*.php" | ForEach-Object {
    $content = Get-Content $_.FullName -Raw -Encoding UTF8
    $fileName = $_.Name
    $changed = $false
    
    # Remove all <style>...</style> blocks
    while ($content -match '(?s)<style[^>]*>.*?</style>') {
        $content = $content -replace '(?s)<style[^>]*>.*?</style>', ''
        $changed = $true
    }
    
    # Clean up specific inline styles while preserving functionality
    
    # Replace style="display: none;" with class
    if ($content -match 'style="display:\s*none;"') {
        $content = $content -replace 'style="display:\s*none;"', 'class="d-none"'
        $changed = $true
    }
    
    # Replace style="max-height: 300px;" with class
    if ($content -match 'style="max-height:\s*300px;"') {
        $content = $content -replace 'style="max-height:\s*300px;"', 'style="max-height: 300px;"'
        # Keep this one as it's functional, not styling
    }
    
    # Replace image sizing styles with Bootstrap classes
    $content = $content -replace 'style="width:\s*50px;\s*height:\s*50px;\s*object-fit:\s*cover;"', 'class="img-thumbnail" style="width: 50px; height: 50px;"'
    $content = $content -replace 'style="width:\s*40px;\s*height:\s*40px;\s*border-radius:\s*50%;"', 'class="rounded-circle" style="width: 40px; height: 40px;"'
    
    # Replace font-size styles with Bootstrap utilities
    $content = $content -replace 'style="font-size:\s*2\.5rem;"', 'class="fs-1"'
    
    # Replace width styles for icons
    $content = $content -replace 'style="width:\s*20px;"', ''
    
    # Replace height styles for containers with Bootstrap utilities
    $content = $content -replace 'style="height:\s*200px;"', 'style="height: 200px;"'
    $content = $content -replace 'style="height:\s*300px;"', 'style="height: 300px;"'
    
    # Replace max-width styles
    $content = $content -replace 'style="max-width:\s*150px;"', 'style="max-width: 150px;"'
    
    # Replace border-radius styles that are functional
    $content = $content -replace 'style="border-radius:\s*0\s*0\s*0\.5rem\s*0\.5rem;"', ''
    
    # Clean up multiple spaces and newlines
    $content = $content -replace '(?m)^\s*\r?\n\s*\r?\n\s*\r?\n', "`n`n"
    $content = $content -replace '(?m)^\s*\r?\n\s*\r?\n', "`n"
    
    if ($changed) {
        Set-Content $_.FullName $content -Encoding UTF8
        Write-Host "Cleaned: $fileName"
    }
}

Write-Host "CSS cleanup completed for all PHP files"
