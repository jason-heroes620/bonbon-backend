import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import Editor from "@/components/editor/editor";
import Location from "@/components/location/location";
import { MultiSelect } from "@/components/ui/multi-select";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { cn } from "@/lib/utils";
import type { Event } from "@/types";
import { router } from "@inertiajs/react";
import axios from "axios";
import { format } from "date-fns";
import { CalendarIcon } from "lucide-react";
import { useEffect, useState } from "react";
import { Controller, useForm } from "react-hook-form";
import { toast } from "sonner";

type LocationData = {
    location_name: string;
    latitude: number;
    longitude: number;
    place_id?: string;
    address?: string;
    viewport?: any;
    raw_place?: any;
};

type EventFormValues = {
    event_name: string;
    event_start_date: Date | null;
    event_end_date: Date | null;
    event_start_time: string;
    event_end_time: string;
    event_location: string;
    event_description: string;
    is_published: boolean;
    is_active: boolean;
    locations: LocationData[];
    categories: string[];
    event_image?: File | null;
    event_images?: File[];
    delete_event_image_ids?: number[];
    disabled_event_image_ids?: number[];
    disabled_event_image_ids_present?: boolean;
};

export function EventForm({
    mode,
    event,
}: {
    mode: "create" | "edit";
    event?: Event;
}) {
    const [evCategories, setEvCategories] = useState<
        { value: string; label: string }[]
    >([]);

    const methods = useForm<EventFormValues>({
        defaultValues: {
            event_name: event?.event_name ?? "",
            event_image: null,
            event_images: [],
            delete_event_image_ids: [],
            disabled_event_image_ids: [],
            disabled_event_image_ids_present: true,
            event_start_date: event?.event_start_date
                ? new Date(`${event.event_start_date.slice(0, 10)}T00:00:00`)
                : null,
            event_end_date: event?.event_end_date
                ? new Date(`${event.event_end_date.slice(0, 10)}T00:00:00`)
                : null,
            event_start_time: event?.event_start_time ?? "",
            event_end_time: event?.event_end_time ?? "",
            event_location: event?.event_location ?? "",
            event_description: event?.event_description ?? "",
            is_published: event ? Boolean(event.is_published) : false,
            is_active: event ? Boolean(event.is_active) : true,
            categories: event?.categories ?? [],
            locations: [
                {
                    location_name: event?.location_name ?? "",
                    latitude: Number(event?.location_latitude ?? 0),
                    longitude: Number(event?.location_longitude ?? 0),
                    place_id: event?.place_id ?? "",
                    address: event?.event_location ?? "",
                },
            ],
        },
        shouldUnregister: false,
    });

    const eventImageRegister = methods.register("event_image");
    const setValue = methods.setValue;
    const locations = methods.watch("locations");
    const selectedImage = methods.watch("event_image");
    const startDate = methods.watch("event_start_date");
    const endDate = methods.watch("event_end_date");
    const [localPreviewUrl, setLocalPreviewUrl] = useState<string | null>(null);
    const [galleryFiles, setGalleryFiles] = useState<
        { key: string; file: File; url: string }[]
    >([]);
    const [removedExistingImageIds, setRemovedExistingImageIds] = useState<
        number[]
    >([]);
    const [disabledExistingImageIds, setDisabledExistingImageIds] = useState<
        number[]
    >(() =>
        (event?.event_images ?? [])
            .filter((img) => img.is_enabled === false)
            .map((img) => img.event_image_id),
    );

    const existingEventImages = event?.event_images ?? [];

    useEffect(() => {
        axios.get(route("ev_categories.list")).then((res) => {
            setEvCategories(res.data);
        });
    }, []);

    useEffect(() => {
        if (!startDate || !endDate) {
            return;
        }
        if (endDate <= startDate) {
            methods.setValue("event_end_date", null, {
                shouldDirty: true,
                shouldValidate: true,
            });
        }
    }, [endDate, methods, startDate]);

    useEffect(() => {
        setValue(
            "event_images",
            galleryFiles.map((g) => g.file),
            { shouldDirty: true },
        );
    }, [galleryFiles, setValue]);

    useEffect(() => {
        return () => {
            galleryFiles.forEach((g) => URL.revokeObjectURL(g.url));
        };
    }, [galleryFiles]);

    useEffect(() => {
        if (!(selectedImage instanceof File)) {
            setLocalPreviewUrl(null);
            return;
        }

        const url = URL.createObjectURL(selectedImage);
        setLocalPreviewUrl(url);

        return () => {
            URL.revokeObjectURL(url);
        };
    }, [selectedImage]);

    useEffect(() => {
        const nextDisabled = (event?.event_images ?? [])
            .filter((img) => img.is_enabled === false)
            .map((img) => img.event_image_id);
        setDisabledExistingImageIds(nextDisabled);
        methods.setValue("disabled_event_image_ids", nextDisabled, {
            shouldDirty: false,
        });
        methods.setValue("disabled_event_image_ids_present", true, {
            shouldDirty: false,
        });
    }, [event?.event_id, methods]);

    const handleSubmit = (values: EventFormValues) => {
        const loc = values.locations?.[0];
        const payload = {
            event_name: values.event_name,
            event_start_date: values.event_start_date
                ? format(values.event_start_date, "yyyy-MM-dd")
                : "",
            event_end_date: values.event_end_date
                ? format(values.event_end_date, "yyyy-MM-dd")
                : "",
            event_start_time: values.event_start_time,
            event_end_time: values.event_end_time,
            event_location: values.event_location,
            event_description: values.event_description,
            location_name: loc?.location_name || "",
            location_latitude: loc?.latitude ?? 0,
            location_longitude: loc?.longitude ?? 0,
            place_id: loc?.place_id || "",
            is_published: Boolean(values.is_published),
            is_active: Boolean(values.is_active),
            categories: values.categories,
            event_image: values.event_image ?? undefined,
            event_images:
                values.event_images && values.event_images.length > 0
                    ? values.event_images
                    : undefined,
            delete_event_image_ids:
                values.delete_event_image_ids &&
                values.delete_event_image_ids.length > 0
                    ? values.delete_event_image_ids
                    : undefined,
            disabled_event_image_ids_present: true,
            disabled_event_image_ids:
                disabledExistingImageIds.length > 0
                    ? disabledExistingImageIds
                    : undefined,
        };

        return new Promise<void>((resolve) => {
            if (mode === "create") {
                router.post(route("events.store"), payload as any, {
                    forceFormData: true,
                    onSuccess: () => {
                        toast.success("Event created successfully");
                        router.visit(route("events.index"));
                    },
                    onError: (errors: Record<string, string>) => {
                        toast.error("Event creation failed");
                        Object.values(errors).forEach((error) =>
                            toast.error(error),
                        );
                    },
                    onFinish: () => resolve(),
                });
                return;
            }

            router.post(
                route("events.update", event!.event_id),
                { _method: "put", ...payload } as any,
                {
                    forceFormData: true,
                    onSuccess: () => {
                        toast.success("Event updated successfully");
                        router.visit(route("events.index"));
                    },
                    onError: (errors: Record<string, string>) => {
                        toast.error("Failed to update event");
                        Object.values(errors).forEach((error) =>
                            toast.error(error),
                        );
                    },
                    onFinish: () => resolve(),
                },
            );
        });
    };

    const previewUrl = localPreviewUrl ?? event?.event_image_path ?? null;

    return (
        <form
            onSubmit={methods.handleSubmit(handleSubmit)}
            className="bg-white p-6 rounded-md shadow-md"
        >
            <div className="flex flex-col md:grid md:grid-cols-2 gap-4">
                <div className="flex flex-col gap-2 md:col-span-2">
                    <Label htmlFor="event_name">Event Name</Label>
                    <Input
                        id="event_name"
                        type="text"
                        required
                        maxLength={100}
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("event_name")}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label>Start Date</Label>
                    <Controller
                        name="event_start_date"
                        control={methods.control}
                        rules={{ required: true }}
                        render={({ field }) => (
                            <Popover>
                                <PopoverTrigger asChild>
                                    <Button
                                        variant={"outline"}
                                        className={cn(
                                            "w-full pl-3 text-left font-normal",
                                            !field.value &&
                                                "text-muted-foreground",
                                        )}
                                    >
                                        {field.value ? (
                                            format(field.value, "PPP")
                                        ) : (
                                            <span>Pick a date</span>
                                        )}
                                        <CalendarIcon className="ml-auto h-4 w-4 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent
                                    className="w-auto p-0"
                                    align="start"
                                >
                                    <Calendar
                                        mode="single"
                                        selected={field.value ?? undefined}
                                        onSelect={(d) =>
                                            field.onChange(d ?? null)
                                        }
                                        disabled={(date) =>
                                            date < new Date("1900-01-01")
                                        }
                                        required
                                    />
                                </PopoverContent>
                            </Popover>
                        )}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label>End Date</Label>
                    <Controller
                        name="event_end_date"
                        control={methods.control}
                        rules={{ required: true }}
                        render={({ field }) => (
                            <Popover>
                                <PopoverTrigger asChild>
                                    <Button
                                        variant={"outline"}
                                        className={cn(
                                            "w-full pl-3 text-left font-normal",
                                            !field.value &&
                                                "text-muted-foreground",
                                        )}
                                    >
                                        {field.value ? (
                                            format(field.value, "PPP")
                                        ) : (
                                            <span>Pick a date</span>
                                        )}
                                        <CalendarIcon className="ml-auto h-4 w-4 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent
                                    className="w-auto p-0"
                                    align="start"
                                >
                                    <Calendar
                                        mode="single"
                                        selected={field.value ?? undefined}
                                        onSelect={(d) =>
                                            field.onChange(d ?? null)
                                        }
                                        startMonth={
                                            new Date(
                                                methods.getValues()
                                                    .event_start_date ?? "",
                                            )
                                        }
                                        disabled={(date) =>
                                            startDate
                                                ? date <= startDate
                                                : false
                                        }
                                        required
                                    />
                                </PopoverContent>
                            </Popover>
                        )}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="event_start_time">Start Time</Label>
                    <Input
                        id="event_start_time"
                        type="time"
                        required
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("event_start_time")}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="event_end_time">End Time</Label>
                    <Input
                        id="event_end_time"
                        type="time"
                        required
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("event_end_time")}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="event_location">Location</Label>
                    <Input
                        id="event_location"
                        type="text"
                        required
                        maxLength={100}
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("event_location")}
                    />
                </div>

                <div className="flex flex-col gap-2 md:col-span-2">
                    <Label>Google Location Search</Label>
                    <Location
                        data={{ locations: locations ?? [] }}
                        setData={(key: string, value: any) => {
                            if (key === "locations") {
                                methods.setValue("locations", value, {
                                    shouldDirty: true,
                                });
                            }
                        }}
                        single
                    />
                </div>

                <div className="flex flex-col gap-2 md:col-span-2">
                    <Label htmlFor="event_description">Description</Label>
                    <Editor
                        placeholder="Full details..."
                        control={methods.control}
                        name="event_description"
                        defaultValue={methods.getValues("event_description")}
                    />
                </div>

                <div className="flex flex-col gap-2 md:col-span-2">
                    <Label>Categories</Label>
                    <Controller
                        name="categories"
                        control={methods.control}
                        render={({ field }) => (
                            <MultiSelect
                                defaultValue={field.value || []}
                                options={evCategories}
                                onValueChange={field.onChange}
                                placeholder="Choose categories"
                            />
                        )}
                    />
                </div>

                <div className="flex flex-col gap-2 md:col-span-2">
                    <Label htmlFor="event_image">Event Image</Label>
                    <div className="flex items-center gap-2">
                        <Input
                            id="event_image"
                            type="file"
                            accept="image/*"
                            className="border border-[#D1D5DB] rounded-md"
                            onChange={(e) => {
                                eventImageRegister.onChange(e);
                                const file = e.target.files?.[0] ?? null;
                                methods.setValue("event_image", file, {
                                    shouldDirty: true,
                                });
                            }}
                            name={eventImageRegister.name}
                            ref={eventImageRegister.ref}
                        />
                        {previewUrl ? (
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() => {
                                    window.open(
                                        previewUrl,
                                        "_blank",
                                        "noopener,noreferrer",
                                    );
                                }}
                            >
                                Preview
                            </Button>
                        ) : null}
                    </div>
                </div>
                {previewUrl ? (
                    <div className="md:col-span-2">
                        <img
                            src={previewUrl}
                            alt={methods.getValues("event_name")}
                            className="w-32 h-32 object-cover rounded-md border"
                        />
                    </div>
                ) : null}

                <div className="flex flex-col gap-2 md:col-span-2">
                    <Label htmlFor="event_images">Event Images</Label>
                    <Input
                        id="event_images"
                        type="file"
                        accept="image/*"
                        multiple
                        className="border border-[#D1D5DB] rounded-md"
                        onChange={(e) => {
                            const files = Array.from(e.target.files ?? []);
                            if (files.length === 0) {
                                return;
                            }

                            setGalleryFiles((prev) => [
                                ...prev,
                                ...files.map((file) => ({
                                    key: `${file.name}-${file.size}-${file.lastModified}`,
                                    file,
                                    url: URL.createObjectURL(file),
                                })),
                            ]);

                            e.target.value = "";
                        }}
                    />

                    <div className="grid grid-cols-3 md:grid-cols-6 gap-2">
                        {existingEventImages
                            .filter(
                                (img) =>
                                    !removedExistingImageIds.includes(
                                        img.event_image_id,
                                    ),
                            )
                            .map((img) => (
                                <div
                                    key={img.event_image_id}
                                    className="border rounded-md p-1"
                                >
                                    <img
                                        src={img.event_image_path}
                                        alt=""
                                        className={cn(
                                            "w-full h-16 object-cover rounded cursor-pointer",
                                            disabledExistingImageIds.includes(
                                                img.event_image_id,
                                            ) && "opacity-50",
                                        )}
                                        onClick={() => {
                                            window.open(
                                                img.event_image_path,
                                                "_blank",
                                                "noopener,noreferrer",
                                            );
                                        }}
                                    />
                                    <div className="flex items-center justify-between gap-2 mt-1">
                                        <label className="flex items-center gap-2 text-xs">
                                            <input
                                                type="checkbox"
                                                checked={
                                                    !disabledExistingImageIds.includes(
                                                        img.event_image_id,
                                                    )
                                                }
                                                onChange={(e) => {
                                                    const isEnabled =
                                                        e.target.checked;
                                                    const nextDisabled =
                                                        isEnabled
                                                            ? disabledExistingImageIds.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      img.event_image_id,
                                                              )
                                                            : Array.from(
                                                                  new Set([
                                                                      ...disabledExistingImageIds,
                                                                      img.event_image_id,
                                                                  ]),
                                                              );
                                                    setDisabledExistingImageIds(
                                                        nextDisabled,
                                                    );
                                                    methods.setValue(
                                                        "disabled_event_image_ids",
                                                        nextDisabled,
                                                        {
                                                            shouldDirty: true,
                                                        },
                                                    );
                                                    methods.setValue(
                                                        "disabled_event_image_ids_present",
                                                        true,
                                                        {
                                                            shouldDirty: true,
                                                        },
                                                    );
                                                }}
                                            />
                                            Enabled
                                        </label>
                                        <Button
                                            type="button"
                                            size={"sm"}
                                            variant="secondary"
                                            onClick={() => {
                                                window.open(
                                                    img.event_image_path,
                                                    "_blank",
                                                    "noopener,noreferrer",
                                                );
                                            }}
                                        >
                                            Preview
                                        </Button>
                                    </div>
                                    <Button
                                        type="button"
                                        size={"sm"}
                                        variant="secondary"
                                        className="w-full mt-1"
                                        onClick={() => {
                                            const next = Array.from(
                                                new Set([
                                                    ...removedExistingImageIds,
                                                    img.event_image_id,
                                                ]),
                                            );
                                            setRemovedExistingImageIds(next);
                                            const nextDisabled =
                                                disabledExistingImageIds.filter(
                                                    (id) =>
                                                        id !==
                                                        img.event_image_id,
                                                );
                                            setDisabledExistingImageIds(
                                                nextDisabled,
                                            );
                                            methods.setValue(
                                                "delete_event_image_ids",
                                                next,
                                                { shouldDirty: true },
                                            );
                                            methods.setValue(
                                                "disabled_event_image_ids",
                                                nextDisabled,
                                                { shouldDirty: true },
                                            );
                                            methods.setValue(
                                                "disabled_event_image_ids_present",
                                                true,
                                                { shouldDirty: true },
                                            );
                                        }}
                                    >
                                        Delete
                                    </Button>
                                </div>
                            ))}

                        {galleryFiles.map((g) => (
                            <div key={g.key} className="border rounded-md p-1">
                                <img
                                    src={g.url}
                                    alt=""
                                    className="w-full h-16 object-cover rounded"
                                />
                                <Button
                                    type="button"
                                    size={"sm"}
                                    variant="secondary"
                                    className="w-full mt-1"
                                    onClick={() => {
                                        setGalleryFiles((prev) => {
                                            const target = prev.find(
                                                (p) => p.key === g.key,
                                            );
                                            if (target) {
                                                URL.revokeObjectURL(target.url);
                                            }
                                            return prev.filter(
                                                (p) => p.key !== g.key,
                                            );
                                        });
                                    }}
                                >
                                    Delete
                                </Button>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="flex items-center space-x-2">
                    <input
                        type="checkbox"
                        id="is_published"
                        className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                        {...methods.register("is_published")}
                    />
                    <Label htmlFor="is_published">Published</Label>
                </div>

                <div className="flex items-center space-x-2">
                    <input
                        type="checkbox"
                        id="is_active"
                        className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                        {...methods.register("is_active")}
                    />
                    <Label htmlFor="is_active">Active</Label>
                </div>

                <div className="flex flex-end md:col-span-2 justify-end gap-2">
                    <Button
                        size={"sm"}
                        type="button"
                        variant="secondary"
                        onClick={() => router.visit(route("events.index"))}
                    >
                        Cancel
                    </Button>
                    <Button
                        size={"sm"}
                        type="submit"
                        disabled={methods.formState.isSubmitting}
                    >
                        {methods.formState.isSubmitting
                            ? "Saving..."
                            : mode === "create"
                              ? "Save"
                              : "Update"}
                    </Button>
                </div>
            </div>
        </form>
    );
}
