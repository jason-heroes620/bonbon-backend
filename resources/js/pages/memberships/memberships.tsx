import { Button } from "@/components/ui/button";
import { DataTable } from "@/components/datatable/data-table";
import AppLayout from "@/layouts/AppLayout";
import type { Membership } from "@/types";
import type { ColumnDef } from "@tanstack/react-table";
import { router } from "@inertiajs/react";
import { Pencil, Plus } from "lucide-react";

export const columns: ColumnDef<Membership>[] = [
    {
        accessorKey: "membership_name",
        header: "Name",
        cell: ({ row }) => row.original.membership_name,
    },
    {
        accessorKey: "membership_type",
        header: "Type",
        cell: ({ row }) => row.original.membership_type,
    },
    {
        accessorKey: "membership_price",
        header: "Price",
        cell: ({ row }) => row.original.membership_price,
    },
    {
        accessorKey: "duration",
        header: "Duration",
        cell: ({ row }) =>
            `${row.original.duration} ${row.original.duration_unit}`,
    },
    {
        accessorKey: "is_active",
        header: "Status",
        cell: ({ row }) => (row.original.is_active ? "Active" : "Inactive"),
    },
    {
        accessorKey: "sort_order",
        header: "Sort",
        cell: ({ row }) => row.original.sort_order,
    },
    {
        accessorKey: "best_value",
        header: "Best Value",
        cell: ({ row }) => (row.original.best_value ? "Yes" : "No"),
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
                            route(
                                "memberships.edit",
                                row.original.membership_id,
                            ),
                        )
                    }
                >
                    <Pencil />
                </Button>
            </div>
        ),
    },
];

const Memberships = () => {
    return (
        <AppLayout>
            <div className="flex px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Memberships
                            </h2>
                        </div>
                        <div>
                            <Button
                                variant="default"
                                onClick={() =>
                                    router.visit(route("memberships.create"))
                                }
                            >
                                <Plus className="mr-2" size={20} />
                                Membership
                            </Button>
                        </div>
                    </div>
                    <div className="mt-4">
                        <DataTable
                            columns={columns}
                            endpoint="/memberships/all"
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

export default Memberships;
