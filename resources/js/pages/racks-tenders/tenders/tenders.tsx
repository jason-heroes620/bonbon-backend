import { DataTable } from "@/components/datatable/data-table";
import { Button } from "@/components/ui/button";
import AppLayout from "@/layouts/AppLayout";
import type { Tender } from "@/types";
import type { ColumnDef } from "@tanstack/react-table";
import { router } from "@inertiajs/react";
import { Pencil, Plus } from "lucide-react";

export const columns: ColumnDef<Tender>[] = [
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
        accessorKey: "tender_status",
        header: "Status",
        cell: ({ row }) => row.original.tender_status ?? "-",
    },
    {
        accessorKey: "actions",
        header: "Actions",
        cell: ({ row }) => (
            <div className="flex items-center gap-2">
                <Button
                    variant="default"
                    onClick={() =>
                        router.visit(
                            route("tenders.edit", row.original.tender_id),
                        )
                    }
                >
                    <Pencil />
                </Button>
            </div>
        ),
    },
];

const Tenders = () => {
    return (
        <AppLayout>
            <div className="flex px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Tenders
                            </h2>
                        </div>
                        <div>
                            <Button
                                variant="default"
                                onClick={() =>
                                    router.visit(route("tenders.create"))
                                }
                            >
                                <Plus className="mr-2" size={20} />
                                Tender
                            </Button>
                        </div>
                    </div>
                    <div className="mt-4">
                        <DataTable
                            columns={columns}
                            endpoint="/tenders/all"
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

export default Tenders;
