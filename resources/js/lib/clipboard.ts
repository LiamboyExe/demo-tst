/**
 * Copy text to the clipboard, falling back to the legacy execCommand path
 * when the async Clipboard API is unavailable (for example on plain HTTP
 * origins). Throws when the copy did not actually happen so callers never
 * report a false success.
 */
export async function copyTextToClipboard(text: string): Promise<void> {
    if (navigator.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(text);

            return;
        } catch {
            // Fall through to the legacy path below.
        }
    }

    const el = document.createElement('textarea');
    el.value = text;
    el.setAttribute('readonly', '');
    el.style.position = 'fixed';
    el.style.top = '0';
    el.style.left = '-9999px';
    el.style.opacity = '0';

    document.body.appendChild(el);

    try {
        el.focus();
        el.select();
        el.setSelectionRange(0, text.length);

        if (!document.execCommand('copy')) {
            throw new Error('Copy command was rejected by the browser.');
        }
    } finally {
        document.body.removeChild(el);
    }
}
