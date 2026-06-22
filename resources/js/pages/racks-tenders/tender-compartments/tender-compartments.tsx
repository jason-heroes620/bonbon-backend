import { DataTable } from "@/components/datatable/data-table";
import { Button } from "@/components/ui/button";
import AppLayout from "@/layouts/AppLayout";
import type { TenderCompartment } from "@/types";
import type { ColumnDef } from "@tanstack/react-table";
import { router } from "@inertiajs/react";
import { Pencil, Plus, Trash2 } from "lucide-react";

type Props = {
    rack?: { rack_id: string; rack_name: string } | null;
};

export const columns: ColumnDef<TenderCompartment>[] = [
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
        accessorKey: "compartment_label",
        header: "Compartment",
        cell: ({ row }) => row.original.compartment_label ?? "-",
    },
    {
        accessorKey: "vendor_name",
        header: "Vendor",
        cell: ({ row }) => row.original.vendor_name ?? "-",
    },
    {
        accessorKey: "bid_price",
        header: "Bid Price",
        cell: ({ row }) => row.original.bid_price ?? "-",
    },
    {
        accessorKey: "durations",
        header: "Months",
        cell: ({ row }) => row.original.durations ?? "-",
    },
    {
        accessorKey: "tender_status",
        header: "Status",
        cell: ({ row }) => row.original.tender_status ?? "-",
    },
    {
        accessorKey: "selected_at",
        header: "Selected At",
        cell: ({ row }) => row.original.selected_at ?? "-",
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
                                "tender_compartments.edit",
                                row.original.tender_compartment_id,
                            ),
                        )
                    }
                >
                    <Pencil />
                </Button>
                <Button
                    size={"sm"}
                    variant="destructive"
                    onClick={() => {
                        const ok = window.confirm(
                            "Delete this tender compartment? This cannot be undone.",
                        );
                        if (!ok) return;
                        router.delete(
                            route(
                                "tender_compartments.destroy",
                                row.original.tender_compartment_id,
                            ),
                            { preserveScroll: true },
                        );
                    }}
                >
                    <Trash2 />
                </Button>
            </div>
        ),
    },
];

export default function TenderCompartmentsIndex({ rack }: Props) {
    const endpoint =
        rack?.rack_id != null && rack.rack_id !== ""
            ? `/tender-compartments/all?rack_id=${rack.rack_id}`
            : "/tender-compartments/all";

    return (
        <AppLayout>
            <div className="flex px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Tender Compartments
                            </h2>
                            {rack ? (
                                <div className="text-sm text-gray-700">
                                    {rack.rack_name}
                                </div>
                            ) : null}
                        </div>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="default"
                                onClick={() =>
                                    router.visit(route("tender_compartments.create"))
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
                            endpoint={endpoint}
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
