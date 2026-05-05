import AppLayout from "@/layouts/AppLayout";
import { Button } from "@/components/ui/button";
import { router, usePage } from "@inertiajs/react";
import { DataTable } from "@/components/datatable/data-table";
import type { ColumnDef } from "@tanstack/react-table";
import type { Voucher } from "@/types";
import { Download, Pencil, Plus } from "lucide-react";
import { format } from "date-fns";

export const columns: ColumnDef<Voucher>[] = [
    {
        accessorKey: "vendor_name",
        header: "Vendor Name",
        cell: ({ row }) => row.original.vendor_name,
    },
    {
        accessorKey: "voucher_name",
        header: "Voucher Name",
        cell: ({ row }) => row.original.voucher_name,
    },
    {
        accessorKey: "voucher_start_date",
        header: "Start Date",
        cell: ({ row }) => format(row.original.voucher_start_date, "PPP"),
    },
    {
        accessorKey: "voucher_expiry_date",
        header: "Expiry Date",
        cell: ({ row }) => format(row.original.voucher_expiry_date, "PPP"),
    },
    {
        accessorKey: "voucher_status",
        header: "Status",
        cell: ({ row }) =>
            row.original.voucher_status ? "Active" : "Inactive",
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
                            route("vouchers.edit", row.original.voucher_id),
                        )
                    }
                >
                    <Pencil />
                </Button>
            </div>
        ),
    },
];

const vouchers = () => {
    const page = usePage();
    const role = (page.props as any)?.auth?.user?.role as string | undefined;
    const canCreate = role === "admin";
    const canExport = role === "admin" || role === "vendor";
    return (
        <AppLayout>
            <div className="flex px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Vouchers
                            </h2>
                        </div>
                        <div>
                            {canExport ? (
                                <Button
                                    variant="secondary"
                                    className="mr-2"
                                    onClick={() => {
                                        window.location.assign(
                                            route("vouchers.export"),
                                        );
                                    }}
                                >
                                    <Download className="mr" size={20} />
                                    Export
                                </Button>
                            ) : null}
                            {canCreate ? (
                                <Button
                                    variant="default"
                                    onClick={() =>
                                        router.visit(route("vouchers.create"))
                                    }
                                >
                                    <Plus className="mr" size={20} />
                                    Voucher
                                </Button>
                            ) : null}
                        </div>
                    </div>
                    <div className="mt-4">
                        <div>
                            <DataTable
                                columns={columns}
                                endpoint="/vouchers/all"
                                options={{
                                    showSearch: true,
                                    showFilters: true,
                                    showPagination: true,
                                    defaultPageSize: 10,
                                }}
                            />
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
};

export default vouchers;
