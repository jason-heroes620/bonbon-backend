import { Button } from "@/components/ui/button";
import { type Option, ProductForm } from "@/pages/products/product-form";
import type { VendorLocationOption } from "@/types";
import AppLayout from "@/layouts/AppLayout";
import { Head, router } from "@inertiajs/react";
import { ChevronLeft } from "lucide-react";

const CreateProduct = ({
    categories,
    taxRates,
    vendorLocations,
}: {
    categories: Option[];
    taxRates: Option[];
    vendorLocations: VendorLocationOption[];
}) => {
    return (
        <AppLayout>
            <Head title="Create Product" />
            <div className="flex flex-col px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div className="flex items-center gap-4">
                            <Button
                                variant="default"
                                onClick={() =>
                                    router.visit(route("products.index"))
                                }
                            >
                                <ChevronLeft className="mr" size={20} />
                                Back
                            </Button>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Create Product
                            </h2>
                        </div>
                    </div>
                </div>

                <div className="flex-1 mt-4">
                    <ProductForm
                        mode="create"
                        categories={categories}
                        taxRates={taxRates}
                        vendorLocations={vendorLocations}
                    />
                </div>
            </div>
        </AppLayout>
    );
};

export default CreateProduct;
