import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import CountController from '@/actions/App/Http/Controllers/CountController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PinMap } from '@/components/viability/pin-map';
import type { Pin } from '@/components/viability/pin-map';
import { humanize } from '@/lib/format';
import { index } from '@/routes/counts';

type DayPartOption = { value: string; start_hour: number; end_hour: number };

type Count = {
    id: number;
    lat: number;
    lng: number;
    day_part: string;
    counted_on: string;
    minutes: number;
    count: number;
    note: string | null;
};

type Props = {
    counts: Count[];
    day_parts: DayPartOption[];
    map: {
        tile_url: string;
        attribution: string;
        max_zoom: number;
        centre: [number, number];
    };
};

const LENGTH_SECONDS = 10 * 60;

/** The day part the clock is in now (night runs past midnight, as 24–27). */
function currentDayPart(parts: DayPartOption[]): string {
    const now = new Date();
    const hour = now.getHours() + now.getMinutes() / 60;
    const clock = hour < 4 ? hour + 24 : hour;

    return (
        parts.find((p) => clock >= p.start_hour && clock < p.end_hour)?.value ??
        parts[0].value
    );
}

function mmss(seconds: number): string {
    const s = Math.max(0, Math.round(seconds));

    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}

/**
 * Counting pedestrians for the footfall calibration (SPEC §8): stand on a
 * shopping street, start the timer, tap once per person walking past.
 */
