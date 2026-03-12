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
