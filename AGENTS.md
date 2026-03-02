# AGENTS.md

## Project Overview

This project provides VaultWarden/Bitwarden secrets support for MultiFlexi as a separate Debian-packaged addon. It produces two binary packages from one source:

- **multiflexi-vaultwarden** — credential prototype for `php-vitexsoftware-multiflexi-core` (`MultiFlexi\CredentialProtoType\VaultWarden`)
- **multiflexi-vaultwarden-ui** — UI form helper for `multiflexi-web` (`MultiFlexi\Ui\CredentialType\VaultWarden`)

## Directory Structure

- `src/MultiFlexi/CredentialProtoType/VaultWarden.php` — core credential prototype class
- `src/MultiFlexi/Ui/CredentialType/VaultWarden.php` — web UI credential form helper
- `src/images/vaultwarden.svg` — logo asset
- `debian/` — Debian packaging
- `tests/` — PHPUnit tests

## Build & Test

```bash
make vendor    # install composer dependencies
make phpunit   # run tests
make cs        # fix coding standards
make deb       # build Debian packages
```

## Coding Standards

- PHP 8.1+ with strict types
- PSR-12 via ergebnis/php-cs-fixer-config
- Run `make cs` before committing

## Debian Packaging

The `debian/control` defines two binary packages with proper dependency chains:
- `multiflexi-vaultwarden` depends on `php-vitexsoftware-multiflexi-core`, `multiflexi-cli (>= 2.2.0)` and `php-jalismrs-bitwarden`
- `multiflexi-vaultwarden-ui` depends on `multiflexi-vaultwarden` and `multiflexi-web`

The `postinst` for `multiflexi-vaultwarden` runs `multiflexi-cli crprototype sync` to register the credential prototype.

## Key Classes

### MultiFlexi\CredentialProtoType\VaultWarden
Extends `\MultiFlexi\CredentialProtoType` and implements `\MultiFlexi\credentialTypeInterface`.
Defines fields: VAULTWARDEN_URL, VAULTWARDEN_EMAIL, VAULTWARDEN_PASSWORD, VAULTWARDEN_FOLDER.
Dynamically populates provided fields by querying VaultWarden via the Bitwarden API (jalismrs/bitwarden-php).

### MultiFlexi\Ui\CredentialType\VaultWarden
Extends `\MultiFlexi\Ui\CredentialFormHelperPrototype`.
Tests server connectivity (cURL), authenticates via Bitwarden API, verifies folder access, and lists vault items in the web UI.
