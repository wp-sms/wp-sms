(function () {
    'use strict';

    var config = window.wsmsUserProfile || {};
    var SVG_CHECK = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>';
    var SVG_X = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>';

    document.addEventListener('click', function (e) {
        // Phone edit toggle
        var toggle = e.target.closest('.wsms-phone-edit-toggle');
        if (toggle) {
            e.preventDefault();
            var td = toggle.closest('td');
            var editDiv = td.querySelector('.wsms-phone-edit');
            if (editDiv) {
                editDiv.style.display = editDiv.style.display === 'none' ? 'block' : 'none';
            }
            return;
        }

        // Phone cancel
        var cancel = e.target.closest('.wsms-phone-cancel');
        if (cancel) {
            e.preventDefault();
            cancel.closest('.wsms-phone-edit').style.display = 'none';
            return;
        }

        // Action buttons
        var btn = e.target.closest('.wsms-action');
        if (!btn) return;

        e.preventDefault();
        var action = btn.dataset.action;
        var userId = btn.dataset.userId;

        switch (action) {
            case 'verification':
                handleVerification(btn, userId, btn.dataset.channel, btn.dataset.verified === 'true');
                break;
            case 'reset-mfa':
                handleResetMfa(btn, userId, btn.dataset.channel);
                break;
            case 'disconnect-social':
                handleDisconnectSocial(btn, userId, btn.dataset.provider);
                break;
            case 'unlock':
                handleUnlock(btn, userId);
                break;
            case 'save-phone':
                handleSavePhone(btn, userId);
                break;
            case 'remove-phone':
                handleRemovePhone(btn, userId);
                break;
            case 'password-reset':
                handlePasswordReset(btn, userId);
                break;
            case 'activate':
                handleActivate(btn, userId);
                break;
            case 'send-verification':
                handleSendVerification(btn, userId, btn.dataset.channel);
                break;
            case 'suspend':
                handleSuspend(btn, userId);
                break;
            case 'unsuspend':
                handleUnsuspend(btn, userId);
                break;
        }
    });

    function handleVerification(btn, userId, channel, verified) {
        var label = verified ? 'verify' : 'unverify';
        if (!window.confirm('Are you sure you want to ' + label + ' this user\'s ' + channel + '?')) return;

        apiRequest(userId + '/verification', 'PUT', { channel: channel, verified: verified })
            .then(function () {
                var td = btn.closest('td');
                var pill = td.querySelector('.wsms-pill');
                if (verified) {
                    pill.className = 'wsms-pill wsms-pill--verified wsms-profile-badge';
                    pill.innerHTML = SVG_CHECK + ' Verified';
                    btn.textContent = 'Mark Unverified';
                    btn.dataset.verified = 'false';
                } else {
                    pill.className = 'wsms-pill wsms-pill--unverified wsms-profile-badge';
                    pill.innerHTML = SVG_X + ' Not Verified';
                    btn.textContent = 'Mark Verified';
                    btn.dataset.verified = 'true';
                }
            });
    }

    function handleResetMfa(btn, userId, channel) {
        if (!window.confirm('Are you sure you want to reset this MFA factor? The user will need to re-enroll.')) return;

        var row = btn.closest('.wsms-mfa-row');
        var td = row.parentElement;

        apiRequest(userId + '/mfa/' + channel, 'DELETE')
            .then(function () {
                row.remove();
                if (td && !td.querySelector('.wsms-mfa-row')) {
                    td.innerHTML = '<span style="color: #999;">None enrolled</span>';
                }
            });
    }

    function handleDisconnectSocial(btn, userId, provider) {
        if (!window.confirm('Are you sure you want to disconnect the ' + provider + ' account?')) return;

        var row = btn.closest('.wsms-social-row');
        var td = row.parentElement;

        apiRequest(userId + '/social/' + provider, 'DELETE')
            .then(function () {
                row.remove();
                if (td && !td.querySelector('.wsms-social-row')) {
                    td.innerHTML = '<span style="color: #999;">None linked</span>';
                }
            });
    }

    function handleUnlock(btn, userId) {
        if (!window.confirm('Are you sure you want to unlock this account and reset failed attempts?')) return;

        apiRequest(userId + '/lockout', 'DELETE')
            .then(function () {
                var td = btn.closest('td');
                td.innerHTML = '<span style="color: #999;">No lockout</span>';
            });
    }

    function handleSavePhone(btn, userId) {
        var editDiv = btn.closest('.wsms-phone-edit');
        var input = editDiv.querySelector('.wsms-phone-input');
        var phone = input.value.trim();

        if (!phone) {
            window.alert('Please enter a phone number.');
            return;
        }

        apiRequest(userId + '/phone', 'PUT', { phone: phone })
            .then(function () {
                window.location.reload();
            });
    }

    function handleRemovePhone(btn, userId) {
        if (!window.confirm('Are you sure you want to remove this phone number?')) return;

        apiRequest(userId + '/phone', 'PUT', { phone: '' })
            .then(function () {
                window.location.reload();
            });
    }

    function handlePasswordReset(btn, userId) {
        if (!window.confirm('Send a password reset email to this user?')) return;

        var originalText = btn.textContent;
        btn.disabled = true;

        apiRequest(userId + '/password-reset', 'POST')
            .then(function () {
                btn.textContent = 'Sent!';
                setTimeout(function () {
                    btn.textContent = originalText;
                    btn.disabled = false;
                }, 2000);
            })
            .catch(function () {
                btn.disabled = false;
            });
    }

    function handleActivate(btn, userId) {
        if (!window.confirm('Activate this user? They will be able to log in.')) return;

        apiRequest(userId + '/activate', 'POST')
            .then(function () {
                var row = btn.closest('.wsms-registration-row');
                if (row) {
                    row.remove();
                }
            });
    }

    function handleSendVerification(btn, userId, channel) {
        var originalText = btn.textContent;
        btn.disabled = true;

        apiRequest(userId + '/send-verification', 'POST', { channel: channel })
            .then(function () {
                btn.textContent = 'Sent!';
                setTimeout(function () {
                    btn.textContent = originalText;
                    btn.disabled = false;
                }, 2000);
            })
            .catch(function () {
                btn.disabled = false;
            });
    }

    function handleSuspend(btn, userId) {
        if (!window.confirm('Are you sure you want to suspend this user? They will be immediately logged out of all sessions.')) return;

        apiRequest(userId + '/suspend', 'POST')
            .then(function () {
                window.location.reload();
            });
    }

    function handleUnsuspend(btn, userId) {
        if (!window.confirm('Are you sure you want to unsuspend this user? They will be able to log in again.')) return;

        apiRequest(userId + '/suspension', 'DELETE')
            .then(function () {
                window.location.reload();
            });
    }

    function apiRequest(path, method, body) {
        var options = {
            method: method,
            headers: {
                'X-WP-Nonce': config.nonce,
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
        };

        if (body) {
            options.body = JSON.stringify(body);
        }

        return fetch(config.restUrl + path, options)
            .then(function (response) {
                if (!response.ok) {
                    return response.json()
                        .catch(function () {
                            throw new Error('Request failed (' + response.status + ')');
                        })
                        .then(function (data) {
                            throw new Error(data.message || 'Request failed');
                        });
                }
                return response.json();
            })
            .catch(function (err) {
                window.alert('Error: ' + err.message);
                throw err;
            });
    }
})();
