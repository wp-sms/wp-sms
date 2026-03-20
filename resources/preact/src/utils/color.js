/**
 * Hex color to OKLch conversion and palette generation.
 *
 * Converts admin-configured hex colors into OKLch CSS variable overrides
 * for the auth page theming system.
 */

/** Parse hex (#rrggbb) to linear RGB [0..1]. */
function hexToLinearRgb(hex) {
    const r = parseInt(hex.slice(1, 3), 16) / 255;
    const g = parseInt(hex.slice(3, 5), 16) / 255;
    const b = parseInt(hex.slice(5, 7), 16) / 255;

    // sRGB → linear
    const toLinear = (c) => (c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4));
    return [toLinear(r), toLinear(g), toLinear(b)];
}

/** Linear RGB → OKLab. */
function linearRgbToOklab(rgb) {
    const [r, g, b] = rgb;

    const l_ = 0.4122214708 * r + 0.5363325363 * g + 0.0514459929 * b;
    const m_ = 0.2119034982 * r + 0.6806995451 * g + 0.1073969566 * b;
    const s_ = 0.0883024619 * r + 0.2220049412 * g + 0.6736926970 * b;

    const l = Math.cbrt(l_);
    const m = Math.cbrt(m_);
    const s = Math.cbrt(s_);

    return [
        0.2104542553 * l + 0.7936177850 * m - 0.0040720468 * s,
        1.9779984951 * l - 2.4285922050 * m + 0.4505937099 * s,
        0.0259040371 * l + 0.7827717662 * m - 0.8086757660 * s,
    ];
}

/** OKLab → OKLch. */
function oklabToOklch(lab) {
    const [L, a, b] = lab;
    const C = Math.sqrt(a * a + b * b);
    let h = (Math.atan2(b, a) * 180) / Math.PI;
    if (h < 0) h += 360;
    return [L, C, h];
}

