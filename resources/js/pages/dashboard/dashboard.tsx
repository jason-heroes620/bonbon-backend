import AppLayout from "@/layouts/AppLayout";
import { Button } from "@/components/ui/button";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { router, usePage } from "@inertiajs/react";
import { format } from "date-fns";
import {
    Award,
    CalendarDays,
    Receipt,
    Ticket,
    UserPlus,
    Users,
} from "lucide-react";

type DashboardKpis = {
    revenue_mtd: number;
    orders_today: number;
    orders_mtd: number;
    new_users_7d: number;
    active_vendors: number;
    active_memberships: number;
    membership_expiring_7d: number;
    upcoming_events_30d: number;
};

type ChartPoint = { day: string; total: number };

type DashboardCharts = {
    days: string[];
    revenue_30d: ChartPoint[];
    new_users_30d: ChartPoint[];
    referrals_by_status: {
        pending: number;
        qualified: number;
        rewarded: number;
        revoked: number;
    };
};

type AttentionPayment = {
    payment_id: string;
    order_no: string;
    payment_amount: number;
    payment_method?: string | null;
    payment_date?: string | null;
    payment_status: number;
};

type AttentionVoucher = {
    voucher_id: string;
    voucher_name: string;
    voucher_expiry_date: string;
};

type AttentionDiscount = {
    discount_id: string;
    discount_code: string;
    discount_name: string;
    discount_end_date: string;
};

type AttentionReferral = {
    referral_id: string;
    referral_code: string;
    referral_date: string;
    referrer_name: string;
    referrer_email: string;
};

type DashboardAttention = {
    recent_failed_payments: AttentionPayment[];
    expiring_vouchers_7d: AttentionVoucher[];
    expiring_discounts_7d: AttentionDiscount[];
    stale_pending_referrals: AttentionReferral[];
};

type DashboardPageProps = {
    kpis: DashboardKpis;
    charts: DashboardCharts;
    attention: DashboardAttention;
};

function formatCurrencyMYR(value: number) {
    return new Intl.NumberFormat("en-MY", {
        style: "currency",
        currency: "MYR",
        maximumFractionDigits: 2,
    }).format(value);
}

function StatCard({
    title,
    value,
    subtitle,
    icon,
    onClick,
}: {
    title: string;
    value: string;
    subtitle?: string;
    icon: React.ReactNode;
    onClick?: () => void;
}) {
    return (
        <div
            className="rounded-xl bg-muted/50 p-4 border border-border/50 hover:bg-muted transition-colors cursor-pointer"
            onClick={onClick}
            role={onClick ? "button" : undefined}
            tabIndex={onClick ? 0 : undefined}
            onKeyDown={(e) => {
                if (!onClick) return;
                if (e.key === "Enter" || e.key === " ") onClick();
            }}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="text-sm text-muted-foreground">{title}</div>
                    <div className="mt-1 text-2xl font-semibold text-foreground">
                        {value}
                    </div>
                    {subtitle ? (
                        <div className="mt-1 text-xs text-muted-foreground">
                            {subtitle}
                        </div>
                    ) : null}
                </div>
                <div className="text-muted-foreground">{icon}</div>
            </div>
        </div>
    );
}

function MiniLineChart({ points }: { points: ChartPoint[] }) {
    const width = 520;
    const height = 140;
    const padding = 16;
    const values = points.map((p) => p.total);
    const max = Math.max(1, ...values);
    const min = Math.min(0, ...values);
    const scaleX = (i: number) => {
        if (points.length <= 1) return padding;
        return (
            padding +
            (i * (width - padding * 2)) / (Math.max(1, points.length - 1))
        );
    };
    const scaleY = (v: number) => {
        const denom = max - min || 1;
        return height - padding - ((v - min) * (height - padding * 2)) / denom;
    };
    const d = points
        .map((p, i) => `${i === 0 ? "M" : "L"} ${scaleX(i)} ${scaleY(p.total)}`)
        .join(" ");
    const last = points[points.length - 1];

    return (
        <div className="w-full">
            <svg
                viewBox={`0 0 ${width} ${height}`}
                className="w-full h-[140px]"
                preserveAspectRatio="none"
            >
                <path
                    d={d}
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    className="text-[#3730A3]"
                />
                <circle
                    cx={scaleX(points.length - 1)}
                    cy={scaleY(last?.total ?? 0)}
                    r="3.5"
                    className="fill-[#3730A3]"
                />
            </svg>
        </div>
    );
}

