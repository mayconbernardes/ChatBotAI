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
            this.greetingMessage = '';

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

                // Display Mode
                if (settings.displayMode === 'embedded') {
                    this.container.addClass('aisc-embedded');
                } else if (settings.displayMode === 'fullscreen') {
                    this.container.addClass('aisc-fullscreen');
                } else {
                    this.container.addClass('aisc-floating');
                }

                // Initial State
                if (settings.initialState === 'closed') {
                    this.container.addClass('closed');
                    this.isOpen = false;
                } else {
                    this.container.addClass('opened');
                    this.isOpen = true;
                }

                // Dark Mode
                if (settings.darkMode) {
                    this.container.addClass('aisc-dark-mode');
                }

                // Colors
                if (settings.primaryColor) {
                    document.documentElement.style.setProperty('--aisc-primary', settings.primaryColor);
                    document.documentElement.style.setProperty('--aisc-user-msg', settings.primaryColor);
                }

                if (settings.secondaryColor) {
                    document.documentElement.style.setProperty('--aisc-secondary', settings.secondaryColor);
                    document.documentElement.style.setProperty('--aisc-bot-msg', settings.secondaryColor);
                }

                // Font Family
                if (settings.fontFamily && settings.fontFamily !== 'inherit') {
                    document.documentElement.style.setProperty('--aisc-font', settings.fontFamily);
                }

                // Animations
                if (settings.bounceAnimation) {
                    this.container.addClass('bounce');
                }

                if (settings.pulseAnimation) {
                    this.container.addClass('pulse');
                }

                // Mobile Settings
                const isMobile = window.innerWidth <= 768;
                if (isMobile && settings.mobilePosition) {
                    if (settings.mobilePosition === 'bottom-center') {
                        this.container.addClass('bottom-center-mobile');
                    } else if (settings.mobilePosition === 'fullscreen') {
                        this.container.addClass('fullscreen-mobile');
                    }
                }

                // Auto-open delay
                const autoOpenDelay = parseInt(settings.autoOpenDelay) || 0;
                if (autoOpenDelay > 0 && settings.initialState !== 'open') {
                    setTimeout(() => {
                        if (!this.isOpen) {
                            this.toggleChat();
                        }
                    }, autoOpenDelay * 1000);
                }

                // Store greeting for display when chat opens
                if (settings.greeting) {
                    this.greetingMessage = settings.greeting;
                }
            }
        }

        toggleChat() {
            this.isOpen = !this.isOpen;
            this.container.toggleClass('opened', this.isOpen);

            if (this.isOpen && this.greetingMessage) {
                this.addMessage(this.greetingMessage, 'bot');
                this.greetingMessage = '';
            }

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