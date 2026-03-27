export function getInitials(user) {
    if (!user) return '?';
    if (user.first_name) {
        return (user.first_name[0] + (user.last_name?.[0] || '')).toUpperCase();
    }
    if (user.display_name) {
        const parts = user.display_name.trim().split(/\s+/);
        return (parts[0][0] + (parts[1]?.[0] || '')).toUpperCase();
    }
    return (user.username?.[0] || '?').toUpperCase();
}

export function maskEmail(email) {
    if (!email) return '';
    const [local, domain] = email.split('@');
    if (!domain) return email;
    return local[0] + '***@' + domain;
}

export function maskPhone(phone) {
    if (!phone) return '';
    const digits = phone.replace(/\D/g, '');
    if (digits.length < 4) return phone;
    return '+' + digits.slice(0, -4).replace(/\d/g, '*') + digits.slice(-4);
}
