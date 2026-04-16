import { DataTable } from "@/components/datatable/data-table";
import AppLayout from "@/layouts/AppLayout";
import type { ColumnDef } from "@tanstack/react-table";
import { format } from "date-fns";

type InterestListRow = {
    user_interest_list_id: string;
    email: string;
    referral_code: string;
    created_at?: string | null;
};

export const columns: ColumnDef<InterestListRow>[] = [
    {
        accessorKey: "email",
        header: "Email",
        cell: ({ row }) => row.original.email,
    },
    {
        accessorKey: "referral_code",
        header: "Referral Code",
        cell: ({ row }) => row.original.referral_code,
    },
    {
        accessorKey: "created_at",
        header: "Created At",
        cell: ({ row }) =>
            row.original.created_at
                ? format(new Date(row.original.created_at), "PPP HH:mm")
                : "-",
    },
];

const UserInterestList = () => {
    return (
        <AppLayout>
            <div className="flex px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                User Interest List
                            </h2>
                        </div>
                    </div>
                    <div className="mt-4">
                        <DataTable
                            columns={columns}
                            endpoint="/user-interest-list/all"
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

export default UserInterestList;
