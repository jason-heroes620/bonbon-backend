import { Button } from "@/components/ui/button";
import AppLayout from "@/layouts/AppLayout";
import type { ColumnDef } from "@tanstack/react-table";
import type { User } from "@/types";
import { Pencil } from "lucide-react";
import { DataTable } from "@/components/datatable/data-table";
import { router } from "@inertiajs/react";

export const columns: ColumnDef<User>[] = [
    {
        accessorKey: "name",
        header: "Name",
        cell: ({ row }) =>
            `${row.original.first_name} ${row.original.last_name}`,
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
        accessorKey: "is_active",
        header: "Status",
        cell: ({ row }) =>
            row.original.is_active === true ? "Active" : "Inactive",
    },
    {
        accessorKey: "role",
        header: "Role",
        cell: ({ row }) => row.original.role.toLocaleUpperCase(),
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
                        router.visit(route("users.edit", row.original.user_id))
                    }
                >
                    <Pencil />
                </Button>
            </div>
        ),
    },
];

const Users = () => {
    return (
        <AppLayout>
            <div className="flex px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Users
                            </h2>
                        </div>
                        {/* <div>
                            <Button
                                variant="default"
                                onClick={() =>
                                    router.visit(route("users.create"))
                                }
                            >
                                <Plus className="mr-2" size={20} />
                                User
                            </Button>
                        </div> */}
                    </div>
                    <div className="mt-4">
                        <div>
                            <DataTable
                                columns={columns}
                                endpoint="/users/all"
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

export default Users;
