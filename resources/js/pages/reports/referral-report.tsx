import AppLayout from "@/layouts/AppLayout";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Head } from "@inertiajs/react";
import axios from "axios";
import { useEffect, useMemo, useState } from "react";

type ReferralReportRow = {
    user_id: string;
    first_name: string;
    last_name: string;
    email: string;
    membership_type: string;
    total_referrals: number;
    total_payable: number;
};

type Option = { value: string; label: string };

function getDefaultMonthYear() {
    const d = new Date();
    d.setMonth(d.getMonth() - 1);
    return { month: d.getMonth() + 1, year: d.getFullYear() };
}

const monthOptions = [
    { value: "1", label: "January" },
    { value: "2", label: "February" },
    { value: "3", label: "March" },
    { value: "4", label: "April" },
    { value: "5", label: "May" },
    { value: "6", label: "June" },
    { value: "7", label: "July" },
    { value: "8", label: "August" },
    { value: "9", label: "September" },
    { value: "10", label: "October" },
    { value: "11", label: "November" },
    { value: "12", label: "December" },
];

export default function ReferralReport({
    defaultMonth,
    defaultYear,
    years,
}: {
    defaultMonth?: number;
    defaultYear?: number;
    years?: number[];
}) {
    const fallback = useMemo(() => getDefaultMonthYear(), []);
    const initialMonth = defaultMonth ?? fallback.month;
    const initialYear = defaultYear ?? fallback.year;

    const [membershipType, setMembershipType] = useState<string>("all");
    const [userId, setUserId] = useState<string>("all");
    const [month, setMonth] = useState<string>(String(initialMonth));
    const [year, setYear] = useState<string>(String(initialYear));

    const [userOptions, setUserOptions] = useState<Option[]>([]);
    const [rows, setRows] = useState<ReferralReportRow[]>([]);
    const [loading, setLoading] = useState(false);

    const yearOptions = useMemo(() => {
        const list = Array.isArray(years) && years.length > 0 ? years : [];
        if (list.length > 0) {
            return list.map((y) => String(y));
        }
        const current = new Date().getFullYear();
        return Array.from({ length: 6 }, (_, i) => String(current - i));
    }, [years]);

    useEffect(() => {
        setUserId("all");
        axios
            .get(route("reports.referral.users"), {
                params: { membership_type: membershipType },
            })
            .then((res) => {
                const data = (res.data?.data ?? []) as Option[];
                setUserOptions(data);
            });
    }, [membershipType]);

    const fetchReport = async () => {
        setLoading(true);
        try {
            const res = await axios.get(route("reports.referral.data"), {
                params: {
                    membership_type: membershipType,
                    user_id: userId,
                    month: Number(month),
                    year: Number(year),
                },
            });
            setRows((res.data?.data ?? []) as ReferralReportRow[]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchReport();
    }, [membershipType, userId, month, year]);

    const totalPayable = useMemo(
        () => rows.reduce((sum, r) => sum + (Number(r.total_payable) || 0), 0),
        [rows],
    );

    return (
        <AppLayout>
            <Head title="Referral Report" />
            <div className="px-4 py-2 w-full space-y-4">
                <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                    <div>
                        <h2 className="text-lg font-bold text-[#3730A3]">
                            Referral Report
                        </h2>
                    </div>
                    <div className="text-sm text-muted-foreground">
                        Total payable: {totalPayable}
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-4 gap-4 bg-white p-4 rounded-md border">
                    <div className="space-y-2">
                        <Label>Membership Type</Label>
                        <Select
                            value={membershipType}
                            onValueChange={setMembershipType}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">KOL + FOBB</SelectItem>
                                <SelectItem value="KOL">KOL</SelectItem>
                                <SelectItem value="FOBB">FOBB</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <Label>User</Label>
                        <Select value={userId} onValueChange={setUserId}>
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="All users" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All</SelectItem>
                                {userOptions.map((u) => (
                                    <SelectItem key={u.value} value={u.value}>
                                        {u.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <Label>Month</Label>
                        <Select value={month} onValueChange={setMonth}>
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Month" />
                            </SelectTrigger>
                            <SelectContent>
                                {monthOptions.map((m) => (
                                    <SelectItem key={m.value} value={m.value}>
                                        {m.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <Label>Year</Label>
                        <Select value={year} onValueChange={setYear}>
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Year" />
                            </SelectTrigger>
                            <SelectContent>
                                {yearOptions.map((y) => (
                                    <SelectItem key={y} value={y}>
                                        {y}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div className="bg-white rounded-md border overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-bold tracking-wider text-gray-500">
                                    User
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-bold tracking-wider text-gray-500">
                                    Email
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-bold tracking-wider text-gray-500">
                                    Membership
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-bold tracking-wider text-gray-500">
                                    Referrals
                                </th>
                                <th className="px-6 py-3 text-left text-xs font-bold tracking-wider text-gray-500">
                                    Total Payable
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 bg-white">
                            {loading ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-6 py-4 text-center"
                                    >
                                        Loading...
                                    </td>
                                </tr>
                            ) : rows.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-6 py-4 text-center text-muted-foreground"
                                    >
                                        No results
                                    </td>
                                </tr>
                            ) : (
                                rows.map((r) => (
                                    <tr key={r.user_id}>
                                        <td className="px-6 py-3 text-sm whitespace-nowrap">
                                            {`${r.first_name ?? ""} ${r.last_name ?? ""}`.trim()}
                                        </td>
                                        <td className="px-6 py-3 text-sm whitespace-nowrap">
                                            {r.email}
                                        </td>
                                        <td className="px-6 py-3 text-sm whitespace-nowrap">
                                            {r.membership_type}
                                        </td>
                                        <td className="px-6 py-3 text-sm whitespace-nowrap">
                                            {r.total_referrals}
                                        </td>
                                        <td className="px-6 py-3 text-sm whitespace-nowrap">
                                            {r.total_payable}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex justify-end">
                    <Button
                        variant="outline"
                        onClick={fetchReport}
                        disabled={loading}
                    >
                        Refresh
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}

