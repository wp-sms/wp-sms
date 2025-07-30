export const pickFormDirtyValues = (
    dirtyFields: Record<string, any>,
    formValues: Record<string, any>
): Record<string, any> => {
    return Object.fromEntries(
        Object.entries(dirtyFields)
            .filter(([key, value]) => {
                if (typeof value === 'boolean') {
                    return value;
                }
                if (Array.isArray(value)) {
                    return value.some((item) =>
                        typeof item === 'boolean' ? item : Object.values(item).some((v) => v === true)
                    );
                }
                if (typeof value === 'object' && value !== null) {
                    return Object.values(value).some((v) => v === true);
                }
                return false;
            })
            .map(([key, value]) => {
                if (typeof value === 'boolean') {
                    return [key, formValues[key]];
                }
                if (Array.isArray(value)) {
                    const hasDirtyItems = value.some((item) =>
                        typeof item === 'boolean' ? item : Object.values(item).some((v) => v === true)
                    );
                    return [key, hasDirtyItems ? formValues[key] : undefined];
                }
                if (typeof value === 'object' && value !== null) {
                    const nestedDirty = pickFormDirtyValues(value, formValues[key] || {});
                    const hasDirtyNested = Object.keys(nestedDirty).length > 0;
                    return [key, hasDirtyNested ? formValues[key] : undefined];
                }
                return [key, undefined];
            })
            .filter(([key, value]) => value !== undefined)
    );
};
