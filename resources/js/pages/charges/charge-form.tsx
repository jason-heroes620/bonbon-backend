import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { Charge } from "@/types";
import { router } from "@inertiajs/react";
import { useForm } from "react-hook-form";
import { toast } from "sonner";

type ChargeFormValues = {
    charges_type: string;
    charges_name: string;
    charges_rate: number;
    charges_description: string;
    charges_status: boolean;
    charges_start_date: string;
    charges_end_date: string;
    sort_order: number;
};

export function ChargeForm({
    mode,
    charge,
}: {
    mode: "create" | "edit";
    charge?: Charge;
}) {
    const methods = useForm<ChargeFormValues>({
        defaultValues: {
            charges_type: charge?.charges_type ?? "",
            charges_name: charge?.charges_name ?? "",
            charges_rate: Number(charge?.charges_rate ?? 0),
            charges_description: charge?.charges_description ?? "",
            charges_status: charge ? Boolean(charge.charges_status) : true,
            charges_start_date: charge?.charges_start_date ?? "",
            charges_end_date: charge?.charges_end_date ?? "",
            sort_order: Number(charge?.sort_order ?? 0),
        },
        shouldUnregister: false,
    });

    const handleSubmit = (values: ChargeFormValues) => {
        const payload = {
            ...values,
            charges_type: values.charges_type.trim().slice(0, 1),
            charges_rate: Number(values.charges_rate ?? 0),
            sort_order: Number(values.sort_order ?? 0),
        };

        return new Promise<void>((resolve) => {
            if (mode === "create") {
                router.post(route("charges.store"), payload as any, {
                    onSuccess: () => {
                        toast.success("Charge created successfully");
                        router.visit(route("charges.index"));
                    },
                    onError: (errors: Record<string, string>) => {
                        toast.error("Charge creation failed");
                        Object.values(errors).forEach((error) =>
                            toast.error(error),
                        );
                    },
                    onFinish: () => resolve(),
                });
                return;
            }

            router.post(
                route("charges.update", charge!.charges_id),
                { _method: "put", ...payload } as any,
                {
                    onSuccess: () => {
                        toast.success("Charge updated successfully");
                        router.visit(route("charges.index"));
                    },
                    onError: (errors: Record<string, string>) => {
                        toast.error("Failed to update charge");
                        Object.values(errors).forEach((error) =>
                            toast.error(error),
                        );
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
                <div className="flex flex-col gap-2">
                    <Label htmlFor="charges_name">Name</Label>
                    <Input
                        id="charges_name"
                        type="text"
                        required
                        maxLength={255}
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("charges_name")}
                    />
                </div>
                <div></div>

                <div className="flex flex-col gap-2 md:col-span-2">
                    <Label htmlFor="charges_description">Description</Label>
                    <Input
                        id="charges_description"
                        type="text"
                        required
                        maxLength={255}
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("charges_description")}
                    />
                </div>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="charges_type">Type</Label>
                    <select
                        id="charges_type"
                        required
                        maxLength={1}
                        className="border border-[#D1D5DB] rounded-md px-4 py-2 uppercase"
                        {...methods.register("charges_type")}
                    >
                        <option value="F">Fixed</option>
                        <option value="P">Percentage</option>
                    </select>
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="charges_rate">Rate</Label>
                    <Input
                        id="charges_rate"
                        type="number"
                        min={0}
                        step="0.01"
                        required
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("charges_rate", {
                            valueAsNumber: true,
                        })}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="charges_start_date">Start Date</Label>
                    <Input
                        id="charges_start_date"
                        type="date"
                        required
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("charges_start_date")}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="charges_end_date">End Date</Label>
                    <Input
                        id="charges_end_date"
                        type="date"
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("charges_end_date")}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="sort_order">Sort Order</Label>
                    <Input
                        id="sort_order"
                        type="number"
                        min={0}
                        max={127}
                        required
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("sort_order", {
                            valueAsNumber: true,
                        })}
                    />
                </div>
                <div className="flex items-center space-x-2 md:col-span-2">
                    <input
                        type="checkbox"
                        id="charges_status"
                        className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                        {...methods.register("charges_status")}
                    />
                    <Label htmlFor="charges_status">Active</Label>
                </div>

                <div className="flex flex-end md:col-span-2 justify-end gap-2">
                    <Button
                        size={"sm"}
                        type="button"
                        variant="secondary"
                        onClick={() => router.visit(route("charges.index"))}
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
