import AppLayout from "@/layouts/AppLayout";
import { Button } from "@/components/ui/button";
import { router } from "@inertiajs/react";
import { DataTable } from "@/components/datatable/data-table";
import type { ColumnDef } from "@tanstack/react-table";
import type { Vendor } from "@/types";
import { Pencil, Plus } from "lucide-react";
import { usePage } from "@inertiajs/react";

export const columns: ColumnDef<Vendor>[] = [
    {
        accessorKey: "vendor_name",
        header: "Vendor Name",
        cell: ({ row }) => row.original.vendor_name,
    },
    {
        accessorKey: "email",
        header: "Email",
        cell: ({ row }) => row.original.email,
    },
    {
        accessorKey: "contact_no",
        header: "Contact No",
        cell: ({ row }) => row.original.contact_no,
    },
    {
        accessorKey: "contact_person",
        header: "Contact Person",
        cell: ({ row }) =>
            row.original.first_name + " " + row.original.last_name,
    },
    {
        accessorKey: "is_active",
        header: "Is Active",
        cell: ({ row }) => row.original.is_active.toLocaleUpperCase(),
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
                            route("vendors.edit", row.original.vendor_id),
                        )
                    }
                >
                    <Pencil />
                </Button>
            </div>
        ),
    },
];

const Vendors = () => {
    const page = usePage();
    const role = (page.props as any)?.auth?.user?.role as string | undefined;
    const canCreate = role === "admin";

    return (
        <AppLayout>
            <div className="flex px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Vendors
                            </h2>
                        </div>
                        <div>
                            {canCreate ? (
                                <Button
                                    variant="default"
                                    onClick={() =>
                                        router.visit(route("vendors.create"))
                                    }
                                >
                                    <Plus className="mr" size={20} />
                                    Vendor
                                </Button>
                            ) : null}
                        </div>
                    </div>
                    <div className="mt-4">
                        <div>
                            <DataTable
                                columns={columns}
                                endpoint="/vendors/all"
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

export default Vendors;
