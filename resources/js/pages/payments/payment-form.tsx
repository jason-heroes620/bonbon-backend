import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { Payment } from "@/types";
import { router } from "@inertiajs/react";
import { useForm } from "react-hook-form";
import { toast } from "sonner";

type PaymentFormValues = {
    order_id: string;
    order_no: string;
    transaction_id: string;
    ref_no: string;
    payment_description: string;
    payment_method: string;
    payment_amount: number;
    payment_date: string;
    issuing_bank: string;
    payment_ref: string;
    bank_ref: string;
    cc_name: string;
    cc_number: string;
    payment_status: number;
};

export function PaymentForm({
    mode,
    payment,
}: {
    mode: "create" | "edit";
    payment?: Payment;
}) {
    const methods = useForm<PaymentFormValues>({
        defaultValues: {
            order_id: payment?.order_id ?? "",
            order_no: payment?.order_no ?? "",
            transaction_id: payment?.transaction_id ?? "",
            ref_no: payment?.ref_no ?? "",
            payment_description: payment?.payment_description ?? "",
            payment_method: payment?.payment_method ?? "",
            payment_amount: Number(payment?.payment_amount ?? 0),
            payment_date: payment?.payment_date?.slice(0, 10) ?? "",
            issuing_bank: payment?.issuing_bank ?? "",
            payment_ref: payment?.payment_ref ?? "",
            bank_ref: payment?.bank_ref ?? "",
            cc_name: payment?.cc_name ?? "",
            cc_number: payment?.cc_number ?? "",
            payment_status: Number(payment?.payment_status ?? 0),
        },
        shouldUnregister: false,
    });

    const handleSubmit = (values: PaymentFormValues) => {
        return new Promise<void>((resolve) => {
            if (mode === "create") {
                router.post(route("payments.store"), values as any, {
                    onSuccess: () => {
                        toast.success("Payment created successfully");
                        router.visit(route("payments.create"));
                    },
                    onError: (errors: Record<string, string>) => {
                        toast.error("Payment creation failed");
                        Object.values(errors).forEach((error) =>
                            toast.error(error),
                        );
                    },
                    onFinish: () => resolve(),
                });
                return;
            }

            router.post(
                route("payments.update", payment!.payment_id),
                { _method: "put", ...values } as any,
                {
                    onSuccess: () => {
                        toast.success("Payment updated successfully");
                        router.visit(route("payments.edit", payment!.payment_id));
                    },
                    onError: (errors: Record<string, string>) => {
                        toast.error("Failed to update payment");
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
                    <Label htmlFor="order_id">Order ID</Label>
                    <Input
                        id="order_id"
                        type="text"
                        required
                        {...methods.register("order_id")}
                    />
                </div>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="order_no">Order No</Label>
                    <Input
                        id="order_no"
                        type="text"
                        required
                        maxLength={50}
                        {...methods.register("order_no")}
                    />
                </div>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="transaction_id">Transaction ID</Label>
                    <Input
                        id="transaction_id"
                        type="text"
                        required
                        maxLength={50}
                        {...methods.register("transaction_id")}
                    />
                </div>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="ref_no">Ref No</Label>
                    <Input
                        id="ref_no"
                        type="text"
                        required
                        maxLength={50}
                        {...methods.register("ref_no")}
                    />
                </div>
                <div className="flex flex-col gap-2 md:col-span-2">
                    <Label htmlFor="payment_description">Description</Label>
                    <Input
                        id="payment_description"
                        type="text"
                        required
                        {...methods.register("payment_description")}
                    />
                </div>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="payment_method">Method</Label>
                    <Input
                        id="payment_method"
                        type="text"
                        required
                        maxLength={50}
                        {...methods.register("payment_method")}
                    />
                </div>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="payment_amount">Amount</Label>
                    <Input
                        id="payment_amount"
                        type="number"
                        step="0.01"
                        min={0}
                        required
                        {...methods.register("payment_amount", {
                            valueAsNumber: true,
                        })}
                    />
                </div>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="payment_date">Payment Date</Label>
                    <Input
                        id="payment_date"
                        type="date"
                        required
                        {...methods.register("payment_date")}
                    />
                </div>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="issuing_bank">Issuing Bank</Label>
                    <Input
                        id="issuing_bank"
                        type="text"
                        required
                        maxLength={150}
                        {...methods.register("issuing_bank")}
                    />
                </div>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="payment_ref">Payment Ref</Label>
                    <Input
                        id="payment_ref"
                        type="text"
                        required
                        maxLength={50}
                        {...methods.register("payment_ref")}
                    />
                </div>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="bank_ref">Bank Ref</Label>
                    <Input
                        id="bank_ref"
                        type="text"
                        required
                        maxLength={50}
                        {...methods.register("bank_ref")}
                    />
                </div>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="cc_name">Cardholder Name</Label>
                    <Input
                        id="cc_name"
                        type="text"
                        required
                        maxLength={200}
                        {...methods.register("cc_name")}
                    />
                </div>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="cc_number">Card Number</Label>
                    <Input
                        id="cc_number"
                        type="text"
                        required
                        maxLength={50}
                        {...methods.register("cc_number")}
                    />
                </div>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="payment_status">Status</Label>
                    <Input
                        id="payment_status"
                        type="number"
                        required
                        {...methods.register("payment_status", {
                            valueAsNumber: true,
                        })}
                    />
                </div>

                <div className="flex flex-end md:col-span-2 justify-end gap-2">
                    <Button
                        size={"sm"}
                        type="button"
                        variant="secondary"
                        onClick={() => history.back()}
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
