import AppLayout from "@/layouts/AppLayout";
import { Head, router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { ChevronLeft } from "lucide-react";
import { RackForm, type Option } from "./rack-form";

export default function CreateRack({
    vendorLocations,
}: {
    vendorLocations: Option[];
}) {
    return (
        <AppLayout>
            <Head title="Create Rack" />
            <div className="flex flex-col px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div className="flex items-center gap-4">
                            <Button
                                variant="default"
                                onClick={() => router.visit(route("racks.index"))}
                            >
                                <ChevronLeft className="mr-2" size={20} />
                                Back
                            </Button>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Create Rack
                            </h2>
                        </div>
                    </div>
                </div>
                <div className="mt-4">
                    <RackForm
                        mode="create"
                        vendorLocations={vendorLocations}
                    />
                </div>
            </div>
        </AppLayout>
    );
}

