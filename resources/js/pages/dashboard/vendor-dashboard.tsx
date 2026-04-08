import AppLayout from "@/layouts/AppLayout";
import { Ticket, BadgeCheck, Receipt } from "lucide-react";
import { Head } from "@inertiajs/react";

type VendorDashboardKpis = {
    active_vouchers: number;
    total_claims: number;
    total_redeems: number;
};

type Props = {
    kpis: VendorDashboardKpis;
};

function StatCard({
    title,
    value,
    icon,
}: {
    title: string;
    value: string;
    icon: React.ReactNode;
}) {
    return (
        <div className="rounded-xl bg-muted/50 p-4 border border-border/50">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="text-sm text-muted-foreground">{title}</div>
                    <div className="mt-1 text-2xl font-semibold text-foreground">
                        {value}
                    </div>
                </div>
                <div className="text-muted-foreground">{icon}</div>
            </div>
        </div>
    );
}

export default function VendorDashboard({ kpis }: Props) {
    return (
        <AppLayout>
            <Head title="Dashboard" />
            <div className="flex px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Dashboard
                            </h2>
                            <div className="text-sm text-muted-foreground">
                                Vendor overview
                            </div>
                        </div>
                    </div>

                    <div className="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <StatCard
                            title="Active vouchers"
                            value={String(kpis.active_vouchers ?? 0)}
                            icon={<Ticket className="h-5 w-5" />}
                        />
                        <StatCard
                            title="Total claims"
                            value={String(kpis.total_claims ?? 0)}
                            icon={<BadgeCheck className="h-5 w-5" />}
                        />
                        <StatCard
                            title="Total redeems"
                            value={String(kpis.total_redeems ?? 0)}
                            icon={<Receipt className="h-5 w-5" />}
                        />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

