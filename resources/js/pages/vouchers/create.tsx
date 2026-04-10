import {
    VoucherForm,
    type VoucherFormValues,
} from "@/components/vouchers/voucher-form";
import AppLayout from "@/layouts/AppLayout";
import type { User } from "@/types";
import { Head, router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { ChevronLeft } from "lucide-react";

const Create = ({ user }: { user: User }) => {
    const handleSubmit = (values: VoucherFormValues) => {
        router.post(route("vouchers.store"), values as any, {
            forceFormData: true,
        });
    };

    return (
        <AppLayout>
            <Head title="Create Voucher" />
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
                                <ChevronLeft className="mr-2" size={20} />
                                Back
                            </Button>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Create Voucher
                            </h2>
                        </div>
                    </div>
                </div>
                <div className="mt-4">
                    <VoucherForm
                        onSubmit={handleSubmit}
                        canEdit={user.role === "admin" ? true : false}
                    />
                </div>
            </div>
        </AppLayout>
    );
};

export default Create;
