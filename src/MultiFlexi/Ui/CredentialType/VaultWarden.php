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
        $urlField = $this->credential->getFields()->getFieldByCode('VAULTWARDEN_URL');
        $emailField = $this->credential->getFields()->getFieldByCode('VAULTWARDEN_EMAIL');
        $passwordField = $this->credential->getFields()->getFieldByCode('VAULTWARDEN_PASSWORD');
        $folderField = $this->credential->getFields()->getFieldByCode('VAULTWARDEN_FOLDER');

        $url = $urlField ? $urlField->getValue() : '';
        $email = $emailField ? $emailField->getValue() : '';
        $password = $passwordField ? $passwordField->getValue() : '';
        $folder = $folderField ? $folderField->getValue() : '';

        if (empty($url) || empty($email) || empty($password)) {
            $missing = [];

            if (empty($url)) {
                $missing[] = 'VAULTWARDEN_URL';
            }

            if (empty($email)) {
                $missing[] = 'VAULTWARDEN_EMAIL';
            }

            if (empty($password)) {
                $missing[] = 'VAULTWARDEN_PASSWORD';
            }

            $this->addItem(new \Ease\TWB4\Alert('danger', sprintf(
                _('Required fields not set: %s'),
                implode(', ', $missing),
            )));
            parent::finalize();

            return;
        }

        // Display configuration info
        $infoPanel = new \Ease\TWB4\Panel(_('VaultWarden Configuration'), 'default');
        $infoList = new \Ease\Html\DlTag(null, ['class' => 'row']);

        $infoList->addItem(new \Ease\Html\DtTag(_('Server URL'), ['class' => 'col-sm-4']));
        $infoList->addItem(new \Ease\Html\DdTag($url, ['class' => 'col-sm-8']));

        $infoList->addItem(new \Ease\Html\DtTag(_('User'), ['class' => 'col-sm-4']));
        $infoList->addItem(new \Ease\Html\DdTag($email, ['class' => 'col-sm-8']));

        if (!empty($folder)) {
            $infoList->addItem(new \Ease\Html\DtTag(_('Folder'), ['class' => 'col-sm-4']));
            $infoList->addItem(new \Ease\Html\DdTag($folder, ['class' => 'col-sm-8']));
        }

        $infoPanel->addItem($infoList);
        $this->addItem($infoPanel);

        // Test connection to VaultWarden
        $connectionResult = self::testConnection($url);

        if ($connectionResult['success']) {
            $this->addItem(new \Ease\TWB4\Alert('success', sprintf(
                _('VaultWarden server %s is reachable'),
                $url,
            )));

            if (!empty($connectionResult['server_info'])) {
                $serverPanel = new \Ease\TWB4\Panel(_('Server Information'), 'info');
                $serverList = new \Ease\Html\DlTag(null, ['class' => 'row']);

                foreach ($connectionResult['server_info'] as $key => $value) {
                    $serverList->addItem(new \Ease\Html\DtTag($key, ['class' => 'col-sm-4']));
                    $serverList->addItem(new \Ease\Html\DdTag($value, ['class' => 'col-sm-8']));
                }

                $serverPanel->addItem($serverList);
                $this->addItem($serverPanel);
            }

            // Test authentication
            $authResult = self::testAuthentication($url, $email, $password);

            if ($authResult['success']) {
                $this->addItem(new \Ease\TWB4\Alert('success', sprintf(
                    _('Authentication successful for %s'),
                    $email,
                )));

                // Test folder access if folder is set
                if (!empty($folder)) {
                    $folderResult = self::testFolderAccess($url, $email, $password, $folder);

                    if ($folderResult['success']) {
                        $this->addItem(new \Ease\TWB4\Alert('success', sprintf(
                            _('Folder "%s" found with %d items'),
                            $folder,
                            $folderResult['item_count'],
                        )));

                        if (!empty($folderResult['items'])) {
                            $itemsPanel = new \Ease\TWB4\Panel(
                                sprintf(_('Items in folder "%s" (%d)'), $folder, \count($folderResult['items'])),
                                'default',
                            );
                            $itemList = new \Ease\Html\UlTag(null, ['class' => 'list-group list-group-flush', 'style' => 'max-height: 300px; overflow-y: auto;']);

                            foreach ($folderResult['items'] as $itemName) {
                                $itemList->addItem(new \Ease\Html\LiTag(
                                    new \Ease\Html\SpanTag($itemName, ['class' => 'font-monospace']),
                                    ['class' => 'list-group-item py-1'],
                                ));
                            }

                            $itemsPanel->addItem($itemList);
                            $this->addItem($itemsPanel);
                        }
                    } else {
                        $this->addItem(new \Ease\TWB4\Alert('warning', sprintf(
                            _('Folder "%s" not accessible: %s'),
                            $folder,
                            $folderResult['message'],
                        )));
                    }
                }
            } else {
                $this->addItem(new \Ease\TWB4\Alert('danger', sprintf(
                    _('Authentication failed: %s'),
                    $authResult['message'],
                )));
            }
        } else {
            $this->addItem(new \Ease\TWB4\Alert('danger', sprintf(
                _('VaultWarden server %s is not reachable: %s'),
                $url,
                $connectionResult['message'],
            )));
        }

        parent::finalize();
    }

    /**
     * Test connectivity to VaultWarden server.
     *
     * @return array{success: bool, message: string, server_info: array<string, string>}
     */
    private static function testConnection(string $url): array
    {
        $serverInfo = [];

        try {
            $apiUrl = rtrim($url, '/').'/api/config';
            $ch = curl_init($apiUrl);

            if ($ch === false) {
                return [
                    'success' => false,
                    'message' => _('Failed to initialize cURL'),
                    'server_info' => [],
                ];
            }

            curl_setopt_array($ch, [
                \CURLOPT_RETURNTRANSFER => true,
                \CURLOPT_TIMEOUT => 10,
                \CURLOPT_CONNECTTIMEOUT => 5,
                \CURLOPT_FOLLOWLOCATION => true,
                \CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, \CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($response === false || $httpCode === 0) {
                return [
                    'success' => false,
                    'message' => !empty($error) ? $error : _('Connection failed'),
                    'server_info' => [],
                ];
            }

            $serverInfo[_('HTTP Status')] = (string) $httpCode;

            $data = json_decode((string) $response, true);

            if (\is_array($data)) {
                if (isset($data['version'])) {
                    $serverInfo[_('Server Version')] = $data['version'];
                }

                if (isset($data['environment', 'vault'])) {
                    $serverInfo[_('Vault URL')] = $data['environment']['vault'];
                }
            }

            return [
                'success' => $httpCode >= 200 && $httpCode < 400,
                'message' => $httpCode >= 400 ? sprintf(_('HTTP %d'), $httpCode) : '',
                'server_info' => $serverInfo,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'server_info' => [],
            ];
        }
    }

    /**
     * Test authentication against VaultWarden using Bitwarden API.
     *
     * @return array{success: bool, message: string}
     */
    private static function testAuthentication(string $url, string $email, string $password): array
    {
        try {
            $delegate = new \MultiFlexi\BitwardenServiceDelegate($email, $password, $url);
            $service = new \Jalismrs\Bitwarden\BitwardenService($delegate);

            // Attempt to list items — this forces authentication
            $service->searchItems('');

            return ['success' => true, 'message' => ''];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Test access to a specific VaultWarden folder and list items.
     *
     * @return array{success: bool, message: string, item_count: int, items: array<string>}
     */
    private static function testFolderAccess(string $url, string $email, string $password, string $folder): array
    {
        try {
            $delegate = new \MultiFlexi\BitwardenServiceDelegate($email, $password, $url);
            $service = new \Jalismrs\Bitwarden\BitwardenService($delegate);
            $items = $service->searchItems($folder);

            $itemNames = [];

            foreach ($items as $item) {
                $itemNames[] = $item->getName();
            }

            return [
                'success' => true,
                'message' => '',
                'item_count' => \count($items),
                'items' => $itemNames,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'item_count' => 0,
                'items' => [],
            ];
        }
    }
}
