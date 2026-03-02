# multiflexi-vaultwarden

VaultWarden/Bitwarden secrets support for [MultiFlexi](https://multiflexi.eu).

## Description

This package provides VaultWarden credential management for MultiFlexi, split into two Debian packages:

| Package | Enhances | Provides |
|---------|----------|----------|
| `multiflexi-vaultwarden` | `php-vitexsoftware-multiflexi-core` | Credential prototype with VaultWarden URL, login, password and folder fields |
| `multiflexi-vaultwarden-ui` | `multiflexi-web` | Connection test, authentication check, folder browsing, item listing |

## Credential Fields

- **VAULTWARDEN_URL** — VaultWarden instance URL (e.g. `https://vault.example.com/`)
- **VAULTWARDEN_EMAIL** — User login email
- **VAULTWARDEN_PASSWORD** — User password
- **VAULTWARDEN_FOLDER** — Folder name containing secrets (default: `MultiFlexi`)

## UI Features

The web interface component provides:
- Server connectivity test (cURL to `/api/config`)
- Authentication test via Bitwarden API
- Folder access verification
- Item listing from the configured folder
- Server version display

## Installation

### From Debian packages

```bash
apt install multiflexi-vaultwarden multiflexi-vaultwarden-ui
```

### From source (development)

```bash
composer install
make phpunit
make cs
```

## Building Debian Packages

```bash
make deb
```

This produces `multiflexi-vaultwarden_*.deb` and `multiflexi-vaultwarden-ui_*.deb` in the parent directory.

## License

MIT — see [debian/copyright](debian/copyright) for details.

## MultiFlexi

[![MultiFlexi](https://github.com/VitexSoftware/MultiFlexi/blob/main/doc/multiflexi-app.svg)](https://www.multiflexi.eu/)
