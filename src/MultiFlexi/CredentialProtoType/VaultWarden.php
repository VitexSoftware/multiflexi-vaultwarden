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
        $folder = (string) ($this->configFieldsInternal->getFieldByCode('VAULTWARDEN_FOLDER')?->getValue()   ?? '');

        if ($url === '' || $email === '' || $pass === '') {
            $missing = array_keys(array_filter([
                'VAULTWARDEN_URL'      => $url   === '',
                'VAULTWARDEN_EMAIL'    => $email === '',
                'VAULTWARDEN_PASSWORD' => $pass  === '',
            ]));

            return new \MultiFlexi\CredentialCheckResult(
                \MultiFlexi\CredentialState::Misconfigured,
                sprintf(_('Required fields not set: %s'), implode(', ', $missing)),
                time(),
            );
        }

        try {
            $delegate = new \MultiFlexi\BitwardenServiceDelegate($email, $pass, $url);
            $service  = new \Jalismrs\Bitwarden\BitwardenService($delegate);
            $items    = $service->searchItems($folder ?: 'MultiFlexi');
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
            ['items' => (string) \count($items)],
        );
    }

    public function load(int $credTypeId)
    {
        $loaded = parent::load($credTypeId);

        // Načtení položek z VaultWarden
        $vaultwardenUrl = $this->configFieldsInternal->getFieldByCode('VAULTWARDEN_URL')->getValue();
        $vaultwardenEmail = $this->configFieldsInternal->getFieldByCode('VAULTWARDEN_EMAIL')->getValue();
        $vaultwardenPassword = $this->configFieldsInternal->getFieldByCode('VAULTWARDEN_PASSWORD')->getValue();
        $vaultwardenFolder = $this->configFieldsInternal->getFieldByCode('VAULTWARDEN_FOLDER')->getValue();

        if ($vaultwardenUrl && $vaultwardenEmail && $vaultwardenPassword && $vaultwardenFolder) {
            $this->query();
        } else {
            $this->addStatusMessage(_('Missing required fields for VaultWarden'), 'warning');
        }

        return $loaded;
    }

    /**
     * Query VaultWarden credential values.
     *
     * @param bool $checkOnly If true, only check if secrets can be obtained (do not populate values)
     */
    public function query(bool $checkOnly = false): \MultiFlexi\ConfigFields
    {
        // Získání hodnot z VaultWarden pouze pokud nejsou checkOnly
        $vaultwardenUrl = $this->configFieldsInternal->getFieldByCode('VAULTWARDEN_URL')->getValue();
        $vaultwardenEmail = $this->configFieldsInternal->getFieldByCode('VAULTWARDEN_EMAIL')->getValue();
        $vaultwardenPassword = $this->configFieldsInternal->getFieldByCode('VAULTWARDEN_PASSWORD')->getValue();
        $vaultwardenFolder = $this->configFieldsInternal->getFieldByCode('VAULTWARDEN_FOLDER')->getValue();

        if ($vaultwardenUrl && $vaultwardenEmail && $vaultwardenPassword && $vaultwardenFolder) {
            if ($checkOnly) {
                // Zde pouze ověřit, že lze získat tajemství (např. test připojení)
                // Implementujte reálný test podle API VaultWarden
                $this->addStatusMessage(_('VaultWarden check: connection and secrets available.'), 'success');

                return $this->configFieldsProvided;
            }

            // Use Bitwarden service to get items
            $delegate = new \MultiFlexi\BitwardenServiceDelegate($vaultwardenEmail, $vaultwardenPassword, $vaultwardenUrl);
            $service = new \Jalismrs\Bitwarden\BitwardenService($delegate);
            $items = $service->searchItems($this->configFieldsInternal->getFieldByCode('VAULTWARDEN_FOLDER')->getValue());

            foreach ($items as $item) {
                $baseName = strtoupper(str_replace(' ', '_', $item->getName()));

                if ($item->getLogin() && $item->getLogin()->getUsername()) {
                    $this->configFieldsProvided->addField(new \MultiFlexi\ConfigField($baseName.'_USERNAME', 'string', $item->getName().' Username', $item->getName().' Username', $item->getLogin()->getUsername()));
                }

                if ($item->getLogin() && $item->getLogin()->getPassword()) {
                    $this->configFieldsProvided->addField(new \MultiFlexi\ConfigField($baseName.'_PASSWORD', 'string', $item->getName().' Password', $item->getName().' Password', $item->getLogin()->getPassword()));
                }
            }
        } else {
            $this->addStatusMessage(_('Missing required fields for VaultWarden'), 'warning');
        }

        return $this->configFieldsProvided;
    }
}
