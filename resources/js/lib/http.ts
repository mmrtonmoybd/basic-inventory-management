/**
 * Minimal JSON GET for lookup endpoints (session-authenticated, same origin).
 */
export async function getJson<T>(url: string, signal?: AbortSignal): Promise<T> {
    const response = await fetch(url, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        signal,
    });

    if (!response.ok) {
        throw new Error(`Request failed with ${response.status}`);
    }

    return (await response.json()) as T;
}
