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

/* global globalTranslate, globalRootUrl, Form */
'use strict';

/**
 * ModuleBitrixNotify — интерактив диагностики на странице настроек.
 *
 * Все запросы идут на стандартный защищённый роут модуля
 * /pbxcore/api/modules/ModuleBitrixNotify/{action} (админский TokenManager
 * подставляет Authorization: Bearer автоматически).
 */
const moduleBitrixNotify = {
    parentForm: null,
    $formObj: $('#module-bitrix-notify-form'),
    $checkResult: null,
    $testResult: null,

    initialize() {
        this.$checkResult = $('#bx-check-result');
        this.$testResult  = $('#bx-test-result');

        // Semantic UI: тумблеры и дропдауны
        this.$formObj.find('.ui.checkbox').checkbox();
        this.$formObj.find('.ui.dropdown').dropdown();

        this.initializeForm();
        this.initializeTooltips();
        $('#bx-check-btn').on('click', (e) => {
            e.preventDefault();
            this.check();
        });
        $('#bx-test-missed-btn').on('click', (e) => {
            e.preventDefault();
            this.testMissed();
        });
        $('#bx-test-ping-btn').on('click', (e) => {
            e.preventDefault();
            this.testPing();
        });
        $('#generate-secret-btn').on('click', (e) => {
            e.preventDefault();
            this.generateSecret();
        });
    },

    /**
     * Подсказки semantic-ui: popup на иконках + popup на тумблерах.
     */
    initializeTooltips() {
        $('.module-bx-popup').popup({ hoverable: true, position: 'left center' });
        $('.module-bx-tooltip').each(function () {
            const title = $(this).attr('data-title');
            if (title !== undefined && title !== '') {
                $(this).popup({ position: 'left center', hoverable: true });
            }
        });
    },

    /**
     * Инициализация стандартного сохранения формы через админский Form
     * (кнопка <a id="submitbutton"> срабатывает только через него).
     */
    initializeForm() {
        Form.$formObj = this.$formObj;
        Form.url = `${globalRootUrl}module-bitrix-notify/module-bitrix-notify/save`;
        Form.validateRules = {};
        Form.cbBeforeSendForm = (settings) => {
            const vals = this.$formObj.form('get values');
            // Semantic UI отдаёт чекбоксы строкой 'false'/'true' — берём
            // авторитетное состояние из DOM, иначе тумблеры не сохраняются.
            ['notify_missed', 'notify_ring', 'public_api_enabled'].forEach((name) => {
                const el = this.$formObj[0].querySelector(`[name="${name}"]`);
                if (el) {
                    vals[name] = el.checked ? 'on' : 'off';
                }
            });
            settings.data = vals;

            return settings;
        };
        Form.cbAfterSendForm = () => {};
        Form.initialize();
    },

    /**
     * Проверить подключение: настройки, портал, источник токена, режим воркера.
     */
    check() {
        $('#bx-check-btn').addClass('loading disabled');
        $.api({
            url: '/pbxcore/api/modules/ModuleBitrixNotify/check',
            method: 'GET',
            on: 'now',
            onSuccess: (response) => {
                this.renderCheck(response);
            },
            onFailure: (response) => {
                this.renderCheck({ result: false, messages: [response.statusText || 'Request failed'] });
            },
            onError: (errorMessage) => {
                this.renderCheck({ result: false, messages: [errorMessage] });
            },
            onComplete: () => {
                $('#bx-check-btn').removeClass('loading disabled');
            },
        });
    },

    renderCheck(response) {
        const ok = response.result === true;
        this.$checkResult.show();
        $('#bx-check-error').hide();
        $('#bx-mode').text((response.data && response.data.workerMode) ? response.data.workerMode : '—');
        $('#bx-portal').text((response.data && response.data.portal) ? response.data.portal : '—');
        $('#bx-token').text((response.data && response.data.tokenSource) ? response.data.tokenSource : globalTranslate.module_bitrix_notify_DiagTokenNone);
        $('#bx-rec').text(
            (response.data && response.data.defaultRecipients && response.data.defaultRecipients.length > 0)
                ? response.data.defaultRecipients.join(', ')
                : '—'
        );
        $('#bx-map').text((response.data && response.data.numbersMapCount !== undefined) ? response.data.numbersMapCount : '—');
        $('#bx-public').text(
            (response.data && response.data.publicApiEnabled) ? 'on' : 'off'
        );

        if (!ok) {
            const serverMsgs = Array.isArray(response.messages) ? response.messages : [];
            const msg = serverMsgs.join('<br>') || (response.error || response.statusText) || globalTranslate.module_bitrix_notify_DiagFail;
            $('#bx-check-error').html(msg).show();
            $('#bx-check-result .ui.list').css('opacity', 0.6);
        } else {
            $('#bx-check-result .ui.list').css('opacity', 1);
        }
    },

    /**
     * Тест: имитация пропущенного вызова на первом номере карты/дефолт 201.
     */
    testMissed() {
        const $btn = $('#bx-test-missed-btn');
        $btn.addClass('loading disabled');
        $.api({
            url: '/pbxcore/api/modules/ModuleBitrixNotify/test-missed',
            method: 'POST',
            data: {},
            on: 'now',
            onSuccess: (response) => {
                this.renderTest(response);
            },
            onFailure: (response) => {
                this.renderTest({ result: false, messages: [response.statusText || 'Request failed'] });
            },
            onError: (errorMessage) => {
                this.renderTest({ result: false, messages: [errorMessage] });
            },
            onComplete: () => {
                $btn.removeClass('loading disabled');
            },
        });
    },

    /**
     * Тест: отправка тестового сообщения по умолчанию получателям.
     */
    testPing() {
        const $btn = $('#bx-test-ping-btn');
        $btn.addClass('loading disabled');
        $.api({
            url: '/pbxcore/api/modules/ModuleBitrixNotify/ping',
            method: 'POST',
            data: {},
            on: 'now',
            onSuccess: (response) => {
                this.renderTest(response);
            },
            onFailure: (response) => {
                this.renderTest({ result: false, messages: [response.statusText || 'Request failed'] });
            },
            onError: (errorMessage) => {
                this.renderTest({ result: false, messages: [errorMessage] });
            },
            onComplete: () => {
                $btn.removeClass('loading disabled');
            },
        });
    },

    renderTest(response) {
        const ok = response.result === true;
        this.$testResult.show();
        let text;
        if (ok) {
            const d = response.data || {};
            const delivery = [];
            Object.keys(d.delivery || {}).forEach((uid) => {
                delivery.push(`${uid}: ${d.delivery[uid].ok ? '✓' : '✗ ' + (d.delivery[uid].error || '')}`);
            });
            text = `<b>OK</b><br>${globalTranslate.module_bitrix_notify_TestSentTo}: ${delivery.join('; ')}`;
            if (d.message) {
                text += `<br><i>${d.message}</i>`;
            }
            $('#bx-test-message').removeClass('red').addClass('green');
        } else {
            // Ошибка может лежать в data.delivery[*].error — собираем её в текст
            const d = response.data || {};
            const fromDelivery = [];
            Object.keys(d.delivery || {}).forEach((uid) => {
                if (d.delivery[uid] && !d.delivery[uid].ok) {
                    fromDelivery.push(`${uid}: ${d.delivery[uid].error || ''}`);
                }
            });
            const serverMsgs = Array.isArray(response.messages) ? response.messages : [];
            const parts = fromDelivery.length > 0 ? fromDelivery : serverMsgs;
            text = `<b>${globalTranslate.module_bitrix_notify_TestFail}</b><br>${parts.join('<br>')}`;
            $('#bx-test-message').removeClass('green').addClass('red');
        }
        $('#bx-test-message').html(text);
    },

    /**
     * Сгенерировать криптостойкий секрет (48 hex-символов) в поле api_secret.
     */
    generateSecret() {
        const array = new Uint8Array(24);
        crypto.getRandomValues(array);
        const hex = Array.from(array).map(b => b.toString(16).padStart(2, '0')).join('');
        $('input[name="api_secret"]').val(hex);
    },
};

$(document).ready(() => {
    moduleBitrixNotify.initialize();
});
