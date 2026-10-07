import { subscribe, unsubscribe } from '@/routes/push';

/**
 * The installable app and its push notifications (SPEC §11): registers
 * the service worker, keeps the browser's install prompt for an "Install"
 * button, and subscribes this device to Web Push.
 */

type InstallPrompt = Event & { prompt: () => Promise<void> };

let installPrompt: InstallPrompt | null = null;
const listeners = new Set<() => void>();

export function initialisePwa(): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        installPrompt = event as InstallPrompt;
        listeners.forEach((l) => l());
    });

    if ('serviceWorker' in navigator) {
        void navigator.serviceWorker.register('/sw.js');
    }
}

/** True when the browser offered to install the app and it isn't installed yet. */
export const canInstall = () => installPrompt !== null;

export function onInstallAvailable(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

export async function install(): Promise<void> {
    await installPrompt?.prompt();
    installPrompt = null;
}

export const isStandalone = () =>
    window.matchMedia('(display-mode: standalone)').matches ||
    (navigator as Navigator & { standalone?: boolean }).standalone === true;

/** iPhones and iPads only allow push for apps added to the home screen. */
export const isIos = () =>
    /iPad|iPhone|iPod/.test(navigator.userAgent) ||
    (navigator.userAgent.includes('Mac') && navigator.maxTouchPoints > 1);

export const pushSupported = () =>
    'serviceWorker' in navigator &&
    'PushManager' in window &&
    'Notification' in window;

export async function currentSubscription(): Promise<PushSubscription | null> {
    if (!pushSupported()) {
        return null;
    }

    const registration = await navigator.serviceWorker.ready;

    return registration.pushManager.getSubscription();
}

export async function enablePush(vapidPublicKey: string): Promise<void> {
    const permission = await Notification.requestPermission();

    if (permission !== 'granted') {
        throw new Error('permission');
    }

    const registration = await navigator.serviceWorker.ready;
    const subscription =
        (await registration.pushManager.getSubscription()) ??
        (await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: base64UrlToBytes(vapidPublicKey),
        }));

    await send(subscribe.url(), 'POST', {
        ...subscription.toJSON(),
        content_encoding: (
            PushManager as unknown as { supportedContentEncodings?: string[] }
        ).supportedContentEncodings?.includes('aes128gcm')
            ? 'aes128gcm'
            : 'aesgcm',
    });
}

export async function disablePush(): Promise<void> {
    const subscription = await currentSubscription();

    if (subscription) {
        await send(unsubscribe.url(), 'DELETE', {
            endpoint: subscription.endpoint,
        });
        await subscription.unsubscribe();
    }
}

async function send(url: string, method: string, body: unknown) {
    const token = decodeURIComponent(
        document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] ?? '',
    );
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': token,
        },
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }
}

function base64UrlToBytes(base64Url: string): Uint8Array<ArrayBuffer> {
    const base64 = (base64Url + '='.repeat((4 - (base64Url.length % 4)) % 4))
        .replace(/-/g, '+')
        .replace(/_/g, '/');
    const raw = atob(base64);
    const bytes = new Uint8Array(new ArrayBuffer(raw.length));

    for (let i = 0; i < raw.length; i++) {
        bytes[i] = raw.charCodeAt(i);
    }

    return bytes;
}
