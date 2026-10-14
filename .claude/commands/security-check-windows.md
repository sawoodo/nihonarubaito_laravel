---
description: Windows PC security scan — find stray .env copies, database dumps, tokens, and credentials outside the repo.
---

# /security-check-windows — scan for security risks on Windows

Searches this PC for accidentally exposed credentials, database dumps, and sensitive files that should not exist outside the repo or be backed up to cloud storage.

**Scope:** Desktop, Documents, Downloads, OneDrive, Google Drive common paths, and temporary folders.

**Rules:**
- Read-only scan — never delete, move, or modify files automatically
- Report findings with full paths
- Exclude the legitimate repo .env (`C:\Users\Tech Fever\Desktop\nihonarubaito\laravel\.env`)
- Stop and report if more than 50 findings (likely a false positive pattern)

---

## Scan 1 — Stray .env files

Search for `.env` files outside the legitimate Laravel directory:

```powershell
$legitEnv = "C:\Users\Tech Fever\Desktop\nihonarubaito\laravel\.env"
$searchPaths = @(
    "$env:USERPROFILE\Desktop",
    "$env:USERPROFILE\Documents",
    "$env:USERPROFILE\Downloads",
    "$env:USERPROFILE\OneDrive",
    "C:\Users\Tech Fever\Google Drive"
)

$envFiles = @()
foreach ($path in $searchPaths) {
    if (Test-Path $path) {
        $found = Get-ChildItem -Path $path -Filter ".env" -Recurse -File -ErrorAction SilentlyContinue | 
            Where-Object { $_.FullName -ne $legitEnv }
        $envFiles += $found
    }
}

if ($envFiles.Count -gt 50) {
    Write-Host "⚠️  SCAN STOPPED: Found $($envFiles.Count) .env files (likely false positive pattern)"
    exit 1
}

if ($envFiles.Count -eq 0) {
    Write-Host "✅ No stray .env files found"
} else {
    Write-Host "⚠️  Found $($envFiles.Count) stray .env file(s):"
    $envFiles | ForEach-Object {
        $size = [math]::Round($_.Length / 1KB, 2)
        Write-Host "  - $($_.FullName) ($size KB, modified: $($_.LastWriteTime))"
    }
}
```

---

## Scan 2 — Database dumps (.sql, .sql.gz, .dump)

```powershell
$dumpExtensions = @("*.sql", "*.sql.gz", "*.dump", "*.sql.bz2", "*.sql.zip")
$dumps = @()

foreach ($path in $searchPaths) {
    if (Test-Path $path) {
        foreach ($ext in $dumpExtensions) {
            $found = Get-ChildItem -Path $path -Filter $ext -Recurse -File -ErrorAction SilentlyContinue
            $dumps += $found
        }
    }
}

if ($dumps.Count -gt 50) {
    Write-Host "⚠️  SCAN STOPPED: Found $($dumps.Count) database dumps (likely false positive)"
    exit 1
}

if ($dumps.Count -eq 0) {
    Write-Host "✅ No database dumps found"
} else {
    Write-Host "⚠️  Found $($dumps.Count) database dump(s):"
    $dumps | ForEach-Object {
        $size = [math]::Round($_.Length / 1MB, 2)
        Write-Host "  - $($_.FullName) ($size MB, modified: $($_.LastWriteTime))"
    }
}
```

---

## Scan 3 — SSH private keys outside .ssh directory

```powershell
$sshDir = "$env:USERPROFILE\.ssh"
$keyPatterns = @("*siteground*", "*nihonarubaito*", "*id_rsa", "*id_ed25519", "*id_ecdsa")
$strayKeys = @()

foreach ($path in $searchPaths) {
    if (Test-Path $path) {
        foreach ($pattern in $keyPatterns) {
            $found = Get-ChildItem -Path $path -Filter $pattern -Recurse -File -ErrorAction SilentlyContinue |
                Where-Object { -not $_.FullName.StartsWith($sshDir) -and $_.Extension -eq "" }
            $strayKeys += $found
        }
    }
}

if ($strayKeys.Count -eq 0) {
    Write-Host "✅ No stray SSH keys found"
} else {
    Write-Host "⚠️  Found $($strayKeys.Count) SSH private key(s) outside .ssh:"
    $strayKeys | ForEach-Object {
        Write-Host "  - $($_.FullName) (modified: $($_.LastWriteTime))"
    }
}
```

---

## Scan 4 — Credential files (config with passwords, tokens)

```powershell
$credPatterns = @{
    "DB_PASSWORD" = @("*.env", "*.config", "*.ini", "*.conf")
    "APP_KEY" = @("*.env", "*.config", "*.txt")
    "bearer.*token" = @("*.txt", "*.json", "*.log")
}

Write-Host "`nScanning for credential strings in config files..."
$credFindings = @()

foreach ($path in $searchPaths) {
    if (Test-Path $path) {
        # Scan text files for password/token patterns
        $textFiles = Get-ChildItem -Path $path -Include "*.txt", "*.config", "*.ini", "*.json" -Recurse -File -ErrorAction SilentlyContinue |
            Select-Object -First 100  # Limit to prevent runaway scans
        
        foreach ($file in $textFiles) {
            try {
                $content = Get-Content $file.FullName -Raw -ErrorAction SilentlyContinue
                if ($content -match "(DB_PASSWORD|APP_KEY|bearer.*token|password\s*=|api_key|secret)") {
                    $credFindings += $file
                }
            } catch {
                # Skip files that can't be read
            }
        }
    }
}

if ($credFindings.Count -eq 0) {
    Write-Host "✅ No credential files found"
} else {
    Write-Host "⚠️  Found $($credFindings.Count) file(s) with credential patterns:"
    $credFindings | Select-Object -First 10 | ForEach-Object {
        Write-Host "  - $($_.FullName)"
    }
    if ($credFindings.Count -gt 10) {
        Write-Host "  ... and $($credFindings.Count - 10) more"
    }
}
```

---

## Scan 5 — Temporary PHP session files with site data

```powershell
$tempDirs = @(
    "$env:TEMP",
    "$env:LOCALAPPDATA\Temp",
    "C:\Windows\Temp"
)

$phpSessions = @()
foreach ($dir in $tempDirs) {
    if (Test-Path $dir) {
        $found = Get-ChildItem -Path $dir -Filter "sess_*" -File -ErrorAction SilentlyContinue |
            Where-Object { $_.LastWriteTime -gt (Get-Date).AddDays(-7) }  # Last 7 days only
        $phpSessions += $found
    }
}

if ($phpSessions.Count -eq 0) {
    Write-Host "✅ No recent PHP session files in temp directories"
} else {
    Write-Host "ℹ️  Found $($phpSessions.Count) PHP session file(s) in temp (normal for local dev):"
    $phpSessions | Select-Object -First 5 | ForEach-Object {
        Write-Host "  - $($_.FullName) (modified: $($_.LastWriteTime))"
    }
}
```

---

## Summary

After all scans complete, report:

```
=== SECURITY SCAN COMPLETE ===

Scanned paths:
  - Desktop
  - Documents
  - Downloads
  - OneDrive
  - Google Drive (if exists)

Findings:
  - Stray .env files: [count]
  - Database dumps: [count]
  - SSH keys outside .ssh: [count]
  - Credential-containing files: [count]
  - Temp PHP sessions: [count] (informational)

Action needed:
  [If findings exist]: Review each file. Delete if accidental copy, move to secure location if needed backup, or confirm if legitimate.
  [If no findings]: ✅ No security risks found.
```

**⚠️  Never commit files found by this scan.** They are outside the repo for a reason (`.gitignore`, too large, or accidentally copied).
