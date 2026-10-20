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

namespace Modules\ModuleBitrixNotify\App\Forms;

use MikoPBX\AdminCabinet\Forms\BaseForm;
use Phalcon\Forms\Element\Check;
use Phalcon\Forms\Element\Hidden;
use Phalcon\Forms\Element\Numeric;
use Phalcon\Forms\Element\Password;
use Phalcon\Forms\Element\Text;
use Phalcon\Forms\Element\TextArea;

class ModuleBitrixNotifyForm extends BaseForm
{

    public function initialize($entity = null, $options = null): void
    {
        // id
        $this->add(new Hidden('id', ['value' => $entity->id]));

        // Portal URL (полный адрес вебхука: https://portal/rest/индекс/токен/)
        $this->add(new Text('portal_url', [
            'placeholder' => 'https://company.bitrix24.ru/rest/1711/abcdef1234567890/',
        ]));

        // Токен: никогда не предзаполняется; плейсхолдер сообщает, сохранён ли он
        $this->add(new Password('webhook_token', [
            'placeholder'  => $options['tokenPlaceholder'] ?? '',
            'autocomplete' => 'new-password',
        ]));

        // Получатели по умолчанию
        $this->add(new Text('recipients', ['placeholder' => '1711, 205']));

        // Карта персональных получателей "внутренний=uid"
        $this->addTextArea('numbers_map', (string)($entity->numbers_map ?? ''), 60, [
            'placeholder' => "201 = 1711\n202 = 305",
        ]);

        // Шаблоны
        $this->addTextArea('template_missed', (string)($entity->template_missed ?? ''), 90, [
            'placeholder' => '{date} {time}: {src} на {dst} — не отвечено',
        ]);
        $this->addTextArea('template_ring', (string)($entity->template_ring ?? ''), 90, [
            'placeholder' => '{time}: звонок {src} на {dst}',
        ]);

        // Антифлуд
        $this->add(new Numeric('throttle_sec', [
            'maxlength' => 4,
            'style'     => 'width: 100px;',
            'min'       => 0,
            'max'       => 86400,
        ]));

        // Тумблеры
        $this->addToggle('notify_missed', (int)($entity->notify_missed ?? 0));
        $this->addToggle('notify_ring', (int)($entity->notify_ring ?? 0));
        $this->addToggle('public_api_enabled', (int)($entity->public_api_enabled ?? 0));

        // Секрет публичного API
        $secretPlaceholder = $options['secretPlaceholder'] ?? '';
        $this->add(new Password('api_secret', [
            'placeholder'  => $secretPlaceholder,
            'autocomplete' => 'new-password',
        ]));
    }

    /**
     * Тумблер (ui toggle checkbox): 1 = включено.
     *
     * Phalcon 5.9 (Html\Helper\Input\Checkbox::processChecked) рендерит checked
     * ТОЛЬКО при равенстве checked === value. Поэтому оба атрибута = '1';
     * варианты 'checked' => 'checked' или value=null молча не рендерят отметку.
     */
    private function addToggle(string $name, int $checked): void
    {
        $attrs = $checked === 1
            ? ['value' => '1', 'checked' => '1']
            : ['value' => '1'];
        $this->add(new Check($name, $attrs));
    }
}
