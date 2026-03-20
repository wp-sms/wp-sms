/**
 * Shared SVG icons for the messaging button widget.
 *
 * Each icon accepts `size` (default varies per icon) and an optional
 * `class` prop for additional CSS styling.  All icons follow Lucide
 * conventions (stroke-based, 24x24 viewBox, 2px stroke).
 */

const svgBase = { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': '2', 'stroke-linecap': 'round', 'stroke-linejoin': 'round' };

export function MessageIcon({ size = 24, class: cls }) {
    return (
        <svg {...svgBase} width={size} height={size} class={cls}>
            <path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z" />
        </svg>
    );
}

export function CloseIcon({ size = 24, class: cls }) {
    return (
        <svg {...svgBase} width={size} height={size} class={cls}>
            <path d="M18 6 6 18" />
            <path d="m6 6 12 12" />
        </svg>
    );
}

export function ChevronRightIcon({ size = 16, class: cls }) {
    return (
        <svg {...svgBase} width={size} height={size} class={cls}>
            <path d="m9 18 6-6-6-6" />
        </svg>
    );
}

export function HomeIcon({ size = 18, class: cls }) {
    return (
        <svg {...svgBase} width={size} height={size} class={cls}>
            <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
            <polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
    );
}

export function UsersIcon({ size = 18, class: cls }) {
    return (
        <svg {...svgBase} width={size} height={size} class={cls}>
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
    );
}

export function HelpCircleIcon({ size = 18, class: cls }) {
    return (
        <svg {...svgBase} width={size} height={size} class={cls}>
            <circle cx="12" cy="12" r="10"/>
            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
            <path d="M12 17h.01"/>
        </svg>
    );
}

export function CheckCircleIcon({ size = 32, class: cls }) {
    return (
        <svg {...svgBase} width={size} height={size} class={cls}>
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
            <polyline points="22 4 12 14.01 9 11.01"/>
        </svg>
    );
}

export function FileTextIcon({ size = 18, class: cls }) {
    return (
        <svg {...svgBase} width={size} height={size} class={cls}>
            <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
            <polyline points="14 2 14 8 20 8"/>
        </svg>
    );
}

export function ExternalLinkIcon({ size = 16, class: cls }) {
    return (
        <svg {...svgBase} width={size} height={size} class={cls}>
            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
            <polyline points="15 3 21 3 21 9"/>
            <line x1="10" y1="14" x2="21" y2="3"/>
        </svg>
    );
}
