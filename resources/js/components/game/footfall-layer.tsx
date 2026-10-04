import L from 'leaflet';
import { useEffect, useMemo, useState } from 'react';
import { CircleMarker, useMap, useMapEvents } from 'react-leaflet';
import { footfall as footfallRoute } from '@/routes/map';

/** Columns in each row of the endpoint's response, after lat and lng. */
export const FOOTFALL_VIEWS = [
    { key: 'overall', label: 'All day' },
    { key: 'morning', label: 'Morning' },
    { key: 'lunch', label: 'Lunch' },
    { key: 'afternoon', label: 'Afternoon' },
    { key: 'evening', label: 'Evening' },
    { key: 'night', label: 'Night' },
] as const;

export type FootfallView = (typeof FOOTFALL_VIEWS)[number]['key'];

/** Five equal bins over the 0–10 footfall scale. */
export const FOOTFALL_BINS = ['0–2', '2–4', '4–6', '6–8', '8–10'];

type Row = [number, number, number, number, number, number, number, number];

// Loaded once per page, shared by every map on it.
let cache: Promise<Row[]> | null = null;

function loadPoints(): Promise<Row[]> {
    cache ??= fetch(footfallRoute.url(), {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    })
        .then((r) => {
            if (!r.ok) {
                throw new Error(`Footfall: HTTP ${r.status}`);
            }

            return r.json();
        })
        .then((body: { points: Row[] }) => body.points)
        .catch((e) => {
            cache = null;
            throw e;
        });

    return cache;
}

/**
 * The sequential ramp from the CSS tokens. The canvas renderer can't use
 * CSS classes, so the colours are read once from the stylesheet.
 */
function ramp(): string[] {
    const style = getComputedStyle(document.documentElement);

    return [1, 2, 3, 4, 5].map((i) =>
        style.getPropertyValue(`--viz-seq-${i}`).trim(),
    );
}

export const binOf = (value: number) =>
    Math.min(4, Math.max(0, Math.floor(value / 2)));

/**
 * Each period's stored footfall ranks the streets against each other at
 * that time, on the same 0–10 scale, so on its own every period would look
 * alike. The demand model multiplies by how busy the streets are then
 * (the day part's intensity), so the layer does the same: the busiest
 * period shows the stored values and quieter ones fade, in footfall units
 * (people ∝ (footfall / 10) ^ exponent × intensity).
 */
export function periodFactor(
    view: FootfallView,
    intensity: Record<string, number>,
    exponent: number,
): number {
    if (view === 'overall') {
        return 1;
    }

    const busiest = Math.max(...Object.values(intensity));

    return busiest > 0
        ? ((intensity[view] ?? 0) / busiest) ** (1 / exponent)
        : 1;
}

/**
 * The estimated footfall surface: a dot per commercial street point,
 * darker where more people pass. Drawn on a canvas so thousands of points
 * stay smooth; fetched only when the layer is first shown.
 */
export function FootfallLayer({
    view,
    factor,
    onStatus,
}: {
    view: FootfallView;
    /** Multiplies the period's values (see periodFactor). */
    factor: number;
    onStatus?: (status: 'loading' | 'ready' | 'error') => void;
}) {
    const [points, setPoints] = useState<Row[] | null>(null);
    const [zoom, setZoom] = useState(useMap().getZoom());
    const renderer = useMemo(() => L.canvas({ padding: 0.3 }), []);
    const colours = useMemo(ramp, []);

    useMapEvents({ zoomend: (e) => setZoom(e.target.getZoom()) });

    useEffect(() => {
        let live = true;
        onStatus?.('loading');
        loadPoints()
            .then((p) => {
                if (live) {
                    setPoints(p);
                    onStatus?.('ready');
                }
            })
            .catch(() => live && onStatus?.('error'));

        return () => {
            live = false;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    if (!points) {
        return null;
    }

    const column = 2 + FOOTFALL_VIEWS.findIndex((v) => v.key === view);
    const radius = zoom >= 16 ? 5 : zoom >= 14 ? 3.5 : 2.5;

    // Changing pathOptions or radius restyles the existing dots in place.
    return (
        <>
            {points.map((p, i) => {
                const colour = colours[binOf(p[column] * factor)];

                return (
                    <CircleMarker
                        key={i}
                        center={[p[0], p[1]]}
                        radius={radius}
                        renderer={renderer}
                        interactive={false}
                        pathOptions={{
                            stroke: false,
                            fillColor: colour,
                            fillOpacity: 0.85,
                        }}
                    />
                );
            })}
        </>
    );
}
