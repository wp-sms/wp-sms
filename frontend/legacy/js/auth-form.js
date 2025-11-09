/**
 * WP-SMS Authentication Form - Modern Minimal
 * Version: 3.0.0
 * 
 * Features:
 * - Pure JavaScript + jQuery
 * - Smooth transitions
 * - Desktop-first responsive
 * - Modern minimal design
 */

(function($) {
    'use strict';

    /**
     * API Client
     */
    const API = {
        call: function(endpoint, method, data) {
            return $.ajax({
                url: endpoint,
                method: method,
                contentType: 'application/json',
                data: data ? JSON.stringify(data) : null,
                headers: {
                    'X-WP-Nonce': wpsmsAuthConfig.nonces.rest
                },
                dataType: 'json'
            });
        },

        register: {
            init: function() {
                return API.call(wpsmsAuthConfig.endpoints.register.init, 'GET');
            },
            start: function(data) {
                return API.call(wpsmsAuthConfig.endpoints.register.start, 'POST', data);
            },
            verify: function(data) {
                return API.call(wpsmsAuthConfig.endpoints.register.verify, 'POST', data);
            },
            addIdentifier: function(data) {
                return API.call(wpsmsAuthConfig.endpoints.register.addIdentifier, 'POST', data);
            }
        },

        login: {
            init: function() {
                return API.call(wpsmsAuthConfig.endpoints.login.init, 'GET');
            },
            start: function(data) {
                return API.call(wpsmsAuthConfig.endpoints.login.start, 'POST', data);
            },
            verify: function(data) {
                return API.call(wpsmsAuthConfig.endpoints.login.verify, 'POST', data);
            },
            mfaChallenge: function(data) {
                return API.call(wpsmsAuthConfig.endpoints.login.mfaChallenge, 'POST', data);
            },
            mfaVerify: function(data) {
                return API.call(wpsmsAuthConfig.endpoints.login.mfaVerify, 'POST', data);
            }
        },

        passwordReset: {
            init: function(data) {
                return API.call(wpsmsAuthConfig.endpoints.passwordReset.init, 'POST', data);
            },
            verify: function(data) {
                return API.call(wpsmsAuthConfig.endpoints.passwordReset.verify, 'POST', data);
            },
            complete: function(data) {
                return API.call(wpsmsAuthConfig.endpoints.passwordReset.complete, 'POST', data);
            }
        }
    };

    /**
     * Main Auth Form Class
     */
    class WPSMSAuthForm {
        constructor($container) {
            this.$container = $container;
            this.props = this.parseProps();
            this.state = {
                step: 'initial',
                mode: this.props.mode || 'login',
                currentTab: this.props.default_tab || 'login',
                currentMethod: this.getDefaultMethod(),
                flowId: null,
                mfaFlowId: null,
                originalFlowId: null,
                currentIdentifier: null,
                identifierType: null,
                nextIdentifierType: null,
                availableFactors: [],
                selectedFactor: null,
                resetToken: null,
                loading: false
            };

            this.init();
        }

        /**
         * Parse props from data attribute
         */
        parseProps() {
            try {
                const data = this.$container.data('props');
                return {
                    mode: data.mode || 'auth',
                    methods: data.methods || ['otp', 'magic'],
                    tabs: data.tabs || false,
                    default_tab: data.default_tab || 'login',
                    redirect: data.redirect || null,
                    fields: data.fields || ['email', 'phone'],
                    mfa: data.mfa !== undefined ? data.mfa : true,
                    globals: data.globals || {}
                };
            } catch (e) {
                console.error('Parse props error:', e);
                return {
                    mode: 'auth',
                    methods: ['otp'],
                    tabs: false,
                    default_tab: 'login',
                    redirect: null,
                    fields: ['email', 'phone'],
                    mfa: true,
                    globals: {}
                };
            }
        }

        /**
         * Get default method
         */
        getDefaultMethod() {
            const methods = this.props.methods || [];
            if (methods.indexOf('otp') !== -1) return 'otp';
            if (methods.indexOf('magic') !== -1) return 'magic';
            if (methods.indexOf('password') !== -1) return 'password';
            return methods[0] || 'otp';
        }

        /**
         * Initialize
         */
        init() {
            this.render();
            this.bindEvents();
        }

        /**
         * Render form
         */
        render() {
            const self = this;
            
            // Fade out
            this.$container.find('.wpsms-auth-form__content').addClass('transitioning');
            
            setTimeout(function() {
                let html = '<div class="wpsms-auth-form">';

                // Tabs
                if (self.props.tabs && self.props.mode === 'auth') {
                    html += self.renderTabs();
                }

                // Method switcher
                if (self.props.methods.length > 1 && self.state.step === 'initial') {
                    html += self.renderMethodSwitcher();
                }

                // Content
                html += '<div class="wpsms-auth-form__content">';
                html += self.renderContent();
                html += '</div>';

                // Status
                html += '<div class="wpsms-auth-form__status"></div>';

                html += '</div>';

                self.$container.html(html);
                
                // Re-bind events after render
                self.bindEvents();
                
                // Fade in
                setTimeout(function() {
                    self.$container.find('.wpsms-auth-form__content').removeClass('transitioning');
                }, 50);
            }, 150);
        }

        /**
         * Render content based on step
         */
        renderContent() {
            switch (this.state.step) {
                case 'initial':
                    return this.renderInitialForm();
                case 'verify':
                    return this.renderVerifyForm();
                case 'mfa':
                    return this.renderMfaForm();
                case 'add_identifier':
                    return this.renderAddIdentifierForm();
                case 'reset_password':
                    return this.renderResetPasswordForm();
                case 'complete':
                    return this.renderComplete();
                default:
                    return this.renderInitialForm();
            }
        }

        /**
         * Render tabs
         */
        renderTabs() {
            return `
                <div class="wpsms-auth-form__tabs">
                    <button type="button" class="wpsms-auth-form__tab ${this.state.currentTab === 'login' ? 'active' : ''}" data-tab="login">
                        Sign In
                    </button>
                    <button type="button" class="wpsms-auth-form__tab ${this.state.currentTab === 'register' ? 'active' : ''}" data-tab="register">
                        Sign Up
                    </button>
                </div>
            `;
        }

        /**
         * Render method switcher
         */
        renderMethodSwitcher() {
            const labels = {
                'otp': 'OTP Code',
                'magic': 'Magic Link',
                'password': 'Password'
            };

            return `
                <div class="wpsms-auth-form__methods">
                    ${this.props.methods.map(method => `
                        <button type="button" class="wpsms-auth-form__method ${this.state.currentMethod === method ? 'active' : ''}" data-method="${method}">
                            ${labels[method] || method}
                        </button>
                    `).join('')}
                </div>
            `;
        }

        /**
         * Render initial form
         */
        renderInitialForm() {
            const isRegister = this.state.currentTab === 'register';
            const isPasswordReset = this.state.mode === 'password_reset';
            const isLogin = this.state.currentTab === 'login' && !isPasswordReset;

            let buttonText = isPasswordReset ? 'Reset Password' : (isRegister ? 'Create Account' : 'Sign In');

            return `
                <form class="wpsms-auth-form__form" data-form="initial">
                    <div class="wpsms-auth-form__field">
                        <label class="wpsms-auth-form__label" for="identifier">
                            Email or Phone Number
                        </label>
                        <input 
                            type="text" 
                            id="identifier" 
                            name="identifier" 
                            class="wpsms-auth-form__input"
                            placeholder="Enter your email or phone"
                            autocomplete="username"
                            required
                            ${this.state.loading ? 'disabled' : ''}
                        />
                    </div>

                    <button type="submit" class="wpsms-auth-form__button wpsms-auth-form__button--primary" ${this.state.loading ? 'disabled' : ''}>
                        ${this.state.loading ? '<span class="wpsms-auth-form__spinner"></span>Loading...' : buttonText}
                    </button>

                    ${isLogin && !isPasswordReset ? `
                        <div class="wpsms-auth-form__footer">
                            <button type="button" class="wpsms-auth-form__link" data-action="forgot-password">
                                Forgot Password?
                            </button>
                        </div>
                    ` : ''}

                    ${isPasswordReset ? `
                        <div class="wpsms-auth-form__footer">
                            <button type="button" class="wpsms-auth-form__link" data-action="back-to-login">
                                ← Back to Login
                            </button>
                        </div>
                    ` : ''}
                </form>
            `;
        }

        /**
         * Render verify form
         */
        renderVerifyForm() {
            const isMagicLink = this.state.currentMethod === 'magic';

            return `
                <div class="wpsms-auth-form__message wpsms-auth-form__message--info">
                    ${isMagicLink 
                        ? 'We sent a magic link to <strong>' + this.state.currentIdentifier + '</strong>'
                        : 'We sent a verification code to <strong>' + this.state.currentIdentifier + '</strong>'
                    }
                </div>

                ${!isMagicLink ? `
                    <form class="wpsms-auth-form__form" data-form="verify">
                        <div class="wpsms-auth-form__field">
                            <label class="wpsms-auth-form__label" for="code">
                                Verification Code
                            </label>
                            <input 
                                type="text" 
                                id="code" 
                                name="code" 
                                class="wpsms-auth-form__input wpsms-auth-form__input--code"
                                placeholder="000000"
                                autocomplete="one-time-code"
                                maxlength="6"
                                required
                                ${this.state.loading ? 'disabled' : ''}
                            />
                        </div>

                        <button type="submit" class="wpsms-auth-form__button wpsms-auth-form__button--primary" ${this.state.loading ? 'disabled' : ''}>
                            ${this.state.loading ? '<span class="wpsms-auth-form__spinner"></span>Verifying...' : 'Verify'}
                        </button>

                        <div class="wpsms-auth-form__actions">
                            <button type="button" class="wpsms-auth-form__link" data-action="resend">
                                Resend Code
                            </button>
                            <button type="button" class="wpsms-auth-form__link" data-action="change-identifier">
                                Change
                            </button>
                        </div>
                    </form>
                ` : `
                    <div class="wpsms-auth-form__message wpsms-auth-form__message--info">
                        Please check your ${this.state.identifierType === 'email' ? 'email' : 'messages'} and click the magic link to continue.
                    </div>
                    <div class="wpsms-auth-form__actions">
                        <button type="button" class="wpsms-auth-form__link" data-action="resend">
                            Resend Link
                        </button>
                        <button type="button" class="wpsms-auth-form__link" data-action="change-identifier">
                            Change
                        </button>
                    </div>
                `}
            `;
        }

        /**
         * Render MFA form
         */
        renderMfaForm() {
            if (!this.state.selectedFactor) {
                // Check if we have any MFA factors available
                if (!this.state.availableFactors || this.state.availableFactors.length === 0) {
                    return `
                        <div class="wpsms-auth-form__message wpsms-auth-form__message--error">
                            No additional verification methods available. Please contact support.
                        </div>
                    `;
                }

                // Factor selection
                return `
                    <div class="wpsms-auth-form__message wpsms-auth-form__message--info">
                        Select your second verification method
                    </div>

                    <div class="wpsms-auth-form__factors">
                        ${this.state.availableFactors.map(factor => `
                            <button type="button" class="wpsms-auth-form__factor" data-factor="${factor.type}">
                                <span class="wpsms-auth-form__factor-icon">${this.getFactorIcon(factor.type)}</span>
                                <span class="wpsms-auth-form__factor-label">
                                    ${this.getFactorLabel(factor.type)}
                                    ${factor.masked ? '<br><small style="color: #9ca3af;">' + factor.masked + '</small>' : ''}
                                </span>
                            </button>
                        `).join('')}
                    </div>
                `;
            }

            // Factor verification
            return `
                <div class="wpsms-auth-form__message wpsms-auth-form__message--info">
                    Enter the verification code sent to your ${this.state.selectedFactor}
                </div>

                <form class="wpsms-auth-form__form" data-form="mfa-verify">
                    <div class="wpsms-auth-form__field">
                        <label class="wpsms-auth-form__label" for="mfa_code">
                            MFA Code
                        </label>
                        <input 
                            type="text" 
                            id="mfa_code" 
                            name="mfa_code" 
                            class="wpsms-auth-form__input wpsms-auth-form__input--code"
                            placeholder="000000"
                            autocomplete="one-time-code"
                            maxlength="6"
                            required
                            ${this.state.loading ? 'disabled' : ''}
                        />
                    </div>

                    <button type="submit" class="wpsms-auth-form__button wpsms-auth-form__button--primary" ${this.state.loading ? 'disabled' : ''}>
                        ${this.state.loading ? '<span class="wpsms-auth-form__spinner"></span>Verifying...' : 'Verify MFA'}
                    </button>

                    <div class="wpsms-auth-form__footer">
                        <button type="button" class="wpsms-auth-form__link" data-action="change-factor">
                            ← Use Different Method
                        </button>
                    </div>
                </form>
            `;
        }

        /**
         * Render add identifier form
         */
        renderAddIdentifierForm() {
            if (!this.state.nextIdentifierType) {
                return this.renderComplete();
            }

            const identifierType = this.state.nextIdentifierType;
            
            // Check if this identifier is required or optional
            const channelSettings = this.props.globals?.channelSettings || {};
            const channels = channelSettings.channels || {};
            const isRequired = channels[identifierType]?.required || false;

            return `
                <div class="wpsms-auth-form__message wpsms-auth-form__message--info">
                    ${isRequired ? 'Please add your ' : 'Optionally add your '} ${identifierType}
                </div>

                <form class="wpsms-auth-form__form" data-form="add-identifier">
                    <div class="wpsms-auth-form__field">
                        <label class="wpsms-auth-form__label" for="new_identifier">
                            ${identifierType === 'email' ? 'Email Address' : 'Phone Number'}
                        </label>
                        <input 
                            type="text" 
                            id="new_identifier" 
                            name="new_identifier" 
                            class="wpsms-auth-form__input"
                            placeholder="${identifierType === 'email' ? 'you@example.com' : '+1234567890'}"
                            ${isRequired ? 'required' : ''}
                            ${this.state.loading ? 'disabled' : ''}
                        />
                    </div>

                    <div class="wpsms-auth-form__button-group">
                        <button type="submit" class="wpsms-auth-form__button wpsms-auth-form__button--primary" ${this.state.loading ? 'disabled' : ''}>
                            ${this.state.loading ? '<span class="wpsms-auth-form__spinner"></span>Sending...' : 'Continue'}
                        </button>
                        ${!isRequired ? `
                            <button type="button" class="wpsms-auth-form__button wpsms-auth-form__button--secondary" data-action="skip" data-identifier-type="${identifierType}">
                                Skip
                            </button>
                        ` : ''}
                    </div>
                </form>
            `;
        }

        /**
         * Render password reset form
         */
        renderResetPasswordForm() {
            return `
                <div class="wpsms-auth-form__message wpsms-auth-form__message--info">
                    Create a new password for your account
                </div>

                <form class="wpsms-auth-form__form" data-form="reset-password">
                    <div class="wpsms-auth-form__field">
                        <label class="wpsms-auth-form__label" for="new_password">
                            New Password
                        </label>
                        <input 
                            type="password" 
                            id="new_password" 
                            name="new_password" 
                            class="wpsms-auth-form__input"
                            placeholder="Enter new password"
                            autocomplete="new-password"
                            minlength="8"
                            required
                            ${this.state.loading ? 'disabled' : ''}
                        />
                    </div>

                    <div class="wpsms-auth-form__field">
                        <label class="wpsms-auth-form__label" for="confirm_password">
                            Confirm Password
                        </label>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            class="wpsms-auth-form__input"
                            placeholder="Confirm new password"
                            autocomplete="new-password"
                            minlength="8"
                            required
                            ${this.state.loading ? 'disabled' : ''}
                        />
                    </div>

                    <button type="submit" class="wpsms-auth-form__button wpsms-auth-form__button--primary" ${this.state.loading ? 'disabled' : ''}>
                        ${this.state.loading ? '<span class="wpsms-auth-form__spinner"></span>Resetting...' : 'Reset Password'}
                    </button>
                </form>
            `;
        }

        /**
         * Render complete
         */
        renderComplete() {
            const isRegister = this.state.currentTab === 'register';
            const isPasswordReset = this.state.mode === 'password_reset';

            let message = 'Login successful!';
            if (isPasswordReset) message = 'Password reset successful!';
            if (isRegister) message = 'Registration complete!';

            return `
                <div class="wpsms-auth-form__complete">
                    <div class="wpsms-auth-form__complete-icon">✓</div>
                    <h3 class="wpsms-auth-form__complete-title">${message}</h3>
                    <p class="wpsms-auth-form__complete-message">Redirecting you now...</p>
                </div>
            `;
        }

        /**
         * Bind events
         */
        bindEvents() {
            const self = this;

            // Tab switch
            this.$container.off('click', '[data-tab]').on('click', '[data-tab]', function(e) {
                e.preventDefault();
                self.switchTab($(this).data('tab'));
            });

            // Method switch
            this.$container.off('click', '[data-method]').on('click', '[data-method]', function(e) {
                e.preventDefault();
                self.switchMethod($(this).data('method'));
            });

            // Form submit
            this.$container.off('submit', 'form').on('submit', 'form', function(e) {
                e.preventDefault();
                self.handleSubmit($(this));
            });

            // Actions
            this.$container.off('click', '[data-action]').on('click', '[data-action]', function(e) {
                e.preventDefault();
                self.handleAction($(this).data('action'), $(this));
            });

            // Factor selection
            this.$container.off('click', '[data-factor]').on('click', '[data-factor]', function(e) {
                e.preventDefault();
                self.selectFactor($(this).data('factor'));
            });
        }

        /**
         * Switch tab
         */
        switchTab(tab) {
            this.state.currentTab = tab;
            this.state.step = 'initial';
            this.state.flowId = null;
            this.clearStatus();
            this.render();
        }

        /**
         * Switch method
         */
        switchMethod(method) {
            this.state.currentMethod = method;
            this.render();
        }

        /**
         * Handle form submit
         */
        handleSubmit($form) {
            const formType = $form.data('form');

            switch (formType) {
                case 'initial':
                    this.handleInitial($form);
                    break;
                case 'verify':
                    this.handleVerify($form);
                    break;
                case 'mfa-verify':
                    this.handleMfaVerify($form);
                    break;
                case 'add-identifier':
                    this.handleAddIdentifier($form);
                    break;
                case 'reset-password':
                    this.handleResetPassword($form);
                    break;
            }
        }

        /**
         * Handle initial submit
         */
        handleInitial($form) {
            const self = this;
            const identifier = $form.find('[name="identifier"]').val();

            if (!identifier) {
                this.showError('Please enter your email or phone number');
                return;
            }

            this.setLoading(true);
            this.clearStatus();

            const data = {
                identifier: identifier,
                auth_method: this.state.currentMethod
            };

            let apiCall;
            if (this.state.mode === 'password_reset') {
                apiCall = API.passwordReset.init(data);
            } else if (this.state.currentTab === 'register') {
                apiCall = API.register.start(data);
            } else {
                apiCall = API.login.start(data);
            }

            apiCall.done(function(response) {
                self.state.flowId = response.flow_id || response.data.flow_id;
                self.state.currentIdentifier = identifier;
                self.state.identifierType = response.identifier_type || response.data.identifier_type;
                self.state.step = 'verify';
                self.render();
                self.showSuccess('Code sent successfully!');
            }).fail(function(xhr) {
                self.showError(xhr.responseJSON?.message || 'Failed to send code');
            }).always(function() {
                self.setLoading(false);
            });
        }

        /**
         * Handle verify submit
         */
        handleVerify($form) {
            const self = this;
            const code = $form.find('[name="code"]').val();

            if (!code) {
                this.showError('Please enter the verification code');
                return;
            }

            this.setLoading(true);
            this.clearStatus();

            const data = {
                flow_id: this.state.flowId,
                identifier: this.state.currentIdentifier
            };

            // Check if magic link or OTP
            if (this.state.currentMethod === 'magic') {
                data.magic_token = code;
            } else {
                data.otp_code = code;
            }

            let apiCall;
            if (this.state.mode === 'password_reset') {
                apiCall = API.passwordReset.verify(data);
            } else if (this.state.currentTab === 'register') {
                apiCall = API.register.verify(data);
            } else {
                apiCall = API.login.verify(data);
            }

            apiCall.done(function(response) {
                if (self.state.mode === 'password_reset') {
                    // Password reset verified
                    self.state.resetToken = response.data.reset_token;
                    self.state.step = 'reset_password';
                    self.render();
                } else if (self.state.currentTab === 'register') {
                    // Registration verified
                    if (response.data.registration_complete || response.data.status === 'complete') {
                        self.handleComplete(response.data.redirect_url);
                    } else if (response.data.next_step === 'verify_next' && response.data.next_required_identifier) {
                        // More identifiers needed
                        self.state.nextIdentifierType = response.data.next_required_identifier;
                        self.state.step = 'add_identifier';
                        self.render();
                    } else {
                        self.handleComplete(response.data.redirect_url);
                    }
                } else {
                    // Login verified
                    if (response.data.mfa_required) {
                        // Filter out the primary auth method from MFA options
                        let mfaOptions = response.data.available_factors || response.data.mfa_options || [];
                        
                        // Remove the identifier type that was used for primary auth
                        mfaOptions = mfaOptions.filter(function(option) {
                            return option.type !== self.state.identifierType;
                        });
                        
                        self.state.availableFactors = mfaOptions;
                        self.state.step = 'mfa';
                        self.render();
                    } else {
                        self.handleComplete(response.data.redirect_url);
                    }
                }
            }).fail(function(xhr) {
                self.showError(xhr.responseJSON?.message || 'Verification failed');
            }).always(function() {
                self.setLoading(false);
            });
        }

        /**
         * Handle MFA verify
         */
        handleMfaVerify($form) {
            const self = this;
            const code = $form.find('[name="mfa_code"]').val();

            if (!code) {
                this.showError('Please enter the MFA code');
                return;
            }

            this.setLoading(true);
            this.clearStatus();

            API.login.mfaVerify({
                flow_id: this.state.originalFlowId || this.state.flowId,
                mfa_flow_id: this.state.mfaFlowId,
                otp_code: code
            }).done(function(response) {
                self.handleComplete(response.data.redirect_url);
            }).fail(function(xhr) {
                self.showError(xhr.responseJSON?.message || 'MFA verification failed');
            }).always(function() {
                self.setLoading(false);
            });
        }

        /**
         * Handle add identifier
         */
        handleAddIdentifier($form) {
            const self = this;
            const newIdentifier = $form.find('[name="new_identifier"]').val();

            if (!newIdentifier) {
                this.showError('Please enter the identifier');
                return;
            }

            this.setLoading(true);
            this.clearStatus();

            API.register.addIdentifier({
                flow_id: this.state.flowId,
                identifier: newIdentifier,
                auth_method: this.state.currentMethod
            }).done(function(response) {
                // Update flow_id with the new one from response
                self.state.flowId = response.flow_id || response.data.flow_id;
                self.state.currentIdentifier = newIdentifier;
                self.state.identifierType = response.identifier_type || response.data.identifier_type;
                self.state.step = 'verify';
                self.render();
                self.showSuccess('Code sent!');
            }).fail(function(xhr) {
                self.showError(xhr.responseJSON?.message || 'Failed to add identifier');
            }).always(function() {
                self.setLoading(false);
            });
        }

        /**
         * Handle password reset
         */
        handleResetPassword($form) {
            const self = this;
            const newPassword = $form.find('[name="new_password"]').val();
            const confirmPassword = $form.find('[name="confirm_password"]').val();

            if (!newPassword || !confirmPassword) {
                this.showError('Please fill in all fields');
                return;
            }

            if (newPassword !== confirmPassword) {
                this.showError('Passwords do not match');
                return;
            }

            this.setLoading(true);
            this.clearStatus();

            API.passwordReset.complete({
                reset_token: this.state.resetToken,
                new_password: newPassword,
                confirm_password: confirmPassword
            }).done(function(response) {
                self.handleComplete(response.data.redirect_url);
            }).fail(function(xhr) {
                self.showError(xhr.responseJSON?.message || 'Failed to reset password');
            }).always(function() {
                self.setLoading(false);
            });
        }

        /**
         * Handle actions
         */
        handleAction(action, $button) {
            switch (action) {
                case 'resend':
                    this.handleResend();
                    break;
                case 'change-identifier':
                    this.changeIdentifier();
                    break;
                case 'change-factor':
                    this.changeFactor();
                    break;
                case 'skip':
                    this.handleSkip($button.data('identifier-type'));
                    break;
                case 'forgot-password':
                    this.showPasswordReset();
                    break;
                case 'back-to-login':
                    this.backToLogin();
                    break;
            }
        }

        /**
         * Select MFA factor
         */
        selectFactor(factor) {
            const self = this;
            this.setLoading(true);

            API.login.mfaChallenge({
                flow_id: this.state.flowId,
                mfa_method: factor
            }).done(function(response) {
                self.state.selectedFactor = factor;
                // Store the MFA flow ID separately
                self.state.mfaFlowId = response.data.mfa_flow_id;
                // Keep the original flow_id for MFA verify
                if (!self.state.originalFlowId) {
                    self.state.originalFlowId = self.state.flowId;
                }
                self.render();
                self.showSuccess('Code sent!');
            }).fail(function(xhr) {
                self.showError(xhr.responseJSON?.message || 'Failed to send MFA code');
            }).always(function() {
                self.setLoading(false);
            });
        }

        /**
         * Handle resend
         */
        handleResend() {
            const self = this;
            this.setLoading(true);

            const data = {
                identifier: this.state.currentIdentifier,
                auth_method: this.state.currentMethod
            };

            let apiCall;
            if (this.state.mode === 'password_reset') {
                apiCall = API.passwordReset.init(data);
            } else if (this.state.currentTab === 'register') {
                apiCall = API.register.start(data);
            } else {
                apiCall = API.login.start(data);
            }

            apiCall.done(function(response) {
                self.state.flowId = response.flow_id || response.data.flow_id;
                self.showSuccess('Code resent successfully!');
            }).fail(function(xhr) {
                self.showError(xhr.responseJSON?.message || 'Failed to resend code');
            }).always(function() {
                self.setLoading(false);
            });
        }

        /**
         * Handle skip
         */
        handleSkip(identifierType) {
            const self = this;
            this.setLoading(true);

            API.register.verify({
                flow_id: this.state.flowId,
                identifier_type: identifierType,
                action: 'skip'
            }).done(function(response) {
                if (response.data.registration_complete || response.data.status === 'complete') {
                    self.handleComplete(response.data.redirect_url);
                } else if (response.data.next_step === 'verify_next' && response.data.next_required_identifier) {
                    self.state.nextIdentifierType = response.data.next_required_identifier;
                    self.state.step = 'add_identifier';
                    self.render();
                } else {
                    self.handleComplete(response.data.redirect_url);
                }
            }).fail(function(xhr) {
                self.showError(xhr.responseJSON?.message || 'Failed to skip');
            }).always(function() {
                self.setLoading(false);
            });
        }

        /**
         * Change identifier
         */
        changeIdentifier() {
            this.state.step = 'initial';
            this.state.flowId = null;
            this.clearStatus();
            this.render();
        }

        /**
         * Change factor
         */
        changeFactor() {
            this.state.selectedFactor = null;
            this.render();
        }

        /**
         * Show password reset
         */
        showPasswordReset() {
            this.state.mode = 'password_reset';
            this.state.step = 'initial';
            this.state.flowId = null;
            this.clearStatus();
            this.render();
        }

        /**
         * Back to login
         */
        backToLogin() {
            this.state.mode = this.props.mode || 'login';
            this.state.currentTab = 'login';
            this.state.step = 'initial';
            this.state.flowId = null;
            this.state.resetToken = null;
            this.clearStatus();
            this.render();
        }

        /**
         * Handle completion
         */
        handleComplete(redirectUrl) {
            const self = this;
            this.state.step = 'complete';
            this.render();

            const finalRedirect = redirectUrl || this.props.redirect || '/';

            setTimeout(function() {
                window.location.href = finalRedirect;
            }, 2000);
        }

        /**
         * Set loading state
         */
        setLoading(loading) {
            this.state.loading = loading;
            
            // Disable all inputs and buttons
            if (loading) {
                this.$container.find('input, button').prop('disabled', true);
            } else {
                this.$container.find('input, button').prop('disabled', false);
            }
        }

        /**
         * Show success message
         */
        showSuccess(message) {
            const $status = this.$container.find('.wpsms-auth-form__status');
            $status.removeClass('wpsms-auth-form__status--error')
                   .addClass('wpsms-auth-form__status--success')
                   .text(message)
                   .fadeIn(300);

            setTimeout(function() {
                $status.fadeOut(300);
            }, 5000);
        }

        /**
         * Show error message
         */
        showError(message) {
            const $status = this.$container.find('.wpsms-auth-form__status');
            $status.removeClass('wpsms-auth-form__status--success')
                   .addClass('wpsms-auth-form__status--error')
                   .text(message)
                   .fadeIn(300);
        }

        /**
         * Clear status
         */
        clearStatus() {
            this.$container.find('.wpsms-auth-form__status')
                          .removeClass('wpsms-auth-form__status--success wpsms-auth-form__status--error')
                          .text('')
                          .hide();
        }

        /**
         * Get factor icon
         */
        getFactorIcon(type) {
            const icons = {
                'email': '✉',
                'phone': '📱',
                'totp': '🔐',
                'webauthn': '🔑',
                'biometric': '🔑'
            };
            return icons[type] || '●';
        }

        /**
         * Get factor label
         */
        getFactorLabel(type) {
            const labels = {
                'email': 'Email',
                'phone': 'Phone',
                'totp': 'Authenticator App',
                'webauthn': 'Security Key',
                'biometric': 'Biometric'
            };
            return labels[type] || type;
        }
    }

    /**
     * Initialize all forms
     */
    $(document).ready(function() {
        $('.wpsms-auth').each(function() {
            if (!$(this).data('wpsms-initialized')) {
                $(this).data('wpsms-initialized', true);
                new WPSMSAuthForm($(this));
            }
        });
    });

    // Make available globally
    window.WPSMSAuthForm = WPSMSAuthForm;

})(jQuery);
