<?php
/*
 * MikoPBX - free phone system for small business
 * Copyright © 2017-2023 Alexey Portnov and Nikolay Beketov
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <https://www.gnu.org/licenses/>.
 */

namespace Modules\ModuleBitrixNotify\App\Controllers;

use MikoPBX\AdminCabinet\Controllers\BaseController;
use MikoPBX\Modules\PbxExtensionUtils;
use Modules\ModuleBitrixNotify\App\Forms\ModuleBitrixNotifyForm;
use Modules\ModuleBitrixNotify\Models\ModuleBitrixNotify;

/**
 * Страница настроек ModuleBitrixNotify в админ-кабинете.
 *
 * Интерактив (проверка связи, тест пропущенного, health) реализован в JS
 * (public/assets/js/module-bitrix-index.js), который вызывает REST модуля.
 *
 * @package Modules\ModuleBitrixNotify\App\Controllers
 */
class ModuleBitrixNotifyController extends BaseController
{
    private string $moduleUniqueID = 'ModuleBitrixNotify';
    private string $moduleDir;

    public function initialize(): void
    {
        $this->moduleDir = PbxExtensionUtils::getModuleDir($this->moduleUniqueID);
        $this->view->logoImagePath = $this->url->get() . 'assets/img/cache/' . $this->moduleUniqueID . '/logo.svg';
        $this->view->submitMode = null;
        parent::initialize();
    }

    /**
     * Настройки + диагностическая панель.
     */
    public function indexAction(): void
    {
        $headerCollection = $this->assets->collection('headerCSS');
        $headerCollection->addCss("css/cache/{$this->moduleUniqueID}/module-bitrix.css", true);

        $footerCollection = $this->assets->collection('footerJS');
        $footerCollection->addJs('js/pbx/main/form.js', true);
        $footerCollection->addJs("js/cache/{$this->moduleUniqueID}/module-bitrix-index.js", true);

        $settings = ModuleBitrixNotify::findFirst();
        if ($settings === null) {
            $settings = new ModuleBitrixNotify();
        }

        $tokenExists  = trim((string)($settings->webhook_token ?? '')) !== '';
        $secretExists = trim((string)($settings->api_secret ?? '')) !== '';
        $options      = [
            'tokenPlaceholder'  => $tokenExists
                ? $this->translation->_('module_bitrix_notify_TokenSavedPlaceholder')
                : $this->translation->_('module_bitrix_notify_TokenEnterPlaceholder'),
            'secretPlaceholder' => $secretExists
                ? $this->translation->_('module_bitrix_notify_SecretSavedPlaceholder')
                : $this->translation->_('module_bitrix_notify_SecretEnterPlaceholder'),
        ];

        $this->view->publicApiUrl = '/pbxcore/api/modules/ModuleBitrixNotify/public/send';
        $this->view->tokenExists  = $tokenExists;
        $this->view->form         = new ModuleBitrixNotifyForm($settings, $options);
        $this->view->pick("{$this->moduleDir}/App/Views/index");
    }

    /**
     * Сохранение настроек (POST из формы).
     */
    public function saveAction(): void
    {
        if (!$this->request->isPost()) {
            return;
        }
        $data   = $this->request->getPost();
        $record = ModuleBitrixNotify::findFirst();
        if ($record === null) {
            $record = new ModuleBitrixNotify();
        }
        $this->db->begin();
        foreach ($record as $key => $value) {
            switch ($key) {
                case 'id':
                    break;
                case 'webhook_token':
                    // Пустое поле не затирает сохранённый токен
                    $newToken = trim((string)($data['webhook_token'] ?? ''));
                    if ($newToken !== '') {
                        $record->webhook_token = $newToken;
                    }
                    break;
                case 'api_secret':
                    // Пустое поле не затирает секрет (очистка — через JS "генерация" или сброс формы)
                    $newSecret = trim((string)($data['api_secret'] ?? ''));
                    if ($newSecret !== '') {
                        $record->api_secret = $newSecret;
                    }
                    break;
                case 'notify_missed':
                case 'notify_ring':
                case 'public_api_enabled':
                    $val = strtolower(trim((string)($data[$key] ?? '')));
                    // Semantic UI может отдать 'on'/'off'/'1'/'0'/null — понимаем все
                    $record->$key = in_array($val, ['on', '1', 'true', 'yes'], true) ? '1' : '0';
                    break;
                case 'throttle_sec':
                    $throttle = (int)($data['throttle_sec'] ?? 0);
                    if ($throttle < 0 || $throttle > 86400) {
                        $throttle = 60;
                    }
                    $record->throttle_sec = (string)$throttle;
                    break;
                default:
                    $record->$key = array_key_exists($key, $data) ? $data[$key] : '';
            }
        }

        // Валидация: публичный API без секрета бессмысленен
        if (($record->public_api_enabled === '1' || ($data['public_api_enabled'] ?? '') === 'on')
            && trim((string)($record->api_secret ?? '')) === '') {
            $this->flash->error($this->translation->_('module_bitrix_notify_SecretRequired'));
            $this->view->success = false;
            $this->db->rollback();

            return;
        }

        if ($record->save() === false) {
            $errors = $record->getMessages();
            $this->flash->error(implode('<br>', $errors));
            $this->view->success = false;
            $this->db->rollback();

            return;
        }

        $this->flash->success($this->translation->_('ms_SuccessfulSaved'));
        $this->view->success = true;
        $this->db->commit();
    }
}
