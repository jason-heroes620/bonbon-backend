import AppLayout from "@/layouts/AppLayout";
import { Button } from "@/components/ui/button";
import { router } from "@inertiajs/react";
import { DataTable } from "@/components/datatable/data-table";
import type { ColumnDef } from "@tanstack/react-table";
import type { Discount } from "@/types";
import { Pencil, Plus } from "lucide-react";
import { format } from "date-fns";

export const columns: ColumnDef<Discount>[] = [
    {
        accessorKey: "discount_code",
        header: "Code",
        cell: ({ row }) => row.original.discount_code,
    },
    {
        accessorKey: "discount_name",
        header: "Name",
        cell: ({ row }) => row.original.discount_name,
    },
    {
        accessorKey: "discount_type",
        header: "Type",
        cell: ({ row }) =>
            row.original.discount_type === "P" ? "Percentage" : "Fixed Amount",
    },
    {
        accessorKey: "discount_amount",
        header: "Amount",
        cell: ({ row }) => row.original.discount_amount,
    },
    {
        accessorKey: "discount_start_date",
        header: "Start",
        cell: ({ row }) =>
            row.original.discount_start_date
                ? format(row.original.discount_start_date as any, "PPP")
                : "-",
    },
    {
        accessorKey: "discount_end_date",
        header: "End",
        cell: ({ row }) =>
            row.original.discount_end_date
                ? format(row.original.discount_end_date as any, "PPP")
                : "-",
    },
    {
        accessorKey: "is_active",
        header: "Status",
        cell: ({ row }) => (row.original.is_active ? "Active" : "Inactive"),
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
                            route("discounts.edit", row.original.discount_id),
                        )
                    }
                >
                    <Pencil />
                </Button>
            </div>
        ),
    },
];

const Discounts = () => {
    return (
        <AppLayout>
            <div className="flex px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Discounts
                            </h2>
                        </div>
                        <div>
                            <Button
                                variant="default"
                                onClick={() =>
                                    router.visit(route("discounts.create"))
                                }
                            >
                                <Plus className="mr" size={20} />
                                Discount
                            </Button>
                        </div>
                    </div>
                    <div className="mt-4">
                        <DataTable
                            columns={columns}
                            endpoint="/discounts/all"
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

export default Discounts;
