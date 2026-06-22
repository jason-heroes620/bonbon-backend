import AppLayout from "@/layouts/AppLayout";
import { Head, router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { ChevronLeft } from "lucide-react";
import type { Compartment, Rack } from "@/types";
import {
    TenderCompartmentForm,
    type Option,
} from "./tender-compartment-form";

export default function CreateTenderCompartment({
    vendors,
    vendorLocations,
    racks,
    compartments,
}: {
    vendors: Option[];
    vendorLocations: Option[];
    racks: Rack[];
    compartments: Compartment[];
}) {
    return (
        <AppLayout>
            <Head title="Create Tender Compartment" />
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
                                Create Tender Compartment
                            </h2>
                        </div>
                    </div>
                </div>
                <div className="mt-4">
                    <TenderCompartmentForm
                        mode="create"
                        vendors={vendors}
                        vendorLocations={vendorLocations}
                        racks={racks}
                        compartments={compartments}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
