import { useState, useEffect, useRef } from "react";
import { Plus, Trash2 } from "lucide-react";
import { Input } from "../ui/input";
import { Button } from "../ui/button";

declare const google: any;

interface LocationData {
    location_name: string;
    latitude: number;
    longitude: number;
    place_id?: string;
    address?: string;
    viewport?: any;
    raw_place?: any;
    is_primary?: boolean;
    contact_no?: string;
}

const LocationInput = ({
    value,
    onChange,
    onRemove,
    mapsReady,
}: {
    value: LocationData;
    onChange: (value: LocationData) => void;
    onRemove?: () => void;
    mapsReady: boolean;
}) => {
    const inputRef = useRef<HTMLInputElement | null>(null);
    const mapRef = useRef<HTMLDivElement | null>(null);
    const mapInstance = useRef<any>(null);
    const markerInstance = useRef<any>(null);
    const autocompleteRef = useRef<any>(null);
    const visibilityObserver = useRef<ResizeObserver | null>(null);

    const location = value;
    // Log only when location_name changes

    const handleLocationInputFocus = () => {
        if (!mapsReady || !inputRef.current || autocompleteRef.current) return;
        autocompleteRef.current = new google.maps.places.Autocomplete(
            inputRef.current,
            {
                fields: [
                    "formatted_address",
                    "geometry",
                    "name",
                    "place_id",
                    "address_components",
                ],
                componentRestrictions: { country: "my" },
            },
        );

        autocompleteRef.current.addListener("place_changed", () => {
            const place = autocompleteRef.current.getPlace();
            if (!place.geometry) return;

            const viewport = place.geometry.viewport
                ? {
                      north: place.geometry.viewport.getNorthEast().lat(),
                      east: place.geometry.viewport.getNorthEast().lng(),
                      south: place.geometry.viewport.getSouthWest().lat(),
                      west: place.geometry.viewport.getSouthWest().lng(),
                  }
                : null;

            onChange({
                ...location,
                place_id: place.place_id || "",
                location_name: place.name || place.formatted_address || "",
                address: place.formatted_address || "",
                latitude: place.geometry.location.lat(),
                longitude: place.geometry.location.lng(),
                viewport,
                raw_place: place,
            });
        });
    };

    // Initialize map and marker
    useEffect(() => {
        if (
            !mapsReady ||
            !mapRef.current ||
            !location.latitude ||
            !location.longitude
        )
            return;

        if (!mapInstance.current) {
            mapInstance.current = new google.maps.Map(mapRef.current, {
                center: { lat: location.latitude, lng: location.longitude },
                zoom: 15,
            });
        }

        if (!markerInstance.current) {
            markerInstance.current = new google.maps.Marker({
                map: mapInstance.current,
                draggable: true,
            });

            markerInstance.current.addListener("dragend", () => {
                const pos = markerInstance.current.getPosition();
                if (!pos) return;

                const lat = pos.lat();
                const lng = pos.lng();

                const geocoder = new google.maps.Geocoder();
                geocoder.geocode(
                    { location: { lat, lng } },
                    (results: any[], status: string) => {
                        if (status === "OK" && results.length > 0) {
                            const place = results[0];

                            onChange({
                                ...location,
                                latitude: lat,
                                longitude: lng,
                                location_name: place.name,
                                address: place.formatted_address || "",
                                place_id: place.place_id,
                                raw_place: place,
                            });
                        } else {
                            onChange({
                                ...location,
                                latitude: lat,
                                longitude: lng,
                            });
                        }
                    },
                );
            });
        }

        markerInstance.current.setPosition({
            lat: location.latitude,
            lng: location.longitude,
        });
        mapInstance.current.setCenter({
            lat: location.latitude,
            lng: location.longitude,
        });
    }, [mapsReady, location.latitude, location.longitude]);

    useEffect(() => {
        if (!mapRef.current) return;
        if (!visibilityObserver.current) {
            visibilityObserver.current = new ResizeObserver((entries) => {
                for (const entry of entries) {
                    const { width, height } = entry.contentRect;
                    if (width > 0 && height > 0 && mapInstance.current) {
                        // @ts-ignore
                        google.maps.event.trigger(
                            mapInstance.current,
                            "resize",
                        );
                        mapInstance.current.setCenter({
                            lat: location.latitude,
                            lng: location.longitude,
                        });
                        if (
                            markerInstance.current &&
                            location.latitude &&
                            location.longitude
                        ) {
                            markerInstance.current.setPosition({
                                lat: location.latitude,
                                lng: location.longitude,
                            });
                        }
                    }
                }
            });
            visibilityObserver.current.observe(mapRef.current);
        }
        return () => {
            visibilityObserver.current?.disconnect();
            visibilityObserver.current = null;
        };
    }, [mapsReady, location.latitude, location.longitude]);

    return (
        <div className="relative">
            {onRemove && (
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="absolute top-0 right-0 z-10 text-red-500 hover:text-red-700 hover:bg-red-50"
                    onClick={() => onRemove?.()}
                >
                    <Trash2 size={18} />
                </Button>
            )}

            <div className="pr-10">
                <Input
                    ref={inputRef}
                    type="text"
                    value={location.location_name || ""}
                    onChange={(e) =>
                        onChange({
                            ...location,
                            location_name: e.target.value,
                        })
                    }
                    onFocus={handleLocationInputFocus} // only init autocomplete on focus
                    placeholder="Start typing to search for a place..."
                />
            </div>
            <div className="mt-2">
                <Input
                    type="text"
                    value={location.contact_no || ""}
                    onChange={(e) =>
                        onChange({
                            ...location,
                            contact_no: e.target.value,
                        })
                    }
                    placeholder="Contact Number"
                />
            </div>

            {location.location_name &&
                location.latitude &&
                location.longitude && (
                    <div className="mt-6">
                        <div className="relative w-full h-80 rounded-xl overflow-hidden border-4 border-orange-200 shadow-xl">
                            <div
                                ref={mapRef}
                                id="draggable-map"
                                className="absolute inset-0"
                            />
                        </div>
                    </div>
                )}
        </div>
    );
};