function MiniBarChart({ points }: { points: ChartPoint[] }) {
    const max = Math.max(1, ...points.map((p) => p.total));
    return (
        <div className="flex items-end gap-1 h-[140px]">
            {points.map((p) => (
                <div
                    key={p.day}
                    className="flex-1 rounded-sm bg-[#3730A3]/20"
                    style={{
                        height: `${Math.round((p.total / max) * 140)}px`,
                    }}
                    title={`${p.day}: ${p.total}`}
                />
            ))}
        </div>
    );
}

function ReferralFunnel({
    pending,
    qualified,
    rewarded,
    revoked,
}: DashboardCharts["referrals_by_status"]) {
    const total = pending + qualified + rewarded + revoked;
    const pct = (v: number) =>
        total === 0 ? 0 : Math.round((v / total) * 100);

    return (
        <div className="space-y-3">
            <div className="flex h-3 w-full overflow-hidden rounded-full bg-muted">
                <div
                    className="bg-amber-400/80"
                    style={{ width: `${pct(pending)}%` }}
                />
                <div
                    className="bg-blue-500/70"
                    style={{ width: `${pct(qualified)}%` }}
                />
                <div
                    className="bg-emerald-500/70"
                    style={{ width: `${pct(rewarded)}%` }}
                />
                <div
                    className="bg-rose-500/70"
                    style={{ width: `${pct(revoked)}%` }}
                />
            </div>
            <div className="grid grid-cols-2 gap-3 text-sm">
                <div className="flex items-center justify-between rounded-md bg-muted/50 px-3 py-2">
                    <span className="text-muted-foreground">Pending</span>
                    <span className="font-medium">{pending}</span>
                </div>
                <div className="flex items-center justify-between rounded-md bg-muted/50 px-3 py-2">
                    <span className="text-muted-foreground">Qualified</span>
                    <span className="font-medium">{qualified}</span>
                </div>
                <div className="flex items-center justify-between rounded-md bg-muted/50 px-3 py-2">
                    <span className="text-muted-foreground">Rewarded</span>
                    <span className="font-medium">{rewarded}</span>
                </div>
                <div className="flex items-center justify-between rounded-md bg-muted/50 px-3 py-2">
                    <span className="text-muted-foreground">Revoked</span>
                    <span className="font-medium">{revoked}</span>
                </div>
            </div>
        </div>
    );
}

