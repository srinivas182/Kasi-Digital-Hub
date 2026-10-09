/** POST JSON or form data to a Laravel route, with the XSRF cookie Laravel expects. */
export async function postJson<T>(url: string, body: Record<string, unknown> | FormData = {}): Promise<T> {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': match?.[1] ? decodeURIComponent(match[1]) : '',
    };
    if (!(body instanceof FormData)) headers['Content-Type'] = 'application/json';
    const response = await fetch(url, {
        method: 'POST',
        headers,
        body: body instanceof FormData ? body : JSON.stringify(body),
    });
    return (await response.json()) as T;
}
