import { DataTable } from "@/components/datatable/data-table";
import { Button } from "@/components/ui/button";
import AppLayout from "@/layouts/AppLayout";
import type { Rack } from "@/types";
import type { ColumnDef } from "@tanstack/react-table";
import { router } from "@inertiajs/react";
import { LayoutGrid, Pencil, Plus, Trash2 } from "lucide-react";

export const columns: ColumnDef<Rack>[] = [
    {
        accessorKey: "rack_name",
        header: "Rack",
        cell: ({ row }) => row.original.rack_name,
    },
    {
        accessorKey: "vendor_name",
        header: "Vendor",
        cell: ({ row }) => row.original.vendor_name ?? "-",
    },
    {
        accessorKey: "vendor_location_name",
        header: "Location",
        cell: ({ row }) => row.original.vendor_location_name ?? "-",
    },
    {
        accessorKey: "rack_rows",
        header: "Rows",
        cell: ({ row }) => row.original.rack_rows ?? "-",
    },
    {
        accessorKey: "rack_columns",
        header: "Columns",
        cell: ({ row }) => row.original.rack_columns ?? "-",
    },
    {
        accessorKey: "rack_status",
        header: "Status",
        cell: ({ row }) => row.original.rack_status ?? "-",
    },
    {
        accessorKey: "actions",
        header: "Actions",
        cell: ({ row }) => (
            <div className="flex items-center gap-2">
                <Button
                    size={"sm"}
                    variant="outline"
                    onClick={() =>
                        router.visit(
                            route(
                                "racks.compartments.edit",
                                row.original.rack_id,
                            ),
                        )
                    }
                >
                    <LayoutGrid />
                </Button>
                <Button
                    size={"sm"}
                    variant="default"
                    onClick={() =>
                        router.visit(route("racks.edit", row.original.rack_id))
                    }
                >
                    <Pencil />
                </Button>
                <Button
                    size={"sm"}
                    variant="destructive"
                    onClick={() => {
                        const ok = window.confirm(
                            "Delete this rack? This cannot be undone.",
                        );
                        if (!ok) return;
                        router.delete(
                            route("racks.destroy", row.original.rack_id),
                            {
                                preserveScroll: true,
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

const Racks = () => {
    return (
        <AppLayout>
            <div className="flex px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Racks
                            </h2>
                        </div>
                        <div>
                            <Button
                                variant="default"
                                onClick={() =>
                                    router.visit(route("racks.create"))
                                }
                            >
                                <Plus className="mr-2" size={20} />
                                Rack
                            </Button>
                        </div>
                    </div>
                    <div className="mt-4">
                        <DataTable
                            columns={columns}
                            endpoint="/racks/all"
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

export default Racks;
