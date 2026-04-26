import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { TransactionType } from "@/types";
import { router } from "@inertiajs/react";
import { useForm } from "react-hook-form";
import { toast } from "sonner";

type TransactionTypeFormValues = {
    transaction_type: string;
    transaction_name: string;
    credit_amount: number;
    effective_date: string;
    expire_date: string;
    is_active: boolean;
};

function toDateInput(value: unknown): string {
    if (!value) {
        return "";
    }
    if (typeof value === "string") {
        return value.slice(0, 10);
    }
    return "";
}

export function TransactionTypeForm({
    mode,
    transactionType,
}: {
    mode: "create" | "edit";
    transactionType?: TransactionType;
}) {
    const methods = useForm<TransactionTypeFormValues>({
        defaultValues: {
            transaction_type: transactionType?.transaction_type ?? "",
            transaction_name: transactionType?.transaction_name ?? "",
            credit_amount: Number(transactionType?.credit_amount ?? 0),
            effective_date: toDateInput(transactionType?.effective_date) || "",
            expire_date: toDateInput(transactionType?.expire_date) || "",
            is_active: transactionType
                ? Boolean(transactionType.is_active)
                : true,
        },
        shouldUnregister: false,
    });

    const handleSubmit = (values: TransactionTypeFormValues) => {
        const payload: any = {
            ...values,
            credit_amount: Number(values.credit_amount ?? 0),
        };

        if (!payload.expire_date) {
            payload.expire_date = null;
        }

        return new Promise<void>((resolve) => {
            if (mode === "create") {
                router.post(route("transaction_types.store"), payload, {
                    onSuccess: () => {
                        toast.success("Transaction type created successfully");
                        router.visit(route("transaction_types.index"));
                    },
                    onError: (errors: Record<string, string>) => {
                        toast.error("Transaction type creation failed");
                        Object.values(errors).forEach((error) =>
                            toast.error(error),
                        );
                    },
                    onFinish: () => resolve(),
                });
                return;
            }

            router.post(
                route("transaction_types.update", transactionType!.id),
                { _method: "put", ...payload } as any,
                {
                    onSuccess: () => {
                        toast.success("Transaction type updated successfully");
                        router.visit(route("transaction_types.index"));
                    },
                    onError: (errors: Record<string, string>) => {
                        toast.error("Failed to update transaction type");
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
                    <Label htmlFor="transaction_type">Transaction Type</Label>
                    <Input
                        id="transaction_type"
                        type="text"
                        required
                        maxLength={100}
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("transaction_type")}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="transaction_name">Transaction Name</Label>
                    <Input
                        id="transaction_name"
                        type="text"
                        required
                        maxLength={100}
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("transaction_name")}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="credit_amount">Credit Amount</Label>
                    <Input
                        id="credit_amount"
                        type="number"
                        min={0}
                        required
                        {...methods.register("credit_amount", {
                            valueAsNumber: true,
                            required: true,
                        })}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="effective_date">Effective Date</Label>
                    <Input
                        id="effective_date"
                        type="date"
                        required
                        {...methods.register("effective_date", {
                            required: true,
                        })}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="expire_date">Expire Date</Label>
                    <Input
                        id="expire_date"
                        type="date"
                        {...methods.register("expire_date")}
                    />
                </div>

                <div className="flex items-center space-x-2 md:col-span-2">
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
                        onClick={() =>
                            router.visit(route("transaction_types.index"))
                        }
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
