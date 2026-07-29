<#
.SYNOPSIS
	Developer commands for Windows, where GNU Make is usually not installed.

.DESCRIPTION
	Does exactly what the Makefile targets do. Every command is a thin wrapper
	over docker compose, and the underlying command is echoed before it runs, so
	nothing here is required - you can always run it by hand instead.

.EXAMPLE
	.\bin\dev.ps1 setup
	.\bin\dev.ps1 test
	.\bin\dev.ps1 wp -Arguments "plugin list"
#>

[CmdletBinding()]
param(
	[Parameter(Position = 0)]
	[ValidateSet('up', 'setup', 'down', 'destroy', 'logs', 'shell', 'wp',
		'test', 'test-unit', 'test-integration', 'test-e2e',
		'lint', 'analyse', 'seed', 'reindex', 'help')]
	[string]$Command = 'help',

	[Parameter(ValueFromRemainingArguments = $true)]
	[string[]]$Arguments = @()
)

$ErrorActionPreference = 'Stop'

$Root = Split-Path -Parent $PSScriptRoot
$Plugin = '/var/www/html/wp-content/plugins/oxford-course-discovery'

Set-Location $Root

function Invoke-Compose {
	param([string[]]$ComposeArgs)

	Write-Host "docker compose $($ComposeArgs -join ' ')" -ForegroundColor DarkGray
	& docker compose @ComposeArgs

	if ($LASTEXITCODE -ne 0) {
		throw "docker compose exited with $LASTEXITCODE"
	}
}

function Invoke-InContainer {
	param([string[]]$CommandArgs)

	Invoke-Compose (@('exec', '-T', 'wordpress') + $CommandArgs)
}

function Invoke-Wp {
	param([string[]]$WpArgs)

	Invoke-InContainer (@('wp', '--allow-root', '--path=/var/www/html') + $WpArgs)
}

function Show-Help {
	@"
Course Discovery - developer commands

  .\bin\dev.ps1 up                 Build and start the stack
  .\bin\dev.ps1 setup              Start, install WordPress, seed demo content
  .\bin\dev.ps1 down               Stop the stack (data preserved)
  .\bin\dev.ps1 destroy            Stop and delete all data
  .\bin\dev.ps1 logs               Tail the WordPress logs
  .\bin\dev.ps1 shell              Shell inside the container
  .\bin\dev.ps1 wp <args>          Run a WP-CLI command
  .\bin\dev.ps1 test               Every PHP suite
  .\bin\dev.ps1 test-unit          Fast unit suite
  .\bin\dev.ps1 test-integration   Integration and feature suites
  .\bin\dev.ps1 test-e2e           Playwright suite (needs Node on the host)
  .\bin\dev.ps1 lint               PHP_CodeSniffer
  .\bin\dev.ps1 analyse            PHPStan
  .\bin\dev.ps1 seed               Regenerate demo content
  .\bin\dev.ps1 reindex            Rebuild the lookup tables

Examples
  .\bin\dev.ps1 wp plugin list
  .\bin\dev.ps1 wp oxcd seed --courses=100 --fresh
"@ | Write-Host
}

switch ($Command) {
	'up' { Invoke-Compose @('up', '-d', '--build') }

	'setup' {
		Invoke-Compose @('up', '-d', '--build')
		Invoke-InContainer @('bash', '/usr/local/bin/oxcd/setup.sh')
		Write-Host ''
		Write-Host 'Site:  http://localhost:8080' -ForegroundColor Green
		Write-Host 'Admin: http://localhost:8080/wp-admin (admin / password)' -ForegroundColor Green
	}

	'down' { Invoke-Compose @('down') }
	'destroy' { Invoke-Compose @('down', '-v') }
	'logs' { Invoke-Compose @('logs', '-f', 'wordpress') }
	'shell' { Invoke-Compose @('exec', 'wordpress', 'bash') }
	'wp' { Invoke-Wp $Arguments }

	'test' { Invoke-InContainer @('bash', '/usr/local/bin/oxcd/test.sh', 'all') }
	'test-unit' { Invoke-InContainer @('bash', '/usr/local/bin/oxcd/test.sh', 'unit') }
	'test-integration' { Invoke-InContainer @('bash', '/usr/local/bin/oxcd/test.sh', 'integration') }

	'test-e2e' {
		Push-Location (Join-Path $Root 'e2e')
		try {
			& npm install
			& npx playwright install --with-deps chromium
			& npx playwright test
		} finally {
			Pop-Location
		}
	}

	'lint' { Invoke-InContainer @('bash', '-c', "cd $Plugin && vendor/bin/phpcs --standard=phpcs.xml.dist") }
	'analyse' { Invoke-InContainer @('bash', '-c', "cd $Plugin && vendor/bin/phpstan analyse --memory-limit=1G") }

	'seed' { Invoke-Wp @('oxcd', 'seed', '--courses=48', '--fresh') }
	'reindex' { Invoke-Wp @('oxcd', 'reindex') }

	default { Show-Help }
}
