import 'leaflet/dist/leaflet.css';
import {
    Circle,
    CircleMarker,
    GeoJSON,
    LayerGroup,
    LayersControl,
    MapContainer,
    TileLayer,
    Tooltip,
} from 'react-leaflet';
import { formatCents, humanize } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { BusinessForSale, Competitor, MapProps } from '@/types/game';

type Located<T> = T & { lat: number; lng: number };

const located = <T extends { lat: number | null; lng: number | null }>(
    items: T[],
): Located<T>[] =>
    items.filter((i): i is Located<T> => i.lat !== null && i.lng !== null);

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
                    <LayersControl position="topright">
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
            </figcaption>
        </figure>
    );
}
