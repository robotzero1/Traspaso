import 'leaflet/dist/leaflet.css';
import {
    CircleMarker,
    MapContainer,
    TileLayer,
    useMapEvents,
} from 'react-leaflet';

export type Pin = { lat: number; lng: number } | null;

function ClickToPin({ onPin }: { onPin: (pin: Pin) => void }) {
    useMapEvents({
        click: (e) => onPin({ lat: e.latlng.lat, lng: e.latlng.lng }),
    });

    return null;
}

/** OpenStreetMap with a pin the user drops where the café is. */
export function PinMap({
    tileUrl,
    attribution,
    maxZoom,
    centre,
    pin,
    onPin,
    zoom = 13,
}: {
    tileUrl: string;
    attribution: string;
    maxZoom: number;
    centre: [number, number];
    pin: Pin;
    onPin?: (pin: Pin) => void;
    zoom?: number;
}) {
    return (
        <div className="h-72 overflow-hidden rounded-lg border sm:h-96">
            <MapContainer
                center={pin ? [pin.lat, pin.lng] : centre}
                zoom={zoom}
                maxZoom={maxZoom}
                scrollWheelZoom
                className="size-full"
            >
                <TileLayer
                    url={tileUrl}
                    attribution={attribution}
                    maxZoom={maxZoom}
                />
                {onPin && <ClickToPin onPin={onPin} />}
                {pin && (
                    <CircleMarker
                        center={[pin.lat, pin.lng]}
                        radius={9}
                        className="map-marker map-marker--own"
                    />
                )}
            </MapContainer>
        </div>
    );
}
