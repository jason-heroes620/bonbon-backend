import { Button } from "@/components/ui/button";
import { DataTable } from "@/components/datatable/data-table";
import AppLayout from "@/layouts/AppLayout";
import type { TransactionType } from "@/types";
import type { ColumnDef } from "@tanstack/react-table";
import { router } from "@inertiajs/react";
import { Pencil, Plus } from "lucide-react";
import { format } from "date-fns";

export const columns: ColumnDef<TransactionType>[] = [
    {
        accessorKey: "transaction_type",
        header: "Type",
        cell: ({ row }) => row.original.transaction_type,
    },
    {
        accessorKey: "transaction_name",
        header: "Name",
        cell: ({ row }) => row.original.transaction_name,
    },
    {
        accessorKey: "credit_amount",
        header: "Credit Amount",
        cell: ({ row }) => row.original.credit_amount,
    },
    {
        accessorKey: "effective_date",
        header: "Effective",
        cell: ({ row }) => format(row.original.effective_date, "d MMM yyyy"),
    },
    {
        accessorKey: "expire_date",
        header: "Expire",
        cell: ({ row }) =>
            row.original.expire_date
                ? format(row.original.expire_date, "d MMM yyyy")
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
                            route("transaction_types.edit", row.original.id),
                        )
                    }
                >
                    <Pencil />
                </Button>
            </div>
        ),
    },
];

const TransactionTypes = () => {
    return (
        <AppLayout>
            <div className="flex px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Transaction Types
                            </h2>
                        </div>
                        <div>
                            <Button
                                variant="default"
                                onClick={() =>
                                    router.visit(
                                        route("transaction_types.create"),
                                    )
                                }
                            >
                                <Plus className="mr-2" size={20} />
                                Transaction Type
                            </Button>
                        </div>
                    </div>
                    <div className="mt-4">
                        <DataTable
                            columns={columns}
                            endpoint="/transaction-types/all"
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

export default TransactionTypes;
