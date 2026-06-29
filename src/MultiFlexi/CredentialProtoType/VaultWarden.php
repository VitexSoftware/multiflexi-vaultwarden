<?php

declare(strict_types=1);

/**
 * This file is part of the MultiFlexi package
 *
 * https://multiflexi.eu/
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MultiFlexi\CredentialProtoType;

/**
 * Description of VaultWarden.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class VaultWarden extends \MultiFlexi\CredentialProtoType implements \MultiFlexi\credentialTypeInterface, \MultiFlexi\checkableCredentialInterface
{
    public function __construct()
    {
        parent::__construct();
        // Přístupové údaje pro VaultWarden
        $this->configFieldsInternal = new \MultiFlexi\ConfigFields('VaultWarden Internal');
        $this->configFieldsInternal->addField(new \MultiFlexi\ConfigField('VAULTWARDEN_URL', 'url', _('VaultWarden URL'), _('URL instance VaultWarden'), 'https://vault.example.com/'));
        $this->configFieldsInternal->addField(new \MultiFlexi\ConfigField('VAULTWARDEN_EMAIL', 'string', _('VaultWarden User login'), _('User e-mail')));
        $this->configFieldsInternal->addField(new \MultiFlexi\ConfigField('VAULTWARDEN_PASSWORD', 'password', _('VaultWarden User password'), _('Password for user')));
        $this->configFieldsInternal->addField(new \MultiFlexi\ConfigField('VAULTWARDEN_FOLDER', 'string', _('VaultWarden Folder'), _('Název složky s hesly ve VaultWarden'), 'MultiFlexi'));

        // Položky budou naplněny dynamicky podle obsahu VaultWarden
        $this->configFieldsProvided = new \MultiFlexi\ConfigFields('VaultWarden Provided');
    }

    public static function name(): string
    {
        return _('VaultWarden');
    }

    public static function description(): string
    {
        return _('Use VaultWarden secrets');
    }

    public static function uuid(): string
    {
        return '6ba7b818-9dad-11d1-80b4-00c04fd430c8';
    }

    #[\Override]
    public function prepareConfigForm(): void
    {
        //        $folderField = $this->configFieldsInternal->getFieldByCode('VAULTWARDEN_FOLDER');
        parent::prepareConfigForm();
    }

    #[\Override]
    public function fieldsInternal(): \MultiFlexi\ConfigFields
    {
        return $this->configFieldsInternal;
    }

    #[\Override]
    public function fieldsProvided(): \MultiFlexi\ConfigFields
    {
        return $this->configFieldsProvided;
    }

    #[\Override]
    public static function logo(): string
    {
        return 'vaultwarden.svg';
    }

    public function checkAvailability(): \MultiFlexi\CredentialCheckResult
    {
        $url    = (string) ($this->configFieldsInternal->getFieldByCode('VAULTWARDEN_URL')?->getValue()      ?? '');
        $email  = (string) ($this->configFieldsInternal->getFieldByCode('VAULTWARDEN_EMAIL')?->getValue()    ?? '');
        $pass   = (string) ($this->configFieldsInternal->getFieldByCode('VAULTWARDEN_PASSWORD')?->getValue() ?? '');
        $folder = (string) ($this->configFieldsInternal->getFieldByCode('VAULTWARDEN_FOLDER')?->getValue()   ?? 'MultiFlexi');

        $missing = array_keys(array_filter([
            'VAULTWARDEN_URL'      => $url   === '',
            'VAULTWARDEN_EMAIL'    => $email === '',
            'VAULTWARDEN_PASSWORD' => $pass  === '',
        ]));

        if ($missing !== []) {
            return new \MultiFlexi\CredentialCheckResult(
                \MultiFlexi\CredentialState::Misconfigured,
                sprintf(_('Required fields not set: %s'), implode(', ', $missing)),
                time(),
            );
        }

        $this->configureServer($url);

        try {
            $delegate = new \MultiFlexi\BitwardenServiceDelegate($email, $pass, $url);
            $service  = new \Jalismrs\Bitwarden\BitwardenService($delegate);
            $items    = $service->searchItems($folder);
        } catch (\Throwable $e) {
            $msg = strtolower($e->getMessage());

            if (str_contains($msg, 'unauthorized') || str_contains($msg, 'invalid') || str_contains($msg, 'credentials')) {
                return new \MultiFlexi\CredentialCheckResult(
                    \MultiFlexi\CredentialState::Misconfigured,
                    sprintf(_('VaultWarden authentication failed: %s'), $e->getMessage()),
                    time(),
                );
            }

            return new \MultiFlexi\CredentialCheckResult(
                \MultiFlexi\CredentialState::Unavailable,
                sprintf(_('Cannot reach VaultWarden: %s'), $e->getMessage()),
                time(),
                60,
            );
        }

        return new \MultiFlexi\CredentialCheckResult(
            \MultiFlexi\CredentialState::Available,
            '',
            time(),
            300,
            [
                _('Server') => $url,
                _('Folder') => $folder,
                _('Items')  => (string) \count($items),
            ],
        );
    }

    public function load(int $credTypeId)
    {
        $loaded = parent::load($credTypeId);

        $url      = (string) ($this->configFieldsInternal->getFieldByCode('VAULTWARDEN_URL')?->getValue()      ?? '');
        $email    = (string) ($this->configFieldsInternal->getFieldByCode('VAULTWARDEN_EMAIL')?->getValue()    ?? '');
        $password = (string) ($this->configFieldsInternal->getFieldByCode('VAULTWARDEN_PASSWORD')?->getValue() ?? '');
        $folder   = (string) ($this->configFieldsInternal->getFieldByCode('VAULTWARDEN_FOLDER')?->getValue()   ?? '');

        if ($url !== '' && $email !== '' && $password !== '' && $folder !== '') {
            $this->query();
        } else {
            $this->addStatusMessage(_('Missing required fields for VaultWarden'), 'warning');
        }

        return $loaded;
    }

    /**
     * Populate configFieldsProvided from VaultWarden vault items.
     *
     * Each vault item in the configured folder yields two env-style fields:
     *   <ITEM_NAME>_USERNAME and <ITEM_NAME>_PASSWORD.
     */
    public function query(): \MultiFlexi\ConfigFields
    {
        $url    = (string) ($this->configFieldsInternal->getFieldByCode('VAULTWARDEN_URL')?->getValue()      ?? '');
        $email  = (string) ($this->configFieldsInternal->getFieldByCode('VAULTWARDEN_EMAIL')?->getValue()    ?? '');
        $pass   = (string) ($this->configFieldsInternal->getFieldByCode('VAULTWARDEN_PASSWORD')?->getValue() ?? '');
        $folder = (string) ($this->configFieldsInternal->getFieldByCode('VAULTWARDEN_FOLDER')?->getValue()   ?? 'MultiFlexi');

        if ($url === '' || $email === '' || $pass === '') {
            $this->addStatusMessage(_('Missing required fields for VaultWarden'), 'warning');

            return $this->configFieldsProvided;
        }

        $this->configureServer($url);

        $delegate = new \MultiFlexi\BitwardenServiceDelegate($email, $pass, $url);
        $service  = new \Jalismrs\Bitwarden\BitwardenService($delegate);
        $items    = $service->searchItems($folder);

        foreach ($items as $item) {
            $baseName = strtoupper(str_replace([' ', '-'], '_', $item->getName()));
            $login    = $item->getLogin();

            if ($login !== null && $login->getUsername() !== null) {
                $this->configFieldsProvided->addField(new \MultiFlexi\ConfigField(
                    $baseName.'_USERNAME',
                    'string',
                    $item->getName().' '._('Username'),
                    $item->getName().' '._('Username'),
                    $login->getUsername(),
                ));
            }

            if ($login !== null && $login->getPassword() !== null) {
                $this->configFieldsProvided->addField(new \MultiFlexi\ConfigField(
                    $baseName.'_PASSWORD',
                    'password',
                    $item->getName().' '._('Password'),
                    $item->getName().' '._('Password'),
                    $login->getPassword(),
                ));
            }
        }

        return $this->configFieldsProvided;
    }

    /**
     * Configure the bw CLI to point at the correct vaultwarden server.
     *
     * The jalismrs/bitwarden-php library drives the bw CLI, which stores its
     * server URL in its own config. We set it here before every authentication
     * attempt so that custom (non-bitwarden.com) servers work correctly.
     */
    private function configureServer(string $url): void
    {
        if ($url === '') {
            return;
        }

        $process = new \Symfony\Component\Process\Process(['bw', 'config', 'server', $url]);
        $process->run();
    }
}
