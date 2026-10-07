import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    canInstall,
    currentSubscription,
    disablePush,
    enablePush,
    install,
    isIos,
    isStandalone,
    onInstallAvailable,
    pushSupported,
} from '@/lib/pwa';
import { edit, update } from '@/routes/notifications';

type Props = {
    notify_daily_results: boolean;
    notify_events: boolean;
    /** Null until the server has VAPID keys (php artisan webpush:vapid). */
    vapid_public_key: string | null;
    devices: number;
    nightly_at: string;
};

type DeviceState =
    | 'checking'
    | 'on'
    | 'off'
    | 'blocked'
    | 'unsupported'
    | 'ios-home-screen'
    | 'server-off';

export default function Notifications(props: Props) {
    const [device, setDevice] = useState<DeviceState>('checking');
    const [busy, setBusy] = useState(false);
    const [installable, setInstallable] = useState(false);

    useEffect(() => {
        setInstallable(canInstall());

        return onInstallAvailable(() => setInstallable(canInstall()));
    }, []);

    useEffect(() => {
        if (!props.vapid_public_key) {
            setDevice('server-off');
        } else if (!pushSupported()) {
            setDevice(
                isIos() && !isStandalone() ? 'ios-home-screen' : 'unsupported',
            );
        } else if (Notification.permission === 'denied') {
            setDevice('blocked');
        } else {
            void currentSubscription().then((s) => setDevice(s ? 'on' : 'off'));
        }
    }, [props.vapid_public_key]);

    const savePreference = (
        field: 'notify_daily_results' | 'notify_events',
        value: boolean,
    ) =>
        router.patch(
            update.url(),
            {
                notify_daily_results: props.notify_daily_results,
                notify_events: props.notify_events,
                [field]: value,
            },
            { preserveScroll: true },
        );

    const toggleDevice = async () => {
        setBusy(true);

        try {
            if (device === 'on') {
                await disablePush();
                setDevice('off');
            } else {
                await enablePush(props.vapid_public_key!);
                setDevice('on');
            }

            router.reload({ only: ['devices'] });
        } catch {
            setDevice(Notification.permission === 'denied' ? 'blocked' : 'off');
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <Head title="Notifications" />
            <h1 className="sr-only">Notifications</h1>

            <div className="space-y-8">
                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title="What to tell you"
                        description={`Your café trades every day. At ${props.nightly_at} (Madrid time) each night you can get the day's results.`}
                    />
                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="daily"
                            checked={props.notify_daily_results}
                            onCheckedChange={(v) =>
                                savePreference(
                                    'notify_daily_results',
                                    v === true,
                                )
                            }
                        />
                        <Label htmlFor="daily" className="leading-snug">
                            The day's results: customers, takings, weather,
                            anything that happened, and the month's profit at
                            month end
                        </Label>
                    </div>
                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="events"
                            checked={props.notify_events}
                            onCheckedChange={(v) =>
                                savePreference('notify_events', v === true)
                            }
                        />
                        <Label htmlFor="events" className="leading-snug">
                            Events that need your decision (otherwise they take
                            their default after a few days)
                        </Label>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        If your café goes bankrupt you're always told.
                    </p>
                </section>

                <section className="space-y-3">
                    <Heading
                        variant="small"
                        title="This device"
                        description={
                            props.devices === 0
                                ? 'No device gets your notifications yet.'
                                : props.devices === 1
                                  ? 'Notifications are on for 1 device.'
                                  : `Notifications are on for ${props.devices} devices.`
                        }
                    />
                    <DeviceStatus state={device} />
                    {(device === 'on' || device === 'off') && (
                        <Button
                            onClick={toggleDevice}
                            disabled={busy}
                            variant={device === 'on' ? 'secondary' : 'default'}
                        >
                            {device === 'on'
                                ? 'Turn off on this device'
                                : 'Turn on for this device'}
                        </Button>
                    )}
                </section>

                {!isStandaloneSafe() && (
                    <section className="space-y-3">
                        <Heading
                            variant="small"
                            title="Install the app"
                            description="Add Traspaso to your home screen to open it like an app, straight to your café."
                        />
                        {installable ? (
                            <Button
                                variant="secondary"
                                onClick={() =>
                                    void install().then(() =>
                                        setInstallable(false),
                                    )
                                }
                            >
                                Install Traspaso
                            </Button>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                On iPhone or iPad: tap Share, then “Add to Home
                                Screen”. On Android: open the browser menu and
                                choose “Install app” or “Add to Home screen”.
                            </p>
                        )}
                    </section>
                )}
            </div>
        </>
    );
}

function DeviceStatus({ state }: { state: DeviceState }) {
    const text: Record<DeviceState, string> = {
        checking: 'Checking this device…',
        on: 'This device gets your notifications.',
        off: "This device doesn't get notifications yet.",
        blocked:
            'Notifications are blocked for this site. Allow them in your browser settings, then come back.',
        unsupported: "This browser can't receive push notifications.",
        'ios-home-screen':
            'On iPhone and iPad, notifications only work once Traspaso is on your home screen: tap Share, then “Add to Home Screen”, open it from there and come back to this page.',
        'server-off':
            "Notifications aren't set up on this server yet (php artisan webpush:vapid).",
    };

    return <p className="text-sm">{text[state]}</p>;
}

const isStandaloneSafe = () => typeof window !== 'undefined' && isStandalone();

Notifications.layout = {
    breadcrumbs: [{ title: 'Notifications', href: edit() }],
};
