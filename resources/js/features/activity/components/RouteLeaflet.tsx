import { useEffect, useRef } from 'react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/** Real street map (Carto light tiles) with the GPS route drawn on top. */
export function RouteLeaflet({ track, className = '' }: { track: [number, number][]; className?: string }) {
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!ref.current || track.length < 2) return;

        const map = L.map(ref.current, {
            zoomControl: false,
            scrollWheelZoom: false,
            attributionControl: true,
        });

        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap © CARTO',
        }).addTo(map);

        const line = L.polyline(track, { color: '#fc4c02', weight: 4, opacity: 0.95, lineJoin: 'round' }).addTo(map);
        map.fitBounds(line.getBounds(), { padding: [24, 24] });

        L.circleMarker(track[0], { radius: 6, color: '#fff', weight: 2, fillColor: '#10b981', fillOpacity: 1 }).addTo(map);
        L.circleMarker(track[track.length - 1], { radius: 6, color: '#fff', weight: 2, fillColor: '#fc4c02', fillOpacity: 1 }).addTo(map);

        L.control.zoom({ position: 'topright' }).addTo(map);

        // Fix sizing when the container mounts inside a modal/card.
        setTimeout(() => map.invalidateSize(), 60);

        return () => {
            map.remove();
        };
    }, [track]);

    return <div ref={ref} className={className} />;
}