/** Convert hex to OKLch values [L, C, h]. */
export function hexToOklch(hex) {
    if (!hex || !hex.match(/^#[0-9a-fA-F]{6}$/)) return [0.5, 0, 0];
    const linear = hexToLinearRgb(hex);
    const lab = linearRgbToOklab(linear);
    return oklabToOklch(lab);
}

/** Format OKLch values as CSS `oklch(L C h)` string. */
function oklch(L, C, h) {
    return `oklch(${L.toFixed(3)} ${C.toFixed(3)} ${h.toFixed(3)})`;
}

/**
 * Resolve color_mode from config, supporting legacy dark_mode boolean.
 */
function resolveColorMode(config) {
    if (config.color_mode) return config.color_mode;
    if (config.dark_mode !== undefined) return config.dark_mode ? 'dark' : 'light';
    return 'light';
}

/**
 * Build a single-mode (light or dark) CSS variable map from colors.
 */
function buildModeVars(mode, { pL, pC, pH, bgL, bgC, bgH, acL, acC, acH, txL, txC, txH, erL, erC, erH }) {
    const vars = {};

    // Accent is mode-independent
    vars['--accent'] = oklch(acL, acC, acH);
    vars['--accent-foreground'] = acL > 0.6 ? oklch(0.15, acC * 0.1, acH) : oklch(0.95, 0.005, acH);

    if (mode === 'dark') {
        const darkTxL = txL < 0.5 ? (1 - txL) : txL;
        vars['--background'] = oklch(0.15, bgC * 0.3, bgH);
        vars['--foreground'] = oklch(darkTxL, txC, txH);
        vars['--card'] = oklch(0.20, bgC * 0.2, bgH);
        vars['--card-foreground'] = oklch(darkTxL, txC, txH);
        vars['--popover'] = oklch(0.20, bgC * 0.2, bgH);
        vars['--popover-foreground'] = oklch(0.95, 0.005, bgH);
        vars['--muted'] = oklch(0.25, bgC * 0.15, bgH);
        vars['--muted-foreground'] = oklch(0.65, 0.01, bgH);
        vars['--secondary'] = oklch(0.25, 0.005, bgH);
        vars['--secondary-foreground'] = oklch(0.90, 0.005, bgH);
        vars['--border'] = oklch(0.30, bgC * 0.1, bgH);
        vars['--input'] = oklch(0.30, bgC * 0.1, bgH);
        vars['--ring'] = oklch(0.50, 0.01, bgH);
        vars['--destructive'] = oklch(Math.min(erL + 0.1, 0.75), erC, erH);
        vars['--destructive-foreground'] = oklch(0.98, 0.005, erH);

        const boostedL = Math.min(pL + 0.15, 0.85);
        vars['--primary'] = oklch(boostedL, pC, pH);
        vars['--primary-foreground'] = boostedL > 0.6 ? oklch(0.15, pC * 0.1, pH) : oklch(0.98, 0.005, pH);
    } else {
        const lightBgL = bgL < 0.5 ? (1 - bgL) : bgL;
        const lightTxL = txL > 0.5 ? (1 - txL) : txL;
        vars['--background'] = oklch(lightBgL, bgC, bgH);
        vars['--foreground'] = oklch(lightTxL, txC, txH);
        vars['--card'] = oklch(1, 0, 0);
        vars['--card-foreground'] = oklch(lightTxL, txC, txH);
        vars['--popover'] = oklch(1, 0, 0);
        vars['--popover-foreground'] = oklch(0.147, 0.004, bgH);
        vars['--muted'] = oklch(Math.min(lightBgL + 0.02, 0.97), bgC * 0.5, bgH);
        vars['--muted-foreground'] = oklch(0.55, 0.013, bgH);
        vars['--secondary'] = oklch(0.967, 0.001, bgH);
        vars['--secondary-foreground'] = oklch(0.21, 0.006, bgH);
        vars['--border'] = oklch(0.923, bgC * 0.3, bgH);
        vars['--input'] = oklch(0.923, bgC * 0.3, bgH);
        vars['--ring'] = oklch(0.709, 0.01, bgH);
        vars['--destructive'] = oklch(erL, erC, erH);
        vars['--destructive-foreground'] = oklch(0.985, 0.001, erH);

        vars['--primary'] = oklch(pL, pC, pH);
        vars['--primary-foreground'] = pL > 0.6 ? oklch(0.15, pC * 0.1, pH) : oklch(0.987, 0.022, pH);
    }

    return vars;
}

/**
 * Generate full CSS variable palette from branding config.
 *
 * @param {object} config - Branding settings
 * @returns For light/dark: flat Record<string, string>.
 *          For auto: { light: Record, dark: Record, shared: Record }.
 */
export function generatePalette(config) {
    const {
        primary_color = '#171717',
        accent_color = '#6366f1',
        text_color = '#1c1917',
        error_color = '#dc2626',
        background_color = '#ffffff',
        font_family = 'system-ui',
        border_radius = 8,
    } = config || {};

    const colorMode = resolveColorMode(config || {});

    const [pL, pC, pH] = hexToOklch(primary_color);
    const [bgL, bgC, bgH] = hexToOklch(background_color);
    const [acL, acC, acH] = hexToOklch(accent_color);
    const [txL, txC, txH] = hexToOklch(text_color);
    const [erL, erC, erH] = hexToOklch(error_color);

    const colorParams = { pL, pC, pH, bgL, bgC, bgH, acL, acC, acH, txL, txC, txH, erL, erC, erH };

    // Shared (non-color) vars
    const shared = {};
    if (font_family && font_family !== 'system-ui') {
        shared['--font-sans'] = `"${font_family}", ui-sans-serif, system-ui, -apple-system, sans-serif`;
    }
    shared['--radius'] = `${border_radius / 16}rem`;

    if (colorMode === 'auto') {
        return {
            light: buildModeVars('light', colorParams),
            dark: buildModeVars('dark', colorParams),
            shared,
        };
    }

    return {
        ...buildModeVars(colorMode, colorParams),
        ...shared,
    };
}

/**
 * Check if a hex color is "light" (L > 0.6 in OKLch).
 */
export function isLightColor(hex) {
    const [L] = hexToOklch(hex);
    return L > 0.6;
}
