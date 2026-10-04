import 'leaflet/dist/leaflet.css';
import { useState } from 'react';
import {
    Circle,
    CircleMarker,
    GeoJSON,
    LayerGroup,
    LayersControl,
    MapContainer,
    TileLayer,
    Tooltip,
    useMapEvents,
} from 'react-leaflet';
import {
    FOOTFALL_BINS,
    FOOTFALL_VIEWS,
    FootfallLayer,
    periodFactor,
} from '@/components/game/footfall-layer';
import type { FootfallView } from '@/components/game/footfall-layer';
import { formatCents, humanize } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { BusinessForSale, Competitor, MapProps } from '@/types/game';

type Located<T> = T & { lat: number; lng: number };

const located = <T extends { lat: number | null; lng: number | null }>(
    items: T[],
): Located<T>[] =>
    items.filter((i): i is Located<T> => i.lat !== null && i.lng !== null);

const FOOTFALL_LAYER = 'Footfall (estimated)';

/** Tells the parent when the player switches the footfall overlay. */
function OverlayWatcher({ onChange }: { onChange: (on: boolean) => void }) {
    useMapEvents({
        overlayadd: (e) => e.name === FOOTFALL_LAYER && onChange(true),
        overlayremove: (e) => e.name === FOOTFALL_LAYER && onChange(false),
    });

    return null;
}

type Props = {
    map: MapProps;
    forSale?: BusinessForSale[];
    selectedId?: number | null;
    onSelect?: (business: BusinessForSale) => void;
    own?: BusinessForSale;
    competitors?: Competitor[];
    className?: string;
};

/**
 * The city map: OpenStreetMap tiles (with their required attribution in the
 * corner), neighbourhood areas, points of interest, and the businesses.
 */
