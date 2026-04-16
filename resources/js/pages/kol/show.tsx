import AppLayout from "@/layouts/AppLayout";
import { Head, router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { ChevronLeft } from "lucide-react";
import { DataTable } from "@/components/datatable/data-table";
import type { ColumnDef } from "@tanstack/react-table";
import { format } from "date-fns";

type KolUser = {
    user_id: string;
    first_name: string;
    last_name: string;
    email: string;
    contact_no?: string | null;
};

type ReferralRow = {
    referral_id: string;
    referral_code: string;
    referral_status: string;
    referral_date?: string | null;
    qualified_at?: string | null;
    qualifying_order_no?: string | null;
    cycle?: number | null;
    referee_user_id: string;
    referee_first_name: string;
    referee_last_name: string;
    referee_email: string;
};

type Props = {
    kolUser: KolUser;
    referralStats: {
        pending: number;
        qualified: number;
        rewarded: number;
        revoked: number;
    };
};

const columns: ColumnDef<ReferralRow>[] = [
    {
        accessorKey: "referee",
        header: "Referee",
        cell: ({ row }) =>
            `${row.original.referee_first_name} ${row.original.referee_last_name}`,
    },
    {
        accessorKey: "referee_email",
        header: "Referee Email",
        cell: ({ row }) => row.original.referee_email,
    },
    {
        accessorKey: "referral_status",
        header: "Status",
        cell: ({ row }) => row.original.referral_status,
    },
    {
        accessorKey: "cycle",
        header: "Cycle",
        cell: ({ row }) =>
            row.original.cycle !== null && row.original.cycle !== undefined
                ? String(row.original.cycle)
                : "-",
    },
    {
        accessorKey: "referral_date",
        header: "Referral Date",
        cell: ({ row }) =>
            row.original.referral_date
                ? format(new Date(row.original.referral_date), "PPP")
                : "-",
    },
    {
        accessorKey: "qualified_at",
        header: "Qualified At",
        cell: ({ row }) =>
            row.original.qualified_at
                ? format(new Date(row.original.qualified_at), "PPP")
                : "-",
    },
    {
        accessorKey: "qualifying_order_no",
        header: "Order No",
        cell: ({ row }) => row.original.qualifying_order_no ?? "-",
    },
];

export default function KolShow({ kolUser, referralStats }: Props) {
    return (
        <AppLayout>
            <Head title="KOL Details" />
            <div className="flex flex-col px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div className="flex items-center gap-4">
                            <Button
                                variant="default"
                                onClick={() => router.visit(route("kol.index"))}
                            >
                                <ChevronLeft className="mr-2" size={20} />
                                Back
                            </Button>
                            <div>
                                <h2 className="text-lg font-bold text-[#3730A3]">
                                    KOL Details
                                </h2>
                                <div className="text-sm text-muted-foreground">
                                    {kolUser.email}
                                </div>
                            </div>
                        </div>
                        <div className="text-sm text-muted-foreground">
                            Pending {referralStats.pending} · Qualified{" "}
                            {referralStats.qualified} · Rewarded{" "}
                            {referralStats.rewarded} · Revoked{" "}
                            {referralStats.revoked}
                        </div>
                    </div>
                </div>

                <div className="mt-4">
                    <DataTable
                        columns={columns}
                        endpoint={`/kol/${kolUser.user_id}/referrals/all`}
                        options={{
                            showSearch: true,
                            showFilters: false,
                            showPagination: true,
                            defaultPageSize: 10,
                        }}
                    />
                </div>
            </div>
        </AppLayout>
    );
}

