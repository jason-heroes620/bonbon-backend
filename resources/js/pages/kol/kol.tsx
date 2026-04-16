import { Button } from "@/components/ui/button";
import AppLayout from "@/layouts/AppLayout";
import type { ColumnDef } from "@tanstack/react-table";
import { DataTable } from "@/components/datatable/data-table";
import { router } from "@inertiajs/react";
import { Eye } from "lucide-react";
import { format } from "date-fns";

type KolRow = {
    user_id: string;
    first_name: string;
    last_name: string;
    email: string;
    contact_no?: string | null;
    membership_end_date?: string | null;
};

export const columns: ColumnDef<KolRow>[] = [
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
        cell: ({ row }) => row.original.contact_no ?? "-",
    },
    {
        accessorKey: "membership_end_date",
        header: "Membership End",
        cell: ({ row }) =>
            row.original.membership_end_date
                ? format(new Date(row.original.membership_end_date), "PPP")
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
                        router.visit(route("kol.show", row.original.user_id))
                    }
                >
                    <Eye />
                </Button>
            </div>
        ),
    },
];

export default function Kol() {
    return (
        <AppLayout>
            <div className="flex px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                KOL
                            </h2>
                        </div>
                    </div>
                    <div className="mt-4">
                        <DataTable
                            columns={columns}
                            endpoint="/kol/all"
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

