import AppLayout from "@/layouts/AppLayout";
import { Ticket, BadgeCheck, Receipt } from "lucide-react";
import { Head } from "@inertiajs/react";
import { StockLocationBarChart } from "@/components/StockLocationBarChart";
import {
    MiniLineChart,
    type SalesChartPoint,
} from "@/components/MiniLineChart";

type VendorDashboardKpis = {
    active_vouchers: number;
    total_claims: number;
    total_redeems: number;
};

type Props = {
    kpis: VendorDashboardKpis;
    sales_3m: SalesChartPoint[];
    stockByLocation: any[];
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

export default function VendorDashboard({
    kpis,
    sales_3m,
    stockByLocation,
}: Props) {
    console.log(stockByLocation);
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

                    <div className="flex flex-col md:grid md:grid-cols-3 gap-4 mt-4">
                        <div className="rounded-xl bg-muted/50 border border-border/50">
                            <div className="flex items-center justify-between gap-4 p-4">
                                <div>
                                    <div className="text-sm font-semibold text-foreground">
                                        Sales (3 months)
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        Successful payments for your vendor
                                        account
                                    </div>
                                </div>
                            </div>
                            <div className="mt-4 px-2">
                                <MiniLineChart points={sales_3m} />
                            </div>
                        </div>
                        <div>
                            <StockLocationBarChart items={stockByLocation} />
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
