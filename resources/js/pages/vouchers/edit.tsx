import { Head, router, usePage } from "@inertiajs/react";
import AppLayout from "@/layouts/AppLayout";
import {
    VoucherForm,
    type VoucherFormValues,
} from "@/components/vouchers/voucher-form";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { ChevronLeft } from "lucide-react";

interface EditVoucherProps {
    voucher: any;
}

const EditVoucher = ({ voucher }: EditVoucherProps) => {
    const page = usePage();
    const role = (page.props as any)?.auth?.user?.role as string | undefined;
    const canEdit = role === "admin";
    const defaultValues: Partial<VoucherFormValues> = {
        ...voucher,
        vendor_id: voucher.vendor_id || undefined,
        voucher_short_description:
            voucher.voucher_short_description || undefined,
        voucher_description: voucher.voucher_description || undefined,
        duration: voucher.duration || undefined,
        what_you_get: voucher.what_you_get || undefined,
        voucher_type: voucher.voucher_type || undefined,
        voucher_start_date: voucher.voucher_start_date
            ? new Date(voucher.voucher_start_date)
            : undefined,
        voucher_expiry_date: voucher.voucher_expiry_date
            ? new Date(voucher.voucher_expiry_date)
            : undefined,
        voucher_discount:
            voucher.voucher_discount !== null
                ? Number(voucher.voucher_discount)
                : undefined,
        voucher_limit:
            voucher.voucher_limit !== null ? Number(voucher.voucher_limit) : 0,
        voucher_claim_per_user:
            voucher.voucher_claim_per_user !== null
                ? Number(voucher.voucher_claim_per_user)
                : 1,
        voucher_claim_period: voucher.voucher_claim_period || undefined,
        voucher_claim_per_period:
            voucher.voucher_claim_per_period !== null &&
            voucher.voucher_claim_per_period !== undefined
                ? Number(voucher.voucher_claim_per_period)
                : undefined,
        membership_ids: Array.isArray(voucher.membership_ids)
            ? voucher.membership_ids
            : [],
        categories: Array.isArray(voucher.categories) ? voucher.categories : [],
        voucher_status: Boolean(voucher.voucher_status),
    };

    const handleSubmit = (data: VoucherFormValues) => {
        router.post(
            `/vouchers/${voucher.voucher_id}`,
            {
                _method: "put",
                ...data,
            } as any,
            {
                forceFormData: true,
                onSuccess: () => {
                    toast.success("Voucher updated successfully");
                },
                onError: (errors) => {
                    console.error(errors);
                    toast.error("Failed to update voucher");
                },
            },
        );
    };

    return (
        <AppLayout>
            <Head title="Edit Voucher" />
            <div className="flex flex-col px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div className="flex items-center gap-4">
                            <Button
                                variant="default"
                                onClick={() =>
                                    router.visit(route("vouchers.index"))
                                }
                            >
                                <ChevronLeft className="mr" size={20} />
                                Back
                            </Button>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Edit Voucher
                            </h2>
                        </div>
                    </div>
                </div>
                <div className="mt-4">
                    <VoucherForm
                        key={voucher.voucher_id}
                        onSubmit={handleSubmit}
                        defaultValues={defaultValues}
                        existingImageUrl={voucher.voucher_image_path}
                        existingVoucherImages={
                            (voucher as any).voucher_images ?? []
                        }
                        existingImageUrlPortrait={
                            voucher.voucher_image_portrait_path
                        }
                        isEdit={true}
                        canEdit={canEdit}
                    />
                </div>
            </div>
        </AppLayout>
    );
};

export default EditVoucher;