const Dashboard = () => {
    const { kpis, charts, attention } = usePage<DashboardPageProps>().props;

    return (
        <AppLayout>
            <div className="px-4 py-2 w-full space-y-6">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-bold text-[#3730A3]">
                            Dashboard
                        </h2>
                        <div className="text-sm text-muted-foreground">
                            KPI cards are month-to-date. Charts show the last 30
                            days.
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            onClick={() => router.visit("/payments")}
                        >
                            View Payments
                        </Button>
                        <Button
                            variant="default"
                            onClick={() => router.visit("/orders")}
                        >
                            View Orders
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <StatCard
                        title="Gross Revenue (MTD)"
                        value={formatCurrencyMYR(kpis.revenue_mtd)}
                        subtitle="Successful payments only"
                        icon={<Receipt className="h-5 w-5" />}
                        onClick={() => router.visit("/payments")}
                    />
                    <StatCard
                        title="Orders"
                        value={`${kpis.orders_today} today`}
                        subtitle={`${kpis.orders_mtd} month-to-date`}
                        icon={<Receipt className="h-5 w-5" />}
                        onClick={() => router.visit("/orders")}
                    />
                    <StatCard
                        title="New Users (7d)"
                        value={String(kpis.new_users_7d)}
                        subtitle="Last 7 days"
                        icon={<UserPlus className="h-5 w-5" />}
                        onClick={() => router.visit("/users")}
                    />
                    <StatCard
                        title="Active Vendors"
                        value={String(kpis.active_vendors)}
                        subtitle="Vendors marked active"
                        icon={<Users className="h-5 w-5" />}
                        onClick={() => router.visit("/vendors")}
                    />
                    <StatCard
                        title="Active Memberships"
                        value={String(kpis.active_memberships)}
                        subtitle={`${kpis.membership_expiring_7d} expiring in 7d`}
                        icon={<Award className="h-5 w-5" />}
                        onClick={() => router.visit("/memberships")}
                    />
                    <StatCard
                        title="Upcoming Events (30d)"
                        value={String(kpis.upcoming_events_30d)}
                        subtitle="Published + active"
                        icon={<CalendarDays className="h-5 w-5" />}
                        onClick={() => router.visit("/events")}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="rounded-xl bg-muted/50 border border-border/50 p-4">
                        <div className="flex items-center justify-between">
                            <div className="font-semibold">
                                Revenue Trend (30d)
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => router.visit("/payments")}
                            >
                                Details
                            </Button>
                        </div>
                        <div className="mt-3">
                            <MiniLineChart points={charts.revenue_30d} />
                        </div>
                    </div>

                    <div className="rounded-xl bg-muted/50 border border-border/50 p-4">
                        <div className="flex items-center justify-between">
                            <div className="font-semibold">
                                New Users (30d)
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => router.visit("/users")}
                            >
                                Details
                            </Button>
                        </div>
                        <div className="mt-3">
                            <MiniBarChart points={charts.new_users_30d} />
                        </div>
                    </div>

                    <div className="rounded-xl bg-muted/50 border border-border/50 p-4">
                        <div className="flex items-center justify-between">
                            <div className="font-semibold">
                                Referral Funnel
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => router.visit("/referrals")}
                            >
                                Details
                            </Button>
                        </div>
                        <div className="mt-4">
                            <ReferralFunnel {...charts.referrals_by_status} />
                        </div>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <div className="rounded-xl bg-muted/50 border border-border/50 p-4">
                        <div className="flex items-center justify-between">
                            <div className="font-semibold">
                                Recent Failed Payments
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => router.visit("/payments")}
                            >
                                View All
                            </Button>
                        </div>
                        <div className="mt-3">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Order</TableHead>
                                        <TableHead>Amount</TableHead>
                                        <TableHead>Method</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {attention.recent_failed_payments.map(
                                        (p) => (
                                            <TableRow key={p.payment_id}>
                                                <TableCell>
                                                    {p.order_no}
                                                </TableCell>
                                                <TableCell>
                                                    {formatCurrencyMYR(
                                                        p.payment_amount,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {p.payment_method ?? "-"}
                                                </TableCell>
                                                <TableCell>
                                                    {p.payment_date
                                                        ? format(
                                                              new Date(
                                                                  p.payment_date,
                                                              ),
                                                              "PPP",
                                                          )
                                                        : "-"}
                                                </TableCell>
                                                <TableCell>
                                                    {String(p.payment_status)}
                                                </TableCell>
                                            </TableRow>
                                        ),
                                    )}
                                    {attention.recent_failed_payments.length ===
                                    0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={5}
                                                className="text-muted-foreground"
                                            >
                                                No failed payments found.
                                            </TableCell>
                                        </TableRow>
                                    ) : null}
                                </TableBody>
                            </Table>
                        </div>
                    </div>

                    <div className="rounded-xl bg-muted/50 border border-border/50 p-4">
                        <div className="flex items-center justify-between">
                            <div className="font-semibold">
                                Expiring Soon (7d)
                            </div>
                            <div className="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => router.visit("/vouchers")}
                                >
                                    Vouchers
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => router.visit("/discounts")}
                                >
                                    Discounts
                                </Button>
                            </div>
                        </div>

                        <div className="mt-3 space-y-4">
                            <div>
                                <div className="flex items-center justify-between">
                                    <div className="text-sm font-medium">
                                        Vouchers
                                    </div>
                                    <Ticket className="h-4 w-4 text-muted-foreground" />
                                </div>
                                <div className="mt-2">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Name</TableHead>
                                                <TableHead>Expiry</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {attention.expiring_vouchers_7d.map(
                                                (v) => (
                                                    <TableRow key={v.voucher_id}>
                                                        <TableCell>
                                                            {v.voucher_name}
                                                        </TableCell>
                                                        <TableCell>
                                                            {format(
                                                                new Date(
                                                                    v.voucher_expiry_date,
                                                                ),
                                                                "PPP",
                                                            )}
                                                        </TableCell>
                                                    </TableRow>
                                                ),
                                            )}
                                            {attention.expiring_vouchers_7d
                                                .length === 0 ? (
                                                <TableRow>
                                                    <TableCell
                                                        colSpan={2}
                                                        className="text-muted-foreground"
                                                    >
                                                        No vouchers expiring in
                                                        7 days.
                                                    </TableCell>
                                                </TableRow>
                                            ) : null}
                                        </TableBody>
                                    </Table>
                                </div>
                            </div>

                            <div>
                                <div className="flex items-center justify-between">
                                    <div className="text-sm font-medium">
                                        Discounts
                                    </div>
                                    <Ticket className="h-4 w-4 text-muted-foreground" />
                                </div>
                                <div className="mt-2">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Code</TableHead>
                                                <TableHead>Name</TableHead>
                                                <TableHead>End</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {attention.expiring_discounts_7d.map(
                                                (d) => (
                                                    <TableRow
                                                        key={d.discount_id}
                                                    >
                                                        <TableCell>
                                                            {d.discount_code}
                                                        </TableCell>
                                                        <TableCell>
                                                            {d.discount_name}
                                                        </TableCell>
                                                        <TableCell>
                                                            {format(
                                                                new Date(
                                                                    d.discount_end_date,
                                                                ),
                                                                "PPP",
                                                            )}
                                                        </TableCell>
                                                    </TableRow>
                                                ),
                                            )}
                                            {attention.expiring_discounts_7d
                                                .length === 0 ? (
                                                <TableRow>
                                                    <TableCell
                                                        colSpan={3}
                                                        className="text-muted-foreground"
                                                    >
                                                        No discounts expiring in
                                                        7 days.
                                                    </TableCell>
                                                </TableRow>
                                            ) : null}
                                        </TableBody>
                                    </Table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="rounded-xl bg-muted/50 border border-border/50 p-4">
                    <div className="flex items-center justify-between">
                        <div className="font-semibold">
                            Pending Referrals (Older than 14d)
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => router.visit("/referrals")}
                        >
                            View Referrals
                        </Button>
                    </div>
                    <div className="mt-3">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Referrer</TableHead>
                                    <TableHead>Email</TableHead>
                                    <TableHead>Code</TableHead>
                                    <TableHead>Date</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {attention.stale_pending_referrals.map((r) => (
                                    <TableRow key={r.referral_id}>
                                        <TableCell>{r.referrer_name}</TableCell>
                                        <TableCell>
                                            {r.referrer_email}
                                        </TableCell>
                                        <TableCell>{r.referral_code}</TableCell>
                                        <TableCell>
                                            {format(
                                                new Date(r.referral_date),
                                                "PPP",
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {attention.stale_pending_referrals.length ===
                                0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={4}
                                            className="text-muted-foreground"
                                        >
                                            No stale pending referrals found.
                                        </TableCell>
                                    </TableRow>
                                ) : null}
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
};

export default Dashboard;
