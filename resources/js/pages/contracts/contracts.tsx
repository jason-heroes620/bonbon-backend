import { Button } from "@/components/ui/button";
import { DataTable } from "@/components/datatable/data-table";
import AppLayout from "@/layouts/AppLayout";
import type { Contract } from "@/types";
import type { ColumnDef } from "@tanstack/react-table";
import { router } from "@inertiajs/react";
import { format } from "date-fns";
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from "@/components/ui/tooltip";
import { TriangleAlert } from "lucide-react";

export const columns: ColumnDef<Contract>[] = [
    {
        accessorKey: "rack_name",
        header: "Rack",
        cell: ({ row }) => row.original.rack_name,
    },
    {
        accessorKey: "location_name",
        header: "Location",
        cell: ({ row }) => row.original.location_name,
    },
    {
        accessorKey: "compartment_label",
        header: "Compartment",
        cell: ({ row }) => row.original.compartment_label,
    },
    {
        accessorKey: "tender_start_date",
        header: "Tender Start Date",
        cell: ({ row }) =>
            row.original.tender_start_date
                ? format(row.original.tender_start_date, "d MMM, y")
                : "-",
    },
    {
        accessorKey: "tender_end_date",
        header: "Tender End Date",
        cell: ({ row }) =>
            row.original.tender_end_date
                ? format(row.original.tender_end_date, "d MMM, y")
                : "-",
    },
    {
        accessorKey: "tender_status",
        header: "Status",
        cell: ({ row }) =>
            row.original.unallocated_reason !== null ? (
                <div className="flex items-center ">
                    {row.original.tender_status}
                    <Tooltip>
                        <TooltipTrigger className="pl-2 cursor-pointer">
                            <TriangleAlert size={18} className="text-red-600" />
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>{row.original.unallocated_reason}</p>
                        </TooltipContent>
                    </Tooltip>
                </div>
            ) : (
                row.original.tender_status
            ),
    },
    {
        accessorKey: "actions",
        header: "Actions",
        cell: ({ row }) => (
            <Button
                variant="default"
                onClick={() =>
                    router.visit(
                        route(
                            "contracts.show",
                            row.original.tender_compartment_id,
                        ),
                    )
                }
            >
                View
            </Button>
        ),
    },
];

export default function ContractsIndex() {
    return (
        <AppLayout>
            <div className="flex px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                My Contracts
                            </h2>
                        </div>
                    </div>
                    <div className="mt-4">
                        <DataTable
                            columns={columns}
                            endpoint="/contracts/all"
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
}
