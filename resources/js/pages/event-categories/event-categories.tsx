import { Button } from "@/components/ui/button";
import { DataTable } from "@/components/datatable/data-table";
import AppLayout from "@/layouts/AppLayout";
import type { EvCategory } from "@/types";
import type { ColumnDef } from "@tanstack/react-table";
import { router } from "@inertiajs/react";
import { Pencil, Plus, Trash2 } from "lucide-react";
import { toast } from "sonner";

export const columns: ColumnDef<EvCategory>[] = [
    {
        accessorKey: "category_name",
        header: "Category",
        cell: ({ row }) => row.original.category_name,
    },
    {
        accessorKey: "is_active",
        header: "Active",
        cell: ({ row }) => (row.original.is_active ? "Yes" : "No"),
    },
    {
        accessorKey: "actions",
        header: "Actions",
        cell: ({ row }) => (
            <div className="flex items-center gap-2">
                <Button
                    size={"sm"}
                    variant="default"
                    onClick={() =>
                        router.visit(
                            route("ev_categories.edit", row.original.category_id),
                        )
                    }
                >
                    <Pencil />
                </Button>
                <Button
                    size={"sm"}
                    variant="secondary"
                    onClick={() => {
                        const ok = window.confirm(
                            "Delete this event category?",
                        );
                        if (!ok) return;

                        router.post(
                            route("ev_categories.destroy", row.original.category_id),
                            { _method: "delete" } as any,
                            {
                                onSuccess: () => {
                                    toast.success(
                                        "Event category deleted successfully",
                                    );
                                    router.reload();
                                },
                                onError: (errors: Record<string, string>) => {
                                    toast.error(
                                        "Failed to delete event category",
                                    );
                                    Object.values(errors).forEach((error) =>
                                        toast.error(error),
                                    );
                                },
                            },
                        );
                    }}
                >
                    <Trash2 />
                </Button>
            </div>
        ),
    },
];

const EventCategories = () => {
    return (
        <AppLayout>
            <div className="flex px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Event Categories
                            </h2>
                        </div>
                        <div>
                            <Button
                                variant="default"
                                onClick={() =>
                                    router.visit(
                                        route("ev_categories.create"),
                                    )
                                }
                            >
                                <Plus className="mr-2" size={20} />
                                Event Category
                            </Button>
                        </div>
                    </div>
                    <div className="mt-4">
                        <DataTable
                            columns={columns}
                            endpoint="/ev-categories/all"
                            options={{
                                showSearch: true,
                                showFilters: false,
                                showPagination: true,
                                defaultPageSize: 10,
                            }}
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
};

export default EventCategories;
