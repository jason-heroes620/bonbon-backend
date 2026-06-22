import { DataTable } from "@/components/datatable/data-table";
import { Button } from "@/components/ui/button";
import AppLayout from "@/layouts/AppLayout";
import type { RackAvailability } from "@/types";
import type { ColumnDef } from "@tanstack/react-table";
import { router } from "@inertiajs/react";

export const columns: ColumnDef<RackAvailability>[] = [
    {
        accessorKey: "vendor_location_name",
        header: "Vendor Location",
        cell: ({ row }) => row.original.vendor_location_name ?? "-",
    },
    {
        accessorKey: "rack_name",
        header: "Rack",
        cell: ({ row }) => row.original.rack_name ?? "-",
    },
    {
        accessorKey: "open_compartments_count",
        header: "Open Compartments",
        cell: ({ row }) => row.original.open_compartments_count ?? 0,
    },
    {
        accessorKey: "rack_status",
        header: "Rack Status",
        cell: ({ row }) => row.original.rack_status ?? "-",
    },
    {
        accessorKey: "actions",
        header: "Actions",
        cell: ({ row }) => (
            <Button
                variant="default"
                onClick={() =>
                    router.visit(
                        route("tenders-summary.show", row.original.rack_id),
                    )
                }
            >
                View
            </Button>
        ),
    },
];

export default function TenderSummary() {
    return (
        <AppLayout>
            <div className="flex px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Tender Summary
                            </h2>
                        </div>
                    </div>
                    <div className="mt-4">
                        <DataTable
                            columns={columns}
                            endpoint="/tenders-summary/all"
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
