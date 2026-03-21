import { Button } from "@/components/ui/button";
import AppLayout from "@/layouts/AppLayout";
import { DiscountForm } from "@/pages/discounts/discount-form";
import { Head, router } from "@inertiajs/react";
import { ChevronLeft } from "lucide-react";

const CreateDiscount = () => {
    return (
        <AppLayout>
            <Head title="Create Discount" />
            <div className="flex flex-col px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div className="flex items-center gap-4">
                            <Button
                                variant="default"
                                onClick={() =>
                                    router.visit(route("discounts.index"))
                                }
                            >
                                <ChevronLeft className="mr-2" size={20} />
                                Back
                            </Button>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Create Discount
                            </h2>
                        </div>
                    </div>
                </div>

                <div className="flex-1 mt-4">
                    <DiscountForm mode="create" />
                </div>
            </div>
        </AppLayout>
    );
};

export default CreateDiscount;