export function GameMap({
    map,
    forSale = [],
    selectedId,
    onSelect,
    own,
    competitors = [],
    className,
}: Props) {
    const [footfallOn, setFootfallOn] = useState(false);
    const [footfallView, setFootfallView] = useState<FootfallView>('overall');
    const [footfallStatus, setFootfallStatus] = useState<
        'loading' | 'ready' | 'error'
    >('loading');
    const centre: [number, number] =
        own?.lat != null && own.lng != null ? [own.lat, own.lng] : map.centre;
    const kinds = [
        forSale.length > 0 && {
            key: 'for-sale',
            label: 'For sale',
            size: 'size-3',
        },
        own && { key: 'own', label: 'Your café', size: 'size-4' },
        competitors.length > 0 && {
            key: 'rival',
            label: 'Rivals',
            size: 'size-3',
        },
    ].filter(Boolean) as { key: string; label: string; size: string }[];

    return (
        <figure className={cn('space-y-2', className)}>
            <div className="relative h-[28rem] overflow-hidden rounded-lg border">
                <MapContainer
                    center={centre}
                    zoom={own ? 15 : map.zoom}
                    maxZoom={map.max_zoom}
                    scrollWheelZoom
                    className="size-full"
                >
                    <TileLayer
                        url={map.tile_url}
                        attribution={map.attribution}
                        maxZoom={map.max_zoom}
                    />
                    <OverlayWatcher onChange={setFootfallOn} />
                    <LayersControl position="topright">
                        {map.has_footfall && (
                            <LayersControl.Overlay name={FOOTFALL_LAYER}>
                                <LayerGroup>
                                    {footfallOn && (
                                        <FootfallLayer
                                            view={footfallView}
                                            factor={periodFactor(
                                                footfallView,
                                                map.day_part_intensity,
                                                map.footfall_exponent,
                                            )}
                                            onStatus={setFootfallStatus}
                                        />
                                    )}
                                </LayerGroup>
                            </LayersControl.Overlay>
                        )}
                        <LayersControl.Overlay checked name="Neighbourhoods">
                            <LayerGroup>
                                {map.neighbourhoods.map((n) =>
                                    n.boundary ? (
                                        <GeoJSON
                                            key={n.name}
                                            data={n.boundary}
                                            style={{
                                                className: 'map-neighbourhood',
                                            }}
                                        >
                                            <Tooltip sticky>{n.name}</Tooltip>
                                        </GeoJSON>
                                    ) : (
                                        <Circle
                                            key={n.name}
                                            center={[n.lat, n.lng]}
                                            radius={n.radius_m}
                                            className="map-neighbourhood"
                                        >
                                            <Tooltip sticky>{n.name}</Tooltip>
                                        </Circle>
                                    ),
                                )}
                            </LayerGroup>
                        </LayersControl.Overlay>
                        <LayersControl.Overlay
                            checked
                            name="Points of interest"
                        >
                            <LayerGroup>
                                {map.points_of_interest.map((p) => (
                                    <CircleMarker
                                        key={`${p.type}-${p.name}`}
                                        center={[p.lat, p.lng]}
                                        radius={4}
                                        className="map-poi"
                                    >
                                        <Tooltip>
                                            {p.name}{' '}
                                            <span className="text-muted-foreground">
                                                ·{' '}
                                                {humanize(p.type).toLowerCase()}
                                            </span>
                                        </Tooltip>
                                    </CircleMarker>
                                ))}
                            </LayerGroup>
                        </LayersControl.Overlay>
                    </LayersControl>

                    {located(forSale).map((b) => (
                        <CircleMarker
                            // Leaflet only reads className when it creates the
                            // path, so selecting a marker remounts it.
                            key={`${b.id}-${selectedId === b.id}`}
                            center={[b.lat, b.lng]}
                            radius={selectedId === b.id ? 9 : 6}
                            className={cn(
                                'map-marker map-marker--for-sale',
                                selectedId === b.id && 'map-marker--selected',
                            )}
                            eventHandlers={{ click: () => onSelect?.(b) }}
                        >
                            <Tooltip>
                                <span className="font-medium">
                                    {b.fictional_name}
                                </span>
                                <br />
                                {b.neighbourhood} · traspaso{' '}
                                {formatCents(b.traspaso_cents)} · footfall{' '}
                                {b.footfall.toFixed(1)}
                            </Tooltip>
                        </CircleMarker>
                    ))}

                    {located(competitors).map((c) => (
                        <CircleMarker
                            key={c.key}
                            center={[c.lat, c.lng]}
                            radius={7}
                            className="map-marker map-marker--rival"
                        >
                            <Tooltip>
                                <span className="font-medium">{c.name}</span>
                                <br />
                                {Math.round(c.distance_metres)} m away · quality{' '}
                                {Math.round(c.quality)} · reputation{' '}
                                {Math.round(c.reputation)}
                            </Tooltip>
                        </CircleMarker>
                    ))}

                    {own?.lat != null && own.lng != null && (
                        <CircleMarker
                            center={[own.lat, own.lng]}
                            radius={10}
                            className="map-marker map-marker--own"
                        >
                            <Tooltip
                                permanent
                                direction="top"
                                offset={[0, -10]}
                            >
                                {own.fictional_name}
                            </Tooltip>
                        </CircleMarker>
                    )}
                </MapContainer>

                {footfallOn && (
                    <div className="absolute top-2 left-12 z-[1000] space-y-1.5 rounded-md border bg-background/90 px-2 py-1.5 text-xs shadow-sm">
                        <label className="flex items-center gap-2">
                            <span className="font-medium">Footfall</span>
                            <select
                                value={footfallView}
                                onChange={(e) =>
                                    setFootfallView(
                                        e.target.value as FootfallView,
                                    )
                                }
                                className="rounded border bg-background px-1 py-0.5"
                            >
                                {FOOTFALL_VIEWS.map((v) => (
                                    <option key={v.key} value={v.key}>
                                        {v.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                        {footfallStatus === 'loading' && (
                            <div className="text-muted-foreground">
                                Loading…
                            </div>
                        )}
                        {footfallStatus === 'error' && (
                            <div className="text-destructive">
                                Couldn't load footfall.
                            </div>
                        )}
                        {footfallStatus === 'ready' && (
                            <div className="flex items-end gap-0.5">
                                {FOOTFALL_BINS.map((bin, i) => (
                                    <div
                                        key={bin}
                                        className="flex flex-col items-center gap-0.5"
                                    >
                                        <span
                                            className="h-2 w-8"
                                            style={{
                                                background: `var(--viz-seq-${i + 1})`,
                                            }}
                                        />
                                        <span className="text-[10px] text-muted-foreground tabular-nums">
                                            {bin}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {kinds.length > 0 && (
                    <div className="pointer-events-none absolute bottom-6 left-2 z-[1000] space-y-1 rounded-md border bg-background/90 px-2 py-1.5 text-xs shadow-sm">
                        {kinds.map((k) => (
                            <div
                                key={k.key}
                                className="flex items-center gap-2"
                            >
                                <svg
                                    className={k.size}
                                    viewBox="0 0 16 16"
                                    aria-hidden
                                >
                                    <circle
                                        cx="8"
                                        cy="8"
                                        r="6"
                                        className={`map-marker map-marker--${k.key}`}
                                    />
                                </svg>
                                {k.label}
                            </div>
                        ))}
                        <div className="flex items-center gap-2">
                            <svg
                                className="size-3"
                                viewBox="0 0 16 16"
                                aria-hidden
                            >
                                <circle
                                    cx="8"
                                    cy="8"
                                    r="5"
                                    className="map-poi"
                                />
                            </svg>
                            Landmark
                        </div>
                    </div>
                )}
            </div>
            <figcaption className="text-xs text-muted-foreground">
                Business locations, financial data and operating characteristics
                are simulated.
                {map.placeholder &&
                    ' Neighbourhood areas and landmarks are approximate until real OpenStreetMap data is imported.'}
                {footfallOn &&
                    ' Footfall is a 0–10 estimate from OpenStreetMap streets and places, not a pedestrian count. Each time of day is scaled by how busy the streets are then, so quieter periods look lighter.'}
            </figcaption>
        </figure>
    );
}
