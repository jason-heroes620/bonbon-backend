import AppLayout from "@/layouts/AppLayout";
import { DataTable } from "@/components/datatable/data-table";
import type { ReferralByUser } from "@/types";
import type { ColumnDef } from "@tanstack/react-table";
import { format } from "date-fns";

export const columns: ColumnDef<ReferralByUser>[] = [
    {
        accessorKey: "first_name",
        header: "User",
        cell: ({ row }) =>
            `${row.original.first_name} ${row.original.last_name}`,
    },
    {
        accessorKey: "email",
        header: "Email",
        cell: ({ row }) => row.original.email,
    },
    {
        accessorKey: "total_referrals",
        header: "Total",
        cell: ({ row }) => String(row.original.total_referrals ?? 0),
    },
    {
        accessorKey: "pending_count",
        header: "Pending",
        cell: ({ row }) => String(row.original.pending_count ?? 0),
    },
    {
        accessorKey: "qualified_count",
        header: "Qualified",
        cell: ({ row }) => String(row.original.qualified_count ?? 0),
    },
    {
        accessorKey: "rewarded_count",
        header: "Rewarded",
        cell: ({ row }) => String(row.original.rewarded_count ?? 0),
    },
    {
        accessorKey: "revoked_count",
        header: "Revoked",
        cell: ({ row }) => String(row.original.revoked_count ?? 0),
    },
    {
        accessorKey: "latest_referral_date",
        header: "Latest",
        cell: ({ row }) =>
            row.original.latest_referral_date
                ? format(new Date(row.original.latest_referral_date), "PPP")
                : "-",
    },
];

const Referrals = () => {
    return (
        <AppLayout>
            <div className="flex px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Referrals
                            </h2>
                        </div>
                    </div>
                    <div className="mt-4">
                        <DataTable
                            columns={columns}
                            endpoint="/referrals/all"
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

export default Referrals;
