import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { Tax } from "@/types";
import { router } from "@inertiajs/react";
import { useForm } from "react-hook-form";
import { toast } from "sonner";

type TaxFormValues = {
    tax_name: string;
    tax_rate: number;
    is_active: boolean;
};

export function TaxForm({
    mode,
    tax,
}: {
    mode: "create" | "edit";
    tax?: Tax;
}) {
    const methods = useForm<TaxFormValues>({
        defaultValues: {
            tax_name: tax?.tax_name ?? "",
            tax_rate: Number(tax?.tax_rate ?? 0),
            is_active: tax ? Boolean(tax.is_active) : true,
        },
        shouldUnregister: false,
    });

    const handleSubmit = (values: TaxFormValues) => {
        const payload = {
            ...values,
            tax_rate: Number(values.tax_rate ?? 0),
        };

        return new Promise<void>((resolve) => {
            if (mode === "create") {
                router.post(route("taxes.store"), payload as any, {
                    onSuccess: () => {
                        toast.success("Tax created successfully");
                        router.visit(route("taxes.index"));
                    },
                    onError: (errors: Record<string, string>) => {
                        toast.error("Tax creation failed");
                        Object.values(errors).forEach((error) =>
                            toast.error(error),
                        );
                    },
                    onFinish: () => resolve(),
                });
                return;
            }

            router.post(
                route("taxes.update", tax!.tax_rate_id),
                { _method: "put", ...payload } as any,
                {
                    onSuccess: () => {
                        toast.success("Tax updated successfully");
                        router.visit(route("taxes.index"));
                    },
                    onError: (errors: Record<string, string>) => {
                        toast.error("Failed to update tax");
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
                <div className="flex flex-col gap-2 md:col-span-2">
                    <Label htmlFor="tax_name">Name</Label>
                    <Input
                        id="tax_name"
                        type="text"
                        required
                        maxLength={150}
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("tax_name")}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="tax_rate">Rate</Label>
                    <Input
                        id="tax_rate"
                        type="number"
                        step="0.01"
                        min={0}
                        required
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("tax_rate", {
                            valueAsNumber: true,
                        })}
                    />
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
                        onClick={() => router.visit(route("taxes.index"))}
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
