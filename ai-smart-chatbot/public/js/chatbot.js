(function($) {
    'use strict';

    class AIChatbotWidget {
        constructor() {
            this.container = $('#aisc-widget');
            this.messagesContainer = $('#aisc-messages');
            this.input = $('#aisc-input');
            this.sendBtn = $('#aisc-send');
            this.toggle = $('#aisc-toggle');
            this.sessionId = this.generateSessionId();
            this.isTyping = false;
            this.isOpen = false;

            this.init();
        }

        init() {
            this.bindEvents();
            this.loadSettings();
        }

        bindEvents() {
            this.toggle.on('click', () => this.toggleChat());
            this.sendBtn.on('click', () => this.sendMessage());
            this.input.on('keypress', (e) => {
                if (e.which === 13) {
                    this.sendMessage();
                }
            });
            this.input.on('input', () => this.adjustInputHeight());
        }

        loadSettings() {
            if (window.aiscData && window.aiscData.settings) {
                const settings = window.aiscData.settings;

                if (settings.darkMode) {
                    this.container.addClass('aisc-dark-mode');
                }

                if (settings.widgetStyle === 'fullscreen') {
                    this.container.addClass('aisc-fullscreen');
                } else if (settings.widgetStyle === 'sidebar') {
                    this.container.addClass('aisc-sidebar');
                }

                if (settings.primaryColor) {
                    document.documentElement.style.setProperty('--aisc-primary', settings.primaryColor);
                    document.documentElement.style.setProperty('--aisc-user-msg', settings.primaryColor);
                }

                if (settings.secondaryColor) {
                    document.documentElement.style.setProperty('--aisc-secondary', settings.secondaryColor);
                    document.documentElement.style.setProperty('--aisc-bot-msg', settings.secondaryColor);
                }

                if (settings.fontFamily && settings.fontFamily !== 'inherit') {
                    document.documentElement.style.setProperty('--aisc-font', settings.fontFamily);
                }
            }
        }

        toggleChat() {
            this.isOpen = !this.isOpen;
            this.container.toggleClass('opened', this.isOpen);

            if (this.isOpen) {
                setTimeout(() => this.input.focus(), 300);
            }
        }

        generateSessionId() {
            const stored = localStorage.getItem('aisc_session_id');
            if (stored) return stored;

            const newId = 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            localStorage.setItem('aisc_session_id', newId);
            return newId;
        }

        sendMessage() {
            const message = this.input.val().trim();
            if (!message || this.isTyping) return;

            this.addMessage(message, 'user');
            this.input.val('');
            this.showTyping();

            $.ajax({
                url: window.aiscData.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'aisc_chat',
                    nonce: window.aiscData.nonce,
                    message: message,
                    session_id: this.sessionId
                },
                success: (response) => {
                    this.hideTyping();
                    if (response.success) {
                        this.addMessage(response.data.answer, 'bot', response.data.sources);
                    } else {
                        this.addMessage(window.aiscData.i18n.error, 'bot');
                    }
                },
                error: () => {
                    this.hideTyping();
                    this.addMessage(window.aiscData.i18n.error, 'bot');
                }
            });
        }

        addMessage(content, type, sources = null) {
            const messageHtml = `
                <div class="aisc-message aisc-message-${type}">
                    <div class="aisc-message-content">${this.escapeHtml(content)}</div>
                </div>
            `;

            this.messagesContainer.append(messageHtml);
            this.scrollToBottom();
        }

        showTyping() {
            this.isTyping = true;
            const typingHtml = `
                <div class="aisc-message aisc-message-bot">
                    <div class="aisc-typing">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
            `;
            this.messagesContainer.append(typingHtml);
            this.scrollToBottom();
        }

        hideTyping() {
            this.isTyping = false;
            this.messagesContainer.find('.aisc-typing').parent().remove();
        }

        scrollToBottom() {
            this.messagesContainer.scrollTop(this.messagesContainer[0].scrollHeight);
        }

        adjustInputHeight() {
            this.input.css('height', 'auto');
            this.input.css('height', Math.min(this.input[0].scrollHeight, 120) + 'px');
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML.replace(/\n/g, '<br>');
        }
    }

    $(document).ready(function() {
        if ($('#aisc-widget').length) {
            new AIChatbotWidget();
        }
    });

})(jQuery);