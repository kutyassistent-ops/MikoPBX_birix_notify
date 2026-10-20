<div class="ui grid module-bx-grid">
    <!-- ЛЕВАЯ КОЛОНКА: настройки -->
    <div class="eleven wide column">
        {{ form(['action': 'module-bitrix-notify/module-bitrix-notify/save', 'method': 'post', 'role': 'form', 'class': 'ui large form', 'id': 'module-bitrix-notify-form']) }}
        {{ form.render('id') }}

        {# ===== Подключение ===== #}
        <div class="ui attached blue segment">
            <div class="ui ribbon blue label">
                <i class="plug icon"></i> {{ t._('module_bitrix_notify_ConnectTitle') }}
            </div>
            <div class="sixteen wide field">
                <label>{{ t._('module_bitrix_notify_PortalUrl') }}</label>
                {{ form.render('portal_url') }}
                <div class="ui small pointing label">
                    {{ t._('module_bitrix_notify_WebhookHint') }}
                </div>
            </div>
            <div class="sixteen wide field">
                <label>{{ t._('module_bitrix_notify_Token') }}</label>
                {{ form.render('webhook_token') }}
                <div class="ui small pointing label">
                    {{ t._('module_bitrix_notify_TokenPriorityHint') }}
                </div>
            </div>
            <div class="sixteen wide field">
                <label>{{ t._('module_bitrix_notify_Recipients') }}</label>
                {{ form.render('recipients') }}
            </div>
        </div>

        {# ===== Уведомления ===== #}
        <div class="ui attached teal segment">
            <div class="ui ribbon teal label">
                <i class="bell icon"></i> {{ t._('module_bitrix_notify_EventsTitle') }}
            </div>

            <div class="field">
                <div class="ui toggle checkbox module-bx-tooltip" data-title="{{ t._('module_bitrix_notify_MissedTooltip') }}">
                    <label>{{ t._('module_bitrix_notify_NotifyMissed') }}</label>
                    {{ form.render('notify_missed') }}
                </div>
            </div>
            <div class="field">
                <div class="ui toggle checkbox module-bx-tooltip" data-title="{{ t._('module_bitrix_notify_RingTooltip') }}">
                    <label>{{ t._('module_bitrix_notify_NotifyRing') }}</label>
                    {{ form.render('notify_ring') }}
                </div>
                <div class="ui small pointing label">
                    {{ t._('module_bitrix_notify_RingMapHint') }}
                </div>
            </div>

            <div class="sixteen wide field">
                <label>{{ t._('module_bitrix_notify_NumbersMap') }}
                    <i class="question circle outline icon module-bx-popup" data-content="{{ t._('module_bitrix_notify_NumbersMapHelp') }}"></i>
                </label>
                {{ form.render('numbers_map') }}
            </div>

            <div class="sixteen wide field">
                <label>{{ t._('module_bitrix_notify_TemplateMissed') }}
                    <i class="question circle outline icon module-bx-popup" data-content="{{ t._('module_bitrix_notify_TemplateHelp') }}"></i>
                </label>
                {{ form.render('template_missed') }}
            </div>

            <div class="sixteen wide field">
                <label>{{ t._('module_bitrix_notify_TemplateRing') }}</label>
                {{ form.render('template_ring') }}
            </div>

            <div class="four wide field">
                <label>{{ t._('module_bitrix_notify_Throttle') }}
                    <i class="question circle outline icon module-bx-popup" data-content="{{ t._('module_bitrix_notify_ThrottleHelp') }}"></i>
                </label>
                {{ form.render('throttle_sec') }}
            </div>
        </div>

        {# ===== Публичный API ===== #}
        <div class="ui attached violet segment">
            <div class="ui ribbon violet label">
                <i class="code icon"></i> {{ t._('module_bitrix_notify_PublicApiTitle') }}
            </div>
            <div class="field">
                <div class="ui toggle checkbox module-bx-tooltip" data-title="{{ t._('module_bitrix_notify_PublicApiTooltip') }}">
                    <label>{{ t._('module_bitrix_notify_PublicApiToggle') }}</label>
                    {{ form.render('public_api_enabled') }}
                </div>
                <div class="ui small pointing label">
                    {{ t._('module_bitrix_notify_PublicApiUrlLabel') }}:
                    <code id="public-api-url">{{ publicApiUrl }}</code>
                </div>
            </div>
            <div class="fourteen wide field">
                <label>{{ t._('module_bitrix_notify_ApiSecret') }}</label>
                <div class="inline fields" style="margin-bottom:0;">
                    <div class="twelve wide field" style="padding-bottom:0;">
                        {{ form.render('api_secret') }}
                    </div>
                    <div class="four wide field" style="padding-bottom:0;">
                        <button type="button" class="ui basic violet button" id="generate-secret-btn">
                            <i class="random icon"></i>{{ t._('module_bitrix_notify_GenerateSecret') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{ partial("partials/submitbutton",['indexurl':'pbx-extension-modules/index/']) }}
        {{ close('form') }}
    </div>

    <!-- ПРАВАЯ КОЛОНКА: диагностика и подсказки -->
    <div class="five wide column">
        <div class="ui green segment" id="bx-diag-panel">
            <h4><i class="heartbeat icon"></i> {{ t._('module_bitrix_notify_DiagTitle') }}</h4>

            <div class="field">
                <button type="button" class="ui green button module-bx-check" id="bx-check-btn">
                    <i class="sync icon"></i>{{ t._('module_bitrix_notify_DiagCheck') }}
                </button>
            </div>
            <div id="bx-check-result" class="module-bx-result" style="display:none;">
                <div class="ui list">
                    <div class="item"><i class="circle icon"></i><b>{{ t._('module_bitrix_notify_DiagMode') }}:</b> <span id="bx-mode"></span></div>
                    <div class="item"><i class="globe icon"></i><b>{{ t._('module_bitrix_notify_DiagPortal') }}:</b> <span id="bx-portal"></span></div>
                    <div class="item"><i class="key icon"></i><b>{{ t._('module_bitrix_notify_DiagToken') }}:</b> <span id="bx-token"></span></div>
                    <div class="item"><i class="users icon"></i><b>{{ t._('module_bitrix_notify_DiagRecipients') }}:</b> <span id="bx-rec"></span></div>
                    <div class="item"><i class="list icon"></i><b>{{ t._('module_bitrix_notify_DiagMap') }}:</b> <span id="bx-map"></span></div>
                    <div class="item"><i class="shield icon"></i><b>{{ t._('module_bitrix_notify_DiagPublic') }}:</b> <span id="bx-public"></span></div>
                </div>
                <div id="bx-check-error" class="ui red message" style="display:none;"></div>
            </div>
        </div>

        <div class="ui blue segment">
            <h4><i class="flask icon"></i> {{ t._('module_bitrix_notify_TestTitle') }}</h4>

            <div class="field">
                <button type="button" class="ui basic blue button module-bx-test" id="bx-test-missed-btn">
                    <i class="phone slash icon"></i>{{ t._('module_bitrix_notify_TestMissed') }}
                </button>
            </div>
            <div class="field">
                <button type="button" class="ui basic blue button module-bx-test" id="bx-test-ping-btn">
                    <i class="paper plane icon"></i>{{ t._('module_bitrix_notify_TestPing') }}
                </button>
            </div>
            <div id="bx-test-result" class="module-bx-result" style="display:none;">
                <div class="ui small message" id="bx-test-message"></div>
            </div>
        </div>

        <div class="ui grey segment">
            <h4><i class="life ring icon"></i> {{ t._('module_bitrix_notify_HelpTitle') }}</h4>
            <div class="ui bulleted list module-bx-help">
                <div class="item">{{ t._('module_bitrix_notify_HelpPlaceholders') }}</div>
                <div class="item">{{ t._('module_bitrix_notify_HelpPlaceholdersList') }}</div>
                <div class="item">{{ t._('module_bitrix_notify_HelpRouting') }}</div>
                <div class="item">{{ t._('module_bitrix_notify_HelpModes') }}</div>
            </div>
        </div>
    </div>
</div>
