import { Button } from "@/components/ui/button";
import AppLayout from "@/layouts/AppLayout";
import { EventCategoryForm } from "@/pages/event-categories/event-category-form";
import type { EvCategory } from "@/types";
import { Head, router } from "@inertiajs/react";
import { ChevronLeft } from "lucide-react";

const EditEventCategory = ({ evCategory }: { evCategory: EvCategory }) => {
    return (
        <AppLayout>
            <Head title="Edit Event Category" />
            <div className="flex flex-col px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div className="flex items-center gap-4">
                            <Button
                                variant="default"
                                onClick={() =>
                                    router.visit(route("ev_categories.index"))
                                }
                            >
                                <ChevronLeft className="mr" size={20} />
                                Back
                            </Button>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Edit Event Category
                            </h2>
                        </div>
                    </div>
                </div>

                <div className="flex-1 mt-4">
                    <EventCategoryForm mode="edit" evCategory={evCategory} />
                </div>
            </div>
        </AppLayout>
    );
};

export default EditEventCategory;
