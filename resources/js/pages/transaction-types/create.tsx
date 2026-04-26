import { Button } from "@/components/ui/button";
import AppLayout from "@/layouts/AppLayout";
import { TransactionTypeForm } from "@/pages/transaction-types/transaction-type-form";
import { Head, router } from "@inertiajs/react";
import { ChevronLeft } from "lucide-react";

const CreateTransactionType = () => {
    return (
        <AppLayout>
            <Head title="Create Transaction Type" />
            <div className="flex flex-col px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div className="flex items-center gap-4">
                            <Button
                                variant="default"
                                onClick={() =>
                                    router.visit(
                                        route("transaction_types.index"),
                                    )
                                }
                            >
                                <ChevronLeft className="mr" size={20} />
                                Back
                            </Button>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Create Transaction Type
                            </h2>
                        </div>
                    </div>
                </div>

                <div className="flex-1 mt-4">
                    <TransactionTypeForm mode="create" />
                </div>
            </div>
        </AppLayout>
    );
};

export default CreateTransactionType;

