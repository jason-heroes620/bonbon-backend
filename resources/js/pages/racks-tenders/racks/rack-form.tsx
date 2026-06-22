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
import type { Rack } from "@/types";
import { router } from "@inertiajs/react";
import { Controller, useForm } from "react-hook-form";
import { toast } from "sonner";

export type Option = { value: string; label: string };

type RackFormValues = {
    vendor_location_id: string;
    rack_name: string;
    rack_type?: string | null;
    rack_capacity?: string | null;
    rack_rows: number;
    rack_columns: number;
    rack_status: "active" | "inactive";
};

export function RackForm({
    mode,
    rack,
    vendorLocations,
}: {
    mode: "create" | "edit";
    rack?: Rack;
    vendorLocations: Option[];
}) {
    const methods = useForm<RackFormValues>({
        defaultValues: {
            vendor_location_id: rack?.vendor_location_id ?? "",
            rack_name: rack?.rack_name ?? "",
            rack_type: rack?.rack_type ?? "",
            rack_capacity: rack?.rack_capacity ?? "",
            rack_rows: Number(rack?.rack_rows ?? 1),
            rack_columns: Number(rack?.rack_columns ?? 1),
            rack_status: (rack?.rack_status as any) ?? "active",
        },
        shouldUnregister: false,
    });

    const handleSubmit = (values: RackFormValues) => {
        const payload = {
            ...values,
            rack_type: values.rack_type ? values.rack_type : null,
            rack_capacity: values.rack_capacity ? values.rack_capacity : null,
        };

        return new Promise<void>((resolve) => {
            const routeName =
                mode === "create" ? "racks.store" : "racks.update";
            const routeParams = mode === "create" ? [] : [rack!.rack_id];

            router.post(
                route(routeName, ...(routeParams as any)),
                (mode === "create"
                    ? payload
                    : { _method: "put", ...payload }) as any,
                {
                    onSuccess: () => {
                        toast.success(
                            mode === "create"
                                ? "Rack created successfully"
                                : "Rack updated successfully",
                        );
                    },
                    onError: (errors: Record<string, string>) => {
                        toast.error(
                            mode === "create"
                                ? "Rack creation failed"
                                : "Failed to update rack",
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
                    <Label>Vendor Location</Label>
                    <Controller
                        control={methods.control}
                        name="vendor_location_id"
                        render={({ field }) => (
                            <Select
                                value={field.value}
                                onValueChange={field.onChange}
                            >
                                <SelectTrigger className="border border-[#D1D5DB] rounded-md px-4 py-2">
                                    <SelectValue placeholder="Select location" />
                                </SelectTrigger>
                                <SelectContent>
                                    {vendorLocations.map((opt) => (
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

                <div className="flex flex-col gap-2 md:col-span-2">
                    <Label htmlFor="rack_name">Rack Name</Label>
                    <Input
                        id="rack_name"
                        type="text"
                        required
                        maxLength={255}
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("rack_name")}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="rack_type">Rack Type</Label>
                    <Input
                        id="rack_type"
                        type="text"
                        maxLength={255}
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("rack_type")}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="rack_capacity">Rack Capacity</Label>
                    <Input
                        id="rack_capacity"
                        type="text"
                        maxLength={255}
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("rack_capacity")}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="rack_rows">Rows</Label>
                    <Input
                        id="rack_rows"
                        type="number"
                        min={1}
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("rack_rows", {
                            valueAsNumber: true,
                        })}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="rack_columns">Columns</Label>
                    <Input
                        id="rack_columns"
                        type="number"
                        min={1}
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("rack_columns", {
                            valueAsNumber: true,
                        })}
                    />
                </div>

                <div className="flex flex-col gap-2 md:col-span-2">
                    <Label>Status</Label>
                    <Controller
                        control={methods.control}
                        name="rack_status"
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
