import { usePage } from '@inertiajs/react';

/**
 * Mirrors backend permissions for showing or hiding UI. The backend always enforces access.
 */
export function useCan() {
    const { auth } = usePage().props;
    const permissions = auth.permissions ?? [];

    return (permission: string): boolean => permissions.includes('*') || permissions.includes(permission);
}
