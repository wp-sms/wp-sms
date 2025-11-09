/**
 * WP-SMS Account Management
 * 
 * Vanilla JavaScript for profile and MFA management
 */

(function() {
    'use strict';

    const config = window.wpSmsAccount || {};
    const i18n = config.i18n || {};

    // Helper: API request
    async function apiRequest(endpoint, options = {}) {
        const url = config.restUrl + endpoint;
        const headers = {
            'Content-Type': 'application/json',
            'X-WP-Nonce': config.nonce
        };

        const response = await fetch(url, {
            ...options,
            headers: { ...headers, ...(options.headers || {}) }
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || i18n.error);
        }

        return data;
    }

    // Helper: Show toast
    function showToast(message, type = 'success') {
        const container = document.querySelector('.wpsms-toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `wpsms-toast wpsms-toast-${type}`;
        toast.textContent = message;
        toast.setAttribute('role', 'alert');

        container.appendChild(toast);

        setTimeout(() => toast.classList.add('wpsms-toast-show'), 10);
        setTimeout(() => {
            toast.classList.remove('wpsms-toast-show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Helper: Show/hide modal
    function showModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.style.display = 'flex';
            modal.querySelector('.wpsms-modal-content').focus();
        }
    }

    function hideModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.style.display = 'none';
        }
    }

    // ==================== Profile Management ====================

    class ProfileManager {
        constructor() {
            this.form = document.getElementById('wpsms-profile-form');
            this.loading = document.getElementById('profile-loading');
            this.error = document.getElementById('profile-error');
            
            this.init();
        }

        async init() {
            await this.loadProfile();
            this.attachEventListeners();
        }

        async loadProfile() {
            try {
                this.loading.style.display = 'block';
                this.error.style.display = 'none';

                const response = await apiRequest('account/me');
                const userData = response.data.user;

                // Populate form
                document.getElementById('first_name').value = userData.first_name || '';
                document.getElementById('last_name').value = userData.last_name || '';
                document.getElementById('display_name').value = userData.display_name || '';
                document.getElementById('username').value = userData.username || '';
                
                // Email
                const emailInput = document.getElementById('email');
                emailInput.value = userData.email.value_masked;
                this.updateVerifiedBadge('email', userData.email.verified);

                // Phone
                const phoneInput = document.getElementById('phone');
                if (userData.phone) {
                    phoneInput.value = userData.phone.value_masked;
                    this.updateVerifiedBadge('phone', userData.phone.verified);
                } else {
                    phoneInput.value = '';
                    phoneInput.placeholder = i18n.notSet || 'Not set';
                }

                this.loading.style.display = 'none';
                this.form.style.display = 'block';

            } catch (error) {
                this.loading.style.display = 'none';
                this.error.style.display = 'block';
                console.error('Failed to load profile:', error);
            }
        }

        updateVerifiedBadge(type, verified) {
            const badge = document.getElementById(`${type}-verified-badge`);
            if (badge) {
                badge.className = verified ? 'wpsms-badge wpsms-badge-success' : 'wpsms-badge wpsms-badge-warning';
                badge.textContent = verified ? i18n.verified : i18n.unverified;
            }
        }

        attachEventListeners() {
            // Profile form submission
            if (this.form) {
                this.form.addEventListener('submit', (e) => this.handleProfileSubmit(e));
            }

            // Change email button
            const changeEmailBtn = document.getElementById('change-email-btn');
            if (changeEmailBtn) {
                changeEmailBtn.addEventListener('click', () => showModal('email-change-modal'));
            }

            // Change phone button
            const changePhoneBtn = document.getElementById('change-phone-btn');
            if (changePhoneBtn) {
                changePhoneBtn.addEventListener('click', () => showModal('phone-change-modal'));
            }

            // Modal close buttons
            document.querySelectorAll('.wpsms-modal-close, .wpsms-modal-overlay').forEach(el => {
                el.addEventListener('click', (e) => {
                    const modal = e.target.closest('.wpsms-modal');
                    if (modal) modal.style.display = 'none';
                });
            });

            // Email change flow
            new EmailChangeManager();
            new PhoneChangeManager();
        }

        async handleProfileSubmit(e) {
            e.preventDefault();

            const submitBtn = this.form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = i18n.loading || 'Saving...';

            try {
                const formData = new FormData(this.form);
                const data = {
                    first_name: formData.get('first_name'),
                    last_name: formData.get('last_name'),
                    display_name: formData.get('display_name'),
                };

                await apiRequest('account/me', {
                    method: 'POST',
                    body: JSON.stringify(data)
                });

                showToast(i18n.success || 'Profile updated successfully', 'success');

            } catch (error) {
                showToast(error.message, 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = i18n.save || 'Save Changes';
            }
        }
    }

    // ==================== Email Change Manager ====================

    class EmailChangeManager {
        constructor() {
            this.addForm = document.getElementById('email-change-form');
            this.verifyForm = document.getElementById('email-verify-form');
            this.flowId = null;

            this.init();
        }

        init() {
            if (this.addForm) {
                this.addForm.addEventListener('submit', (e) => this.handleSendCode(e));
            }
            if (this.verifyForm) {
                this.verifyForm.addEventListener('submit', (e) => this.handleVerify(e));
            }
        }

        async handleSendCode(e) {
            e.preventDefault();

            const submitBtn = this.addForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;

            try {
                const email = document.getElementById('new_email').value;

                const response = await apiRequest('account/email', {
                    method: 'POST',
                    body: JSON.stringify({ email })
                });

                this.flowId = response.data.flow_id;

                // Show verify form
                this.addForm.style.display = 'none';
                this.verifyForm.style.display = 'block';
                document.getElementById('email-verify-message').textContent = 
                    `${i18n.codeSent || 'Code sent to'} ${response.data.email_masked}`;

                document.getElementById('email_code').focus();

            } catch (error) {
                showToast(error.message, 'error');
                submitBtn.disabled = false;
            }
        }

        async handleVerify(e) {
            e.preventDefault();

            const submitBtn = this.verifyForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;

            try {
                const code = document.getElementById('email_code').value;

                await apiRequest('account/email/verify', {
                    method: 'POST',
                    body: JSON.stringify({
                        flow_id: this.flowId,
                        code
                    })
                });

                showToast('Email updated successfully', 'success');
                hideModal('email-change-modal');

                // Reload profile
                window.location.reload();

            } catch (error) {
                showToast(error.message, 'error');
                submitBtn.disabled = false;
            }
        }
    }

    // ==================== Phone Change Manager ====================

    class PhoneChangeManager {
        constructor() {
            this.addForm = document.getElementById('phone-change-form');
            this.verifyForm = document.getElementById('phone-verify-form');
            this.flowId = null;

            this.init();
        }

        init() {
            if (this.addForm) {
                this.addForm.addEventListener('submit', (e) => this.handleSendCode(e));
            }
            if (this.verifyForm) {
                this.verifyForm.addEventListener('submit', (e) => this.handleVerify(e));
            }
        }

        async handleSendCode(e) {
            e.preventDefault();

            const submitBtn = this.addForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;

            try {
                const phone = document.getElementById('new_phone').value;

                const response = await apiRequest('account/phone', {
                    method: 'POST',
                    body: JSON.stringify({ phone })
                });

                this.flowId = response.data.flow_id;

                // Show verify form
                this.addForm.style.display = 'none';
                this.verifyForm.style.display = 'block';
                document.getElementById('phone-verify-message').textContent = 
                    `${i18n.codeSent || 'Code sent to'} ${response.data.phone_masked}`;

                document.getElementById('phone_code').focus();

            } catch (error) {
                showToast(error.message, 'error');
                submitBtn.disabled = false;
            }
        }

        async handleVerify(e) {
            e.preventDefault();

            const submitBtn = this.verifyForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;

            try {
                const code = document.getElementById('phone_code').value;

                await apiRequest('account/phone/verify', {
                    method: 'POST',
                    body: JSON.stringify({
                        flow_id: this.flowId,
                        code
                    })
                });

                showToast('Phone updated successfully', 'success');
                hideModal('phone-change-modal');

                // Reload profile
                window.location.reload();

            } catch (error) {
                showToast(error.message, 'error');
                submitBtn.disabled = false;
            }
        }
    }

    // ==================== MFA Management ====================

    class MfaManager {
        constructor() {
            this.loading = document.getElementById('mfa-loading');
            this.content = document.getElementById('mfa-content');
            this.error = document.getElementById('mfa-error');
            this.factorsContainer = document.getElementById('factors-container');
            
            this.init();
        }

        async init() {
            await this.loadFactors();
            this.attachEventListeners();
        }

        async loadFactors() {
            try {
                this.loading.style.display = 'block';
                this.error.style.display = 'none';

                const response = await apiRequest('mfa/factors');
                const { factors, allowed_types, total_enrolled } = response.data;

                this.renderFactors(factors);
                this.updateStatusBanner(total_enrolled);
                this.updateAvailableFactors(factors, allowed_types);

                this.loading.style.display = 'none';
                this.content.style.display = 'block';

            } catch (error) {
                this.loading.style.display = 'none';
                this.error.style.display = 'block';
                console.error('Failed to load MFA factors:', error);
            }
        }

        renderFactors(factors) {
            if (factors.length === 0) {
                this.factorsContainer.innerHTML = `
                    <div class="wpsms-empty-state">
                        <p>${i18n.noFactors || 'No MFA factors enrolled yet'}</p>
                    </div>
                `;
                return;
            }

            this.factorsContainer.innerHTML = factors.map(factor => `
                <div class="wpsms-factor-card" data-factor-id="${factor.id}">
                    <div class="wpsms-factor-info">
                        <div class="wpsms-factor-icon">${this.getFactorIcon(factor.type)}</div>
                        <div>
                            <h4>${factor.label}</h4>
                            <p class="wpsms-factor-meta">
                                ${factor.verified ? '<span class="wpsms-badge wpsms-badge-success">' + i18n.verified + '</span>' : ''}
                                ${factor.last_used_at ? `Last used: ${new Date(factor.last_used_at).toLocaleDateString()}` : ''}
                            </p>
                        </div>
                    </div>
                    <button 
                        type="button" 
                        class="wpsms-btn wpsms-btn-danger wpsms-btn-sm" 
                        data-action="remove" 
                        data-type="${factor.type}" 
                        data-id="${factor.id}">
                        ${i18n.remove || 'Remove'}
                    </button>
                </div>
            `).join('');
        }

        getFactorIcon(type) {
            const icons = {
                email: '📧',
                phone: '📱',
                totp: '🔐',
                webauthn: '👆',
                backup: '🔑'
            };
            return icons[type] || '🔒';
        }

        updateStatusBanner(totalEnrolled) {
            const banner = document.getElementById('mfa-status-banner');
            const title = document.getElementById('mfa-status-title');
            const description = document.getElementById('mfa-status-description');

            if (totalEnrolled === 0) {
                banner.className = 'wpsms-status-banner wpsms-status-warning';
                title.textContent = i18n.noProtection || 'No MFA Protection';
                description.textContent = i18n.addFactor || 'Add at least one factor to secure your account';
            } else if (totalEnrolled === 1) {
                banner.className = 'wpsms-status-banner wpsms-status-info';
                title.textContent = i18n.basicProtection || 'Basic Protection';
                description.textContent = i18n.oneFactorEnrolled || '1 factor enrolled';
            } else {
                banner.className = 'wpsms-status-banner wpsms-status-success';
                title.textContent = i18n.strongProtection || 'Strong Protection';
                description.textContent = `${totalEnrolled} ${i18n.factorsEnrolled || 'factors enrolled'}`;
            }
        }

        updateAvailableFactors(factors, allowedTypes) {
            // Hide cards for already enrolled types
            const enrolledTypes = factors.map(f => f.type);

            if (enrolledTypes.includes('email')) {
                document.getElementById('add-email-mfa-card').style.display = 'none';
            }
            if (enrolledTypes.includes('phone')) {
                document.getElementById('add-phone-mfa-card').style.display = 'none';
            }

            // Hide cards for disabled types
            if (!allowedTypes.includes('email')) {
                document.getElementById('add-email-mfa-card').style.display = 'none';
            }
            if (!allowedTypes.includes('phone')) {
                document.getElementById('add-phone-mfa-card').style.display = 'none';
            }
        }

        attachEventListeners() {
            // Add email MFA
            const addEmailBtn = document.getElementById('add-email-mfa-btn');
            if (addEmailBtn) {
                addEmailBtn.addEventListener('click', () => showModal('email-mfa-modal'));
            }

            // Add phone MFA
            const addPhoneBtn = document.getElementById('add-phone-mfa-btn');
            if (addPhoneBtn) {
                addPhoneBtn.addEventListener('click', () => showModal('phone-mfa-modal'));
            }

            // Remove factor buttons (event delegation)
            this.factorsContainer.addEventListener('click', async (e) => {
                if (e.target.dataset.action === 'remove') {
                    await this.handleRemoveFactor(e.target);
                }
            });

            // MFA enrollment flows
            new EmailMfaManager(this);
            new PhoneMfaManager(this);
        }

        async handleRemoveFactor(btn) {
            if (!confirm(i18n.confirmRemove || 'Remove this factor?')) {
                return;
            }

            const type = btn.dataset.type;
            const id = btn.dataset.id;

            btn.disabled = true;

            try {
                await apiRequest(`mfa/${type}/${id}`, { method: 'DELETE' });
                
                showToast(`${type.toUpperCase()} MFA removed successfully`, 'success');
                await this.loadFactors();

            } catch (error) {
                showToast(error.message, 'error');
                btn.disabled = false;
            }
        }
    }

    // ==================== Email MFA Enrollment ====================

    class EmailMfaManager {
        constructor(mfaManager) {
            this.mfaManager = mfaManager;
            this.addForm = document.getElementById('email-mfa-add-form');
            this.verifyForm = document.getElementById('email-mfa-verify-form');
            this.flowId = null;

            this.init();
        }

        init() {
            if (this.addForm) {
                this.addForm.addEventListener('submit', (e) => this.handleSendCode(e));
            }
            if (this.verifyForm) {
                this.verifyForm.addEventListener('submit', (e) => this.handleVerify(e));
            }
        }

        async handleSendCode(e) {
            e.preventDefault();

            const submitBtn = this.addForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;

            try {
                const email = document.getElementById('mfa_email').value;

                const response = await apiRequest('mfa/email/add', {
                    method: 'POST',
                    body: JSON.stringify({ email })
                });

                this.flowId = response.data.flow_id;

                // Show verify form
                this.addForm.style.display = 'none';
                this.verifyForm.style.display = 'block';
                document.getElementById('email-mfa-verify-message').textContent = 
                    `${i18n.codeSent || 'Code sent to'} ${response.data.email_masked}`;

                document.getElementById('email_mfa_code').focus();

            } catch (error) {
                showToast(error.message, 'error');
                submitBtn.disabled = false;
            }
        }

        async handleVerify(e) {
            e.preventDefault();

            const submitBtn = this.verifyForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;

            try {
                const code = document.getElementById('email_mfa_code').value;

                await apiRequest('mfa/email/verify', {
                    method: 'POST',
                    body: JSON.stringify({
                        flow_id: this.flowId,
                        code
                    })
                });

                showToast('Email MFA enrolled successfully', 'success');
                hideModal('email-mfa-modal');

                // Reset forms
                this.addForm.reset();
                this.verifyForm.reset();
                this.addForm.style.display = 'block';
                this.verifyForm.style.display = 'none';

                // Reload MFA list
                await this.mfaManager.loadFactors();

            } catch (error) {
                showToast(error.message, 'error');
                submitBtn.disabled = false;
            }
        }
    }

    // ==================== Phone MFA Enrollment ====================

    class PhoneMfaManager {
        constructor(mfaManager) {
            this.mfaManager = mfaManager;
            this.addForm = document.getElementById('phone-mfa-add-form');
            this.verifyForm = document.getElementById('phone-mfa-verify-form');
            this.flowId = null;

            this.init();
        }

        init() {
            if (this.addForm) {
                this.addForm.addEventListener('submit', (e) => this.handleSendCode(e));
            }
            if (this.verifyForm) {
                this.verifyForm.addEventListener('submit', (e) => this.handleVerify(e));
            }
        }

        async handleSendCode(e) {
            e.preventDefault();

            const submitBtn = this.addForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;

            try {
                const phone = document.getElementById('mfa_phone').value;

                const response = await apiRequest('mfa/phone/add', {
                    method: 'POST',
                    body: JSON.stringify({ phone })
                });

                this.flowId = response.data.flow_id;

                // Show verify form
                this.addForm.style.display = 'none';
                this.verifyForm.style.display = 'block';
                document.getElementById('phone-mfa-verify-message').textContent = 
                    `${i18n.codeSent || 'Code sent to'} ${response.data.phone_masked}`;

                document.getElementById('phone_mfa_code').focus();

            } catch (error) {
                showToast(error.message, 'error');
                submitBtn.disabled = false;
            }
        }

        async handleVerify(e) {
            e.preventDefault();

            const submitBtn = this.verifyForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;

            try {
                const code = document.getElementById('phone_mfa_code').value;

                await apiRequest('mfa/phone/verify', {
                    method: 'POST',
                    body: JSON.stringify({
                        flow_id: this.flowId,
                        code
                    })
                });

                showToast('Phone MFA enrolled successfully', 'success');
                hideModal('phone-mfa-modal');

                // Reset forms
                this.addForm.reset();
                this.verifyForm.reset();
                this.addForm.style.display = 'block';
                this.verifyForm.style.display = 'none';

                // Reload MFA list
                await this.mfaManager.loadFactors();

            } catch (error) {
                showToast(error.message, 'error');
                submitBtn.disabled = false;
            }
        }
    }

    // ==================== Tab Management ====================

    function initTabs() {
        const tabs = document.querySelectorAll('.wpsms-tab-button');
        const panels = document.querySelectorAll('.wpsms-tab-panel');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const targetTab = tab.dataset.tab;

                // Update tab buttons
                tabs.forEach(t => {
                    t.classList.toggle('wpsms-tab-active', t.dataset.tab === targetTab);
                    t.setAttribute('aria-selected', t.dataset.tab === targetTab);
                });

                // Update panels
                panels.forEach(panel => {
                    panel.classList.toggle('wpsms-tab-panel-active', panel.dataset.tab === targetTab);
                });
            });
        });

        // Activate default tab
        const container = document.querySelector('.wpsms-account-container');
        if (container) {
            const defaultTab = container.dataset.defaultTab || 'profile';
            const defaultBtn = document.querySelector(`[data-tab="${defaultTab}"]`);
            if (defaultBtn) defaultBtn.click();
        }
    }

    // ==================== Initialize ====================

    function init() {
        // Check if we're on an account page
        if (!document.querySelector('.wpsms-account-container')) {
            return;
        }

        // Initialize tabs
        initTabs();

        // Initialize profile manager
        new ProfileManager();

        // Initialize MFA manager if on security tab
        if (document.getElementById('mfa-content')) {
            new MfaManager();
        }
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();

