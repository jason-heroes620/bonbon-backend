import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import type { Compartment, Rack, TenderCompartment } from "@/types";
import { router } from "@inertiajs/react";
import { useMemo, useState } from "react";
import { Controller, useForm } from "react-hook-form";
import { toast } from "sonner";

export type Option = { value: string; label: string };

type TenderCompartmentFormValues = {
    rack_id: string;
    compartment_id: string;
    vendor_id: string;
    bid_price: number;
    durations: number;
    tender_status: "pending" | "selected" | "paid" | "expired" | "rejected";
    selected_at?: string | null;
};

export function TenderCompartmentForm({
    mode,
    tenderCompartment,
    vendors,
    vendorLocations,
    racks,
    compartments,
}: {
    mode: "create" | "edit";
    tenderCompartment?: TenderCompartment;
    vendors: Option[];
    vendorLocations: Option[];
    racks: Rack[];
    compartments: Compartment[];
}) {
    const methods = useForm<TenderCompartmentFormValues>({
        defaultValues: {
            rack_id: tenderCompartment?.rack_id ?? "",
            compartment_id: tenderCompartment?.compartment_id ?? "",
            vendor_id: tenderCompartment?.vendor_id ?? "",
            bid_price: Number(tenderCompartment?.bid_price ?? 0),
            durations: Number(tenderCompartment?.durations ?? 1),
            tender_status: (tenderCompartment?.tender_status as any) ?? "pending",
            selected_at: tenderCompartment?.selected_at ?? null,
        },
        shouldUnregister: false,
    });

    const racksById = useMemo(() => {
        const map = new Map<string, Rack>();
        for (const r of racks) map.set(r.rack_id, r);
        return map;
    }, [racks]);

    const compartmentsById = useMemo(() => {
        const map = new Map<string, Compartment>();
        for (const c of compartments) map.set(c.compartment_id, c);
        return map;
    }, [compartments]);

    const initialRackId = tenderCompartment?.rack_id ?? "";
    const initialVendorLocationId =
        (initialRackId
            ? racksById.get(initialRackId)?.vendor_location_id
            : undefined) ?? "";

    const [selectedVendorLocationId, setSelectedVendorLocationId] =
        useState<string>(initialVendorLocationId);
    const [selectedRackId, setSelectedRackId] =
        useState<string>(initialRackId);

    const availableRacks = useMemo(() => {
        return racks.filter(
            (r) => r.vendor_location_id === selectedVendorLocationId,
        );
    }, [racks, selectedVendorLocationId]);

    const selectedRack = selectedRackId ? racksById.get(selectedRackId) : null;
    const rackRows = Number(selectedRack?.rack_rows ?? 0);
    const rackCols = Number(selectedRack?.rack_columns ?? 0);

    const compartmentsForSelectedRack = useMemo(() => {
        if (!selectedRackId) return [];
        return compartments.filter((c) => c.rack_id === selectedRackId);
    }, [compartments, selectedRackId]);

    const compartmentByPos = useMemo(() => {
        const map = new Map<string, Compartment>();
        for (const c of compartmentsForSelectedRack) {
            map.set(`${c.row_index}-${c.column_index}`, c);
        }
        return map;
    }, [compartmentsForSelectedRack]);

    const selectedCompartmentId = methods.watch("compartment_id");
    const selectedCompartment = selectedCompartmentId
        ? compartmentsById.get(selectedCompartmentId)
        : null;

    const handleSubmit = (values: TenderCompartmentFormValues) => {
        const payload = {
            ...values,
            selected_at: values.selected_at ? values.selected_at : null,
        };

        return new Promise<void>((resolve) => {
            const routeName =
                mode === "create"
                    ? "tender_compartments.store"
                    : "tender_compartments.update";
            const routeParams =
                mode === "create"
                    ? []
                    : [tenderCompartment!.tender_compartment_id];

            router.post(
                route(routeName, ...(routeParams as any)),
                (mode === "create"
                    ? payload
                    : { _method: "put", ...payload }) as any,
                {
                    onSuccess: () => {
                        toast.success(
                            mode === "create"
                                ? "Tender compartment created successfully"
                                : "Tender compartment updated successfully",
                        );
                        router.visit(route("tender_compartments.index"));
                    },
                    onError: (errors: Record<string, string>) => {
                        toast.error(
                            mode === "create"
                                ? "Tender compartment creation failed"
                                : "Failed to update tender compartment",
                        );
                        Object.values(errors).forEach((e) => toast.error(e));
                    },
                    onFinish: () => resolve(),
                },
            );
        });
    };

    return (
        <form
            onSubmit={methods.handleSubmit(handleSubmit)}
            className="bg-white p-6 rounded-md shadow-md"
        >
            <div className="flex flex-col md:grid md:grid-cols-2 gap-4">
                <div className="flex flex-col gap-2 md:col-span-2">
                    <Label>Rack Location</Label>
                    <Select
                        value={selectedVendorLocationId}
                        onValueChange={(value) => {
                            setSelectedVendorLocationId(value);
                            setSelectedRackId("");
                            methods.setValue("rack_id", "");
                            methods.setValue("compartment_id", "");
                        }}
                    >
                        <SelectTrigger className="border border-[#D1D5DB] rounded-md px-4 py-2">
                            <SelectValue placeholder="Select location" />
                        </SelectTrigger>
                        <SelectContent>
                            {vendorLocations.map((opt) => (
                                <SelectItem key={opt.value} value={opt.value}>
                                    {opt.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="flex flex-col gap-2 md:col-span-2">
                    <Label>Rack</Label>
                    <Select
                        value={selectedRackId}
                        onValueChange={(value) => {
                            setSelectedRackId(value);
                            methods.setValue("rack_id", value);
                            methods.setValue("compartment_id", "");
                        }}
                        disabled={!selectedVendorLocationId}
                    >
                        <SelectTrigger className="border border-[#D1D5DB] rounded-md px-4 py-2">
                            <SelectValue placeholder="Select rack" />
                        </SelectTrigger>
                        <SelectContent>
                            {availableRacks.map((r) => (
                                <SelectItem key={r.rack_id} value={r.rack_id}>
                                    {r.rack_name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="flex flex-col gap-2 md:col-span-2">
                    <Label>Compartments</Label>
                    <Controller
                        control={methods.control}
                        name="compartment_id"
                        render={() => (
                            <div className="rounded-md border p-3">
                                {!selectedRack ? (
                                    <div className="text-sm text-gray-500">
                                        Select a rack to view compartments.
                                    </div>
                                ) : (
                                    <div className="overflow-x-auto">
                                        <div
                                            className="grid gap-2"
                                            style={{
                                                gridTemplateColumns: `repeat(${rackCols}, minmax(56px, 1fr))`,
                                            }}
                                        >
                                            {Array.from({
                                                length: rackRows,
                                            }).flatMap((_, rIdx) => {
                                                const row = rIdx + 1;
                                                return Array.from({
                                                    length: rackCols,
                                                }).map((_, cIdx) => {
                                                    const col = cIdx + 1;
                                                    const compartment =
                                                        compartmentByPos.get(
                                                            `${row}-${col}`,
                                                        );
                                                    if (!compartment) {
                                                        return (
                                                            <button
                                                                key={`${row}-${col}`}
                                                                type="button"
                                                                disabled
                                                                className="h-14 rounded-md border bg-gray-50 text-xs text-gray-400"
                                                            >
                                                                -
                                                            </button>
                                                        );
                                                    }

                                                    const isSelected =
                                                        selectedCompartmentId ===
                                                        compartment.compartment_id;
                                                    const isAllocated =
                                                        compartment.compartment_status ===
                                                        "allocated";
                                                    const isClosed =
                                                        compartment.compartment_status ===
                                                        "closed";
                                                    const isDisabled =
                                                        !compartment.is_active ||
                                                        isAllocated ||
                                                        isClosed;

                                                    return (
                                                        <button
                                                            key={
                                                                compartment.compartment_id
                                                            }
                                                            type="button"
                                                            disabled={
                                                                isDisabled
                                                            }
                                                            onClick={() =>
                                                                methods.setValue(
                                                                    "compartment_id",
                                                                    compartment.compartment_id,
                                                                    {
                                                                        shouldDirty: true,
                                                                        shouldTouch: true,
                                                                        shouldValidate: true,
                                                                    },
                                                                )
                                                            }
                                                            className={[
                                                                "h-14 rounded-md border px-2 text-left text-xs",
                                                                isSelected
                                                                    ? "border-[#3730A3] bg-[#3730A3]/10"
                                                                    : "bg-white",
                                                                isDisabled &&
                                                                !isSelected
                                                                    ? "opacity-50 cursor-not-allowed"
                                                                    : "hover:bg-gray-50",
                                                            ].join(" ")}
                                                        >
                                                            <div className="font-semibold">
                                                                {
                                                                    compartment.label
                                                                }
                                                            </div>
                                                            <div className="text-[10px] text-gray-500">
                                                                {isAllocated
                                                                    ? "Allocated"
                                                                    : isClosed
                                                                      ? "Closed"
                                                                      : `R${row} C${col}`}
                                                            </div>
                                                        </button>
                                                    );
                                                });
                                            })}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                    />
                    {selectedCompartment ? (
                        <div className="text-xs text-gray-600">
                            Selected: {selectedRack?.rack_name ?? "-"} -{" "}
                            {selectedCompartment.label}
                        </div>
                    ) : null}
                </div>

                <div className="flex flex-col gap-2 md:col-span-2">
                    <Label>Vendor</Label>
                    <Controller
                        control={methods.control}
                        name="vendor_id"
                        render={({ field }) => (
                            <Select
                                value={field.value}
                                onValueChange={field.onChange}
                            >
                                <SelectTrigger className="border border-[#D1D5DB] rounded-md px-4 py-2">
                                    <SelectValue placeholder="Select vendor" />
                                </SelectTrigger>
                                <SelectContent>
                                    {vendors.map((opt) => (
                                        <SelectItem
                                            key={opt.value}
                                            value={opt.value}
                                        >
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="bid_price">Bid Price</Label>
                    <Input
                        id="bid_price"
                        type="number"
                        min={0}
                        step="0.01"
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("bid_price", { valueAsNumber: true })}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="durations">Durations (months)</Label>
                    <Input
                        id="durations"
                        type="number"
                        min={1}
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("durations", { valueAsNumber: true })}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label>Status</Label>
                    <Controller
                        control={methods.control}
                        name="tender_status"
                        render={({ field }) => (
                            <Select
                                value={field.value}
                                onValueChange={field.onChange}
                            >
                                <SelectTrigger className="border border-[#D1D5DB] rounded-md px-4 py-2">
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="pending">
                                        Pending
                                    </SelectItem>
                                    <SelectItem value="selected">
                                        Selected
                                    </SelectItem>
                                    <SelectItem value="paid">Paid</SelectItem>
                                    <SelectItem value="expired">
                                        Expired
                                    </SelectItem>
                                    <SelectItem value="rejected">
                                        Rejected
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        )}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="selected_at">Selected At</Label>
                    <Input
                        id="selected_at"
                        type="datetime-local"
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("selected_at")}
                    />
                </div>
            </div>

            <div className="mt-6 flex justify-end gap-2">
                <Button type="submit" variant="default">
                    {mode === "create" ? "Create" : "Save"}
                </Button>
            </div>
        </form>
    );
}
