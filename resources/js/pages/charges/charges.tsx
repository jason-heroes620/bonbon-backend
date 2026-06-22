import { Button } from "@/components/ui/button";
import { DataTable } from "@/components/datatable/data-table";
import AppLayout from "@/layouts/AppLayout";
import type { Charge } from "@/types";
import type { ColumnDef } from "@tanstack/react-table";
import { router } from "@inertiajs/react";
import { Pencil, Plus, Trash2 } from "lucide-react";
import { format } from "date-fns";

export const columns: ColumnDef<Charge>[] = [
    {
        accessorKey: "charges_name",
        header: "Name",
        cell: ({ row }) => row.original.charges_name,
    },
    {
        accessorKey: "charges_type",
        header: "Type",
        cell: ({ row }) => row.original.charges_type,
    },
    {
        accessorKey: "charges_rate",
        header: "Rate",
        cell: ({ row }) => row.original.charges_rate,
    },
    {
        accessorKey: "charges_status",
        header: "Status",
        cell: ({ row }) =>
            row.original.charges_status ? "Active" : "Inactive",
    },
    {
        accessorKey: "charges_start_date",
        header: "Start Date",
        cell: ({ row }) => format(row.original.charges_start_date, "d MMM, y"),
    },
    {
        accessorKey: "charges_end_date",
        header: "End Date",
        cell: ({ row }) =>
            row.original.charges_end_date
                ? format(row.original.charges_end_date, "d MMM, y")
                : "-",
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
                            route("charges.edit", row.original.charges_id),
                        )
                    }
                >
                    <Pencil />
                </Button>
            </div>
        ),
    },
];

const Charges = () => {
    return (
        <AppLayout>
            <div className="flex px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Charges
                            </h2>
                        </div>
                        <div>
                            <Button
                                variant="default"
                                onClick={() =>
                                    router.visit(route("charges.create"))
                                }
                            >
                                <Plus className="mr-2" size={20} />
                                Charge
                            </Button>
                        </div>
                    </div>
                    <div className="mt-4">
                        <DataTable
                            columns={columns}
                            endpoint="/charges/all"
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

export default Charges;
