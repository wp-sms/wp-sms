import { FileTextIcon, ExternalLinkIcon } from '../icons';

export function ResourcesPage({ config }) {
    const links = config.links ?? [];

    if (links.length === 0) {
        return (
            <div class="wsms-mb-page wsms-mb-page--resources">
                <p class="wsms-mb-resources__empty">No resources available.</p>
            </div>
        );
    }

    return (
        <div class="wsms-mb-page wsms-mb-page--resources">
            <div class="wsms-mb-resources__list">
                {links.map((link, i) => (
                    <a
                        key={i}
                        href={link.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        class="wsms-mb-resource-link"
                    >
                        <div class="wsms-mb-resource-link__icon">
                            <FileTextIcon size={18} />
                        </div>
                        <div class="wsms-mb-resource-link__content">
                            <h4 class="wsms-mb-resource-link__title">{link.title || link.url}</h4>
                            {link.description && (
                                <p class="wsms-mb-resource-link__desc">{link.description}</p>
                            )}
                        </div>
                        <ExternalLinkIcon size={16} class="wsms-mb-resource-link__arrow" />
                    </a>
                ))}
            </div>
        </div>
    );
}
