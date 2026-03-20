/**
 * Lightweight avatar with graceful fallback to initial letter.
 *
 * Used by both TeamPage and WelcomePage to display team member avatars.
 */
export function AvatarImage({ src, name }) {
    const initial = (name || '?')[0].toUpperCase();
    return (
        <img
            src={src}
            alt={name}
            onError={(e) => {
                const parent = e.target.parentNode;
                e.target.remove();
                parent.textContent = initial;
            }}
        />
    );
}
