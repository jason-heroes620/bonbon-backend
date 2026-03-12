import { Button } from "@/components/ui/button";
import AppLayout from "@/layouts/AppLayout";
import type { Category } from "@/types";
import { type Option, CategoryForm } from "@/pages/categories/category-form";
import { Head, router } from "@inertiajs/react";
import { ChevronLeft } from "lucide-react";

const EditCategory = ({
    category,
    parentCategories,
}: {
    category: Category;
    parentCategories: Option[];
}) => {
    return (
        <AppLayout>
            <Head title="Edit Category" />
            <div className="flex flex-col px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div className="flex items-center gap-4">
                            <Button
                                variant="default"
                                onClick={() =>
                                    router.visit(route("categories.index"))
                                }
                            >
                                <ChevronLeft className="mr" size={20} />
                                Back
                            </Button>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Edit Category
                            </h2>
                        </div>
                    </div>
                </div>

                <div className="flex-1 mt-4">
                    <CategoryForm
                        mode="edit"
                        category={category}
                        parentCategories={parentCategories}
                    />
                </div>
            </div>
        </AppLayout>
    );
};

export default EditCategory;