export default function Counts({ counts, day_parts, map }: Props) {
    const errors = usePage().props.errors as Record<string, string>;
    const [pin, setPin] = useState<Pin>(null);
    const [locating, setLocating] = useState(false);
    const [dayPart, setDayPart] = useState(() => currentDayPart(day_parts));
    const [count, setCount] = useState(0);
    const [startedAt, setStartedAt] = useState<number | null>(null);
    const [elapsed, setElapsed] = useState(0);
    const [stopped, setStopped] = useState(false);
    const [note, setNote] = useState('');
    const [saving, setSaving] = useState(false);
    const timer = useRef<number | null>(null);
    // Keep the phone's screen on while counting, where the browser allows.
    const wakeLock = useRef<{ release: () => Promise<void> } | null>(null);

    const running = startedAt !== null && !stopped;

    useEffect(() => {
        if (!running) {
            return;
        }

        timer.current = window.setInterval(() => {
            const seconds = (Date.now() - startedAt!) / 1000;
            setElapsed(seconds);

            if (seconds >= LENGTH_SECONDS) {
                stop();
            }
        }, 250);

        return () => window.clearInterval(timer.current ?? undefined);
    }, [running, startedAt]);

    const locate = () => {
        setLocating(true);
        navigator.geolocation?.getCurrentPosition(
            (p) => {
                setPin({ lat: p.coords.latitude, lng: p.coords.longitude });
                setLocating(false);
            },
            () => setLocating(false),
            { enableHighAccuracy: true, timeout: 10_000 },
        );
    };

    const stop = () => {
        if (startedAt !== null) {
            setElapsed((Date.now() - startedAt) / 1000);
        }

        setStopped(true);
        wakeLock.current?.release().catch(() => {});
        wakeLock.current = null;
    };

    const start = () => {
        (
            navigator as Navigator & {
                wakeLock?: {
                    request: (
                        type: 'screen',
                    ) => Promise<{ release: () => Promise<void> }>;
                };
            }
        ).wakeLock
            ?.request('screen')
            .then((lock) => (wakeLock.current = lock))
            .catch(() => {});
        setCount(0);
        setElapsed(0);
        setStopped(false);
        setStartedAt(Date.now());
        setDayPart(currentDayPart(day_parts));
    };

    const reset = () => {
        wakeLock.current?.release().catch(() => {});
        wakeLock.current = null;
        setStartedAt(null);
        setStopped(false);
        setElapsed(0);
        setCount(0);
        setNote('');
    };

    const save = () => {
        if (!pin) {
            return;
        }

        setSaving(true);
        router.post(
            CountController.store.url(),
            {
                lat: pin.lat,
                lng: pin.lng,
                day_part: dayPart,
                minutes: Math.max(
                    1,
                    Math.round(Math.min(elapsed, LENGTH_SECONDS) / 60),
                ),
                count,
                note: note || null,
            },
            {
                preserveScroll: true,
                onSuccess: reset,
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <>
            <Head title="Pedestrian counts" />
            <div className="mx-auto flex w-full max-w-xl flex-col gap-6 p-4">
                <Heading
                    title="Pedestrian counts"
                    description="Stand on a shopping street, start the timer and tap once for every person who walks past, for 10 minutes. 15–20 different spots, two or three times of day, calibrate the footfall map. Only the spot, time and count are kept."
                />

                <section className="space-y-2">
                    <div className="flex items-center justify-between gap-2">
                        <Label>1. Where you're standing</Label>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={locate}
                            disabled={locating}
                        >
                            {locating ? 'Locating…' : 'Use my location'}
                        </Button>
                    </div>
                    <PinMap
                        tileUrl={map.tile_url}
                        attribution={map.attribution}
                        maxZoom={map.max_zoom}
                        centre={map.centre}
                        pin={pin}
                        onPin={setPin}
                        zoom={pin ? 17 : 13}
                    />
                    <p className="text-xs text-muted-foreground">
                        {pin
                            ? `${pin.lat.toFixed(5)}, ${pin.lng.toFixed(5)}. Tap the map to move it.`
                            : 'Tap the map, or use your location.'}
                    </p>
                    <InputError message={errors.lat ?? errors.lng} />
                </section>

                <section className="space-y-3">
                    <Label htmlFor="day_part">2. Time of day</Label>
                    <select
                        id="day_part"
                        value={dayPart}
                        onChange={(e) => setDayPart(e.target.value)}
                        className="h-9 w-full rounded-md border bg-background px-3 text-sm"
                    >
                        {day_parts.map((p) => (
                            <option key={p.value} value={p.value}>
                                {humanize(p.value)} ({p.start_hour % 24}:00–
                                {p.end_hour % 24}:00)
                            </option>
                        ))}
                    </select>
                </section>

                <section className="space-y-3">
                    <Label>3. Count</Label>
                    <div className="flex items-baseline justify-between">
                        <span className="text-4xl font-semibold tabular-nums">
                            {count}
                        </span>
                        <span className="text-lg text-muted-foreground tabular-nums">
                            {startedAt === null
                                ? mmss(LENGTH_SECONDS)
                                : `${mmss(LENGTH_SECONDS - elapsed)} left`}
                        </span>
                    </div>

                    {startedAt === null ? (
                        <Button
                            type="button"
                            className="h-16 w-full text-lg"
                            onClick={start}
                        >
                            Start 10 minutes
                        </Button>
                    ) : (
                        <>
                            <button
                                type="button"
                                onClick={() => setCount((c) => c + 1)}
                                disabled={!running}
                                className="h-48 w-full touch-manipulation rounded-xl bg-primary text-3xl font-semibold text-primary-foreground select-none active:opacity-80 disabled:opacity-40"
                            >
                                {running ? '+1 person' : "Time's up"}
                            </button>
                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        setCount((c) => Math.max(0, c - 1))
                                    }
                                >
                                    −1
                                </Button>
                                {running && (
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={stop}
                                    >
                                        Stop early
                                    </Button>
                                )}
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={reset}
                                >
                                    Discard
                                </Button>
                            </div>
                        </>
                    )}
                </section>

                {stopped && (
                    <section className="space-y-3 rounded-xl border p-4">
                        <p className="text-sm">
                            {count} people in{' '}
                            {Math.max(
                                1,
                                Math.round(
                                    Math.min(elapsed, LENGTH_SECONDS) / 60,
                                ),
                            )}{' '}
                            min, {humanize(dayPart).toLowerCase()}.
                        </p>
                        <Input
                            value={note}
                            onChange={(e) => setNote(e.target.value)}
                            maxLength={80}
                            placeholder="Note (optional): rain, market day…"
                            aria-label="Note"
                        />
                        <Button
                            type="button"
                            className="w-full"
                            onClick={save}
                            disabled={!pin || saving}
                        >
                            {pin ? 'Save this count' : 'Pin the spot first'}
                        </Button>
                        <InputError
                            message={
                                errors.count ??
                                errors.minutes ??
                                errors.day_part
                            }
                        />
                    </section>
                )}

                <section className="space-y-2">
                    <h2 className="text-sm font-medium">
                        Your counts ({counts.length})
                    </h2>
                    {counts.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            None yet.
                        </p>
                    ) : (
                        <ul className="divide-y text-sm">
                            {counts.map((c) => (
                                <li
                                    key={c.id}
                                    className="flex items-center justify-between gap-2 py-2"
                                >
                                    <span>
                                        {c.counted_on} · {humanize(c.day_part)}{' '}
                                        · {c.count} in {c.minutes} min (
                                        {(c.count / c.minutes).toFixed(1)}/min)
                                        {c.note && (
                                            <span className="block text-xs text-muted-foreground">
                                                {c.note}
                                            </span>
                                        )}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            router.delete(
                                                CountController.destroy.url(
                                                    c.id,
                                                ),
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Delete
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

Counts.layout = {
    breadcrumbs: [{ title: 'Pedestrian counts', href: index() }],
};