const Location = ({ data, setData, single = false }: any) => {
    const [mapsReady, setMapsReady] = useState(false);

    // Handle both single location (legacy) and locations array
    const locations: LocationData[] =
        data.locations ||
        (data.location && Object.keys(data.location).length > 0
            ? [data.location]
            : []);

    // Load Google Maps script
    function loadGoogleMaps(src: string) {
        if (window.google && window.google.maps) return Promise.resolve();

        if ((window as any)._googleMapsLoadingPromise) {
            return (window as any)._googleMapsLoadingPromise;
        }

        const promise = new Promise<void>((resolve, reject) => {
            const existing = document.querySelector(`script[src="${src}"]`);
            if (existing) {
                existing.addEventListener("load", () => resolve());
                existing.addEventListener("error", () => reject());
                return;
            }

            const script = document.createElement("script");
            script.src = src;
            script.async = true;
            script.onload = () => resolve();
            script.onerror = () => reject();
            document.body.appendChild(script);
        });

        (window as any)._googleMapsLoadingPromise = promise;
        return promise;
    }

    useEffect(() => {
        const envAny = import.meta.env as any;
        const apiKey =
            envAny.VITE_GOOGLE_MAPS_API_KEY || envAny.GOOGLE_MAPS_API_KEY;
        loadGoogleMaps(
            `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=places`,
        ).then(() => {
            setMapsReady(true);
        });
    }, []);

    const handleAddLocation = () => {
        const newLocations = [
            ...locations,
            { location_name: "", latitude: 0, longitude: 0, contact_no: "" },
        ];
        setData("locations", newLocations);
    };

    const handleRemoveLocation = (index: number) => {
        const newLocations = locations.filter((_, i) => i !== index);
        setData("locations", newLocations);
    };

    const handleUpdateLocation = (index: number, newValue: LocationData) => {
        const newLocations = [...locations];
        newLocations[index] = newValue;
        setData("locations", newLocations);
    };

    const locationsToRender: LocationData[] = single
        ? locations.length > 0
            ? [locations[0]]
            : [{ location_name: "", latitude: 0, longitude: 0, contact_no: "" }]
        : locations;

    return (
        <div className="space-y-6">
            {locationsToRender.map((loc, index) => (
                <div
                    key={index}
                    className="border p-4 rounded-md relative bg-gray-50"
                >
                    <LocationInput
                        value={loc}
                        onChange={(val: LocationData) =>
                            handleUpdateLocation(index, val)
                        }
                        onRemove={
                            !single && locations.length > 1
                                ? () => handleRemoveLocation(index)
                                : undefined
                        }
                        mapsReady={mapsReady}
                    />
                </div>
            ))}
            {!single && locations.length === 0 && (
                <div className="text-gray-500 text-sm italic mb-2">
                    No locations added.
                </div>
            )}
            {!single && (
                <Button
                    type="button"
                    onClick={handleAddLocation}
                    variant="outline"
                    className="w-full"
                >
                    <Plus className="mr-2 h-4 w-4" /> Add Location
                </Button>
            )}
        </div>
    );
};

export default Location;
