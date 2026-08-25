import { DataTable } from "@/components/datatable/data-table";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import AppLayout from "@/layouts/AppLayout";
import type { ColumnDef } from "@tanstack/react-table";
import { Head } from "@inertiajs/react";
import { useMemo, useState } from "react";

type TenderSummaryReportRow = {
    owner_vendor_id: string;
    owner_vendor_name: string;
    payee_vendor_id: string;
    payee_vendor_name: string;
    contracts_count: number;
    total_payable: string;
    latest_payment_date?: string | null;
};

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

function getDefaultMonthYear() {
    const date = new Date();
    date.setMonth(date.getMonth() - 1);

    return {
        month: date.getMonth() + 1,
        year: date.getFullYear(),
    };
}

function formatCurrency(value: string | number) {
    const amount = Number(value) || 0;

    return new Intl.NumberFormat("en-MY", {
        style: "currency",
        currency: "MYR",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
}

const columns: ColumnDef<TenderSummaryReportRow>[] = [
    {
        accessorKey: "owner_vendor_name",
        header: "Vendor",
        cell: ({ row }) => (
            <div className="space-y-1">
                <div className="font-medium">
                    {row.original.owner_vendor_name ?? "-"}
                </div>
                <div className="text-xs text-muted-foreground">
                    {row.original.owner_vendor_id}
                </div>
            </div>
        ),
    },
    {
        accessorKey: "payee_vendor_name",
        header: "Payee",
        cell: ({ row }) => (
            <div className="space-y-1">
                <div className="font-medium">
                    {row.original.payee_vendor_name ?? "-"}
                </div>
                <div className="text-xs text-muted-foreground">
                    {row.original.payee_vendor_id}
                </div>
            </div>
        ),
    },
    {
        accessorKey: "contracts_count",
        header: "Contracts",
        cell: ({ row }) => row.original.contracts_count ?? 0,
    },
    {
        accessorKey: "total_payable",
        header: "Total Payable",
        cell: ({ row }) => formatCurrency(row.original.total_payable),
    },
    {
        accessorKey: "latest_payment_date",
        header: "Latest Payment",
        cell: ({ row }) => row.original.latest_payment_date ?? "-",
    },
];

export default function TenderSummaryReport({
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

    const [month, setMonth] = useState(String(initialMonth));
    const [year, setYear] = useState(String(initialYear));

    const yearOptions = useMemo(() => {
        if (Array.isArray(years) && years.length > 0) {
            return years.map((value) => String(value));
        }

        const currentYear = new Date().getFullYear();
        return Array.from({ length: 6 }, (_, index) =>
            String(currentYear - index),
        );
    }, [years]);

    const endpoint = `/reports/tender-summary-report/data?month=${month}&year=${year}`;

    return (
        <AppLayout>
            <Head title="Tender Summary Report" />

            <div className="px-4 py-2 w-full space-y-4">
                <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                    <div>
                        <h2 className="text-lg font-bold text-[#3730A3]">
                            Tender Summary Report
                        </h2>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 bg-white p-4 rounded-md border md:grid-cols-2 lg:max-w-xl">
                    <div className="space-y-2">
                        <label className="text-sm font-medium">Month</label>
                        <Select value={month} onValueChange={setMonth}>
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Month" />
                            </SelectTrigger>
                            <SelectContent>
                                {monthOptions.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <label className="text-sm font-medium">Year</label>
                        <Select value={year} onValueChange={setYear}>
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Year" />
                            </SelectTrigger>
                            <SelectContent>
                                {yearOptions.map((option) => (
                                    <SelectItem key={option} value={option}>
                                        {option}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div className="mt-4">
                    <DataTable
                        key={`${month}-${year}`}
                        columns={columns}
                        endpoint={endpoint}
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
