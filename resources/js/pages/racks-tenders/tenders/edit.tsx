import AppLayout from "@/layouts/AppLayout";
import type { Rack, Tender } from "@/types";
import { Head, router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { ChevronLeft } from "lucide-react";
import { TenderForm, type Option } from "./tender-form";

export default function EditTender({
    tender,
    vendorLocations,
    racks,
}: {
    tender: Tender;
    vendorLocations: Option[];
    racks: Rack[];
}) {
    return (
        <AppLayout>
            <Head title="Edit Tender" />
            <div className="flex flex-col px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div className="flex items-center gap-4">
                            <Button
                                variant="default"
                                onClick={() =>
                                    router.visit(route("tenders.index"))
                                }
                            >
                                <ChevronLeft className="mr-2" size={20} />
                                Back
                            </Button>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Edit Tender
                            </h2>
                        </div>
                    </div>
                </div>
                <div className="mt-4">
                    <TenderForm
                        mode="edit"
                        tender={tender}
                        vendorLocations={vendorLocations}
                        racks={racks}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
