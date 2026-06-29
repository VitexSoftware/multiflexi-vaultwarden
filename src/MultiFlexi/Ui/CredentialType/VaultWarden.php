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

namespace MultiFlexi\Ui\CredentialType;

/**
 * Description of VaultWarden.
 *
 * @author Vitex <info@vitexsoftware.cz>
 */
class VaultWarden extends \MultiFlexi\Ui\CredentialFormHelperPrototype
{
    public function finalize(): void
    {
        $proto = $this->loadedPrototype();
        $result = $proto->checkAvailability();

        $stateStyle = match ($result->state) {
            \MultiFlexi\CredentialState::Available     => 'success',
            \MultiFlexi\CredentialState::Degraded      => 'warning',
            \MultiFlexi\CredentialState::Unavailable,
            \MultiFlexi\CredentialState::Misconfigured => 'danger',
            default                                    => 'info',
        };

        if ($result->state === \MultiFlexi\CredentialState::Misconfigured) {
            $this->addItem(new \Ease\TWB4\Alert('danger', $result->message));
            parent::finalize();

            return;
        }

        if ($result->details) {
            $panel = new \Ease\TWB4\Panel(_('VaultWarden Status'), $stateStyle);
            $list = new \Ease\Html\DlTag(null, ['class' => 'row']);

            foreach ($result->details as $key => $value) {
                $list->addItem(new \Ease\Html\DtTag((string) $key, ['class' => 'col-sm-4']));
                $list->addItem(new \Ease\Html\DdTag((string) $value, ['class' => 'col-sm-8']));
            }

            $panel->addItem($list);
            $this->addItem($panel);
        }

        $alertMsg = $result->message ?: _('VaultWarden connection successful');
        $this->addItem(new \Ease\TWB4\Alert($stateStyle, $alertMsg));

        if ($result->state === \MultiFlexi\CredentialState::Available) {
            $this->addVaultItems($proto);
        }

        parent::finalize();
    }

    /**
     * Build a prototype instance populated with this credential's field values.
     */
    private function loadedPrototype(): \MultiFlexi\CredentialProtoType\VaultWarden
    {
        $proto = new \MultiFlexi\CredentialProtoType\VaultWarden();
        $credFields = $this->credential->getFields();

        foreach (['VAULTWARDEN_URL', 'VAULTWARDEN_EMAIL', 'VAULTWARDEN_PASSWORD', 'VAULTWARDEN_FOLDER'] as $code) {
            $credField = $credFields->getFieldByCode($code);
            $protoField = $proto->fieldsInternal()->getFieldByCode($code);

            if ($credField !== null && $protoField !== null) {
                $protoField->setValue($credField->getValue());
            }
        }

        return $proto;
    }

    /**
     * Show vault items returned by the prototype's query() in a collapsible list.
     */
    private function addVaultItems(\MultiFlexi\CredentialProtoType\VaultWarden $proto): void
    {
        try {
            $provided = $proto->query();
            $fieldCount = \count($provided);

            if ($fieldCount === 0) {
                return;
            }

            $panel = new \Ease\TWB4\Panel(
                sprintf(_('Vault items (%d)'), $fieldCount),
                'default',
            );
            $list = new \Ease\Html\UlTag(null, [
                'class' => 'list-group list-group-flush',
                'style' => 'max-height: 300px; overflow-y: auto;',
            ]);

            foreach ($provided as $field) {
                $list->addItem(new \Ease\Html\LiTag(
                    new \Ease\Html\SpanTag($field->getCode(), ['class' => 'font-monospace']),
                    ['class' => 'list-group-item py-1'],
                ));
            }

            $panel->addItem($list);
            $this->addItem($panel);
        } catch (\Throwable) {
            // Silently ignore errors during item listing.
        }
    }
}
