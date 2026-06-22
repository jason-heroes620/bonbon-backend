import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import type { Rack, Tender } from "@/types";
import { router } from "@inertiajs/react";
import { useMemo, useState } from "react";
import { Controller, useForm } from "react-hook-form";
import { toast } from "sonner";

export type Option = { value: string; label: string };

type TenderFormValues = {
    rack_id: string;
    tender_status: "active" | "inactive";
};

export function TenderForm({
    mode,
    tender,
    vendorLocations,
    racks,
}: {
    mode: "create" | "edit";
    tender?: Tender;
    vendorLocations: Option[];
    racks: Rack[];
}) {
    const methods = useForm<TenderFormValues>({
        defaultValues: {
            rack_id: tender?.rack_id ?? "",
            tender_status: (tender?.tender_status as any) ?? "active",
        },
        shouldUnregister: false,
    });

    const racksById = useMemo(() => {
        const map = new Map<string, Rack>();
        for (const r of racks) map.set(r.rack_id, r);
        return map;
    }, [racks]);

    const initialVendorLocationId =
        (tender?.rack_id
            ? racksById.get(tender.rack_id)?.vendor_location_id
            : undefined) ?? "";

    const [selectedVendorLocationId, setSelectedVendorLocationId] =
        useState<string>(initialVendorLocationId);

    const availableRacks = useMemo(() => {
        return racks.filter(
            (r) => r.vendor_location_id === selectedVendorLocationId,
        );
    }, [racks, selectedVendorLocationId]);

    const handleSubmit = (values: TenderFormValues) => {
        return new Promise<void>((resolve) => {
            const routeName =
                mode === "create" ? "tenders.store" : "tenders.update";
            const routeParams = mode === "create" ? [] : [tender!.tender_id];

            router.post(
                route(routeName, ...(routeParams as any)),
                (mode === "create"
                    ? values
                    : { _method: "put", ...values }) as any,
                {
                    onSuccess: () => {
                        toast.success(
                            mode === "create"
                                ? "Tender created successfully"
                                : "Tender updated successfully",
                        );
                        router.visit(route("tenders.index"));
                    },
                    onError: (errors: Record<string, string>) => {
                        toast.error(
                            mode === "create"
                                ? "Tender creation failed"
                                : "Failed to update tender",
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
                            methods.setValue("rack_id", "");
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
                    <Controller
                        control={methods.control}
                        name="rack_id"
                        render={({ field }) => (
                            <Select
                                value={field.value}
                                onValueChange={field.onChange}
                                disabled={!selectedVendorLocationId}
                            >
                                <SelectTrigger className="border border-[#D1D5DB] rounded-md px-4 py-2">
                                    <SelectValue placeholder="Select rack" />
                                </SelectTrigger>
                                <SelectContent>
                                    {availableRacks.map((r) => (
                                        <SelectItem
                                            key={r.rack_id}
                                            value={r.rack_id}
                                        >
                                            {r.rack_name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    />
                </div>

                <div className="flex flex-col gap-2 md:col-span-2">
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
                                    <SelectItem value="active">
                                        Active
                                    </SelectItem>
                                    <SelectItem value="inactive">
                                        Inactive
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        )}
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
