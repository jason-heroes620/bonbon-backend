import { DataTable } from "@/components/datatable/data-table";
import AppLayout from "@/layouts/AppLayout";
import { Badge } from "@/components/ui/badge";
import type { DeliveryOrderListItem } from "@/types";
import type { ColumnDef } from "@tanstack/react-table";
import { router } from "@inertiajs/react";
import { Eye, MapPin } from "lucide-react";
import { format } from "date-fns";
import { Button } from "@/components/ui/button";

const statusTone: Record<string, { className: string; label: string }> = {
    Pending: {
        className: "bg-amber-500/15 text-amber-700 hover:bg-amber-500/15",
        label: "Pending",
    },
    Prepared: {
        className: "bg-blue-500/15 text-blue-700 hover:bg-blue-500/15",
        label: "Prepared",
    },
    Default: {
        className: "bg-indigo-500/15 text-slate-700 hover:bg-slate-500/15",
        label: "Default",
    },
    Received: {
        className: "bg-green-500/15 text-green-700 hover:bg-green-500/15",
        label: "Received",
    },
};

const formatCurrency = (v: string | number | null | undefined) => {
    if (v === null || v === undefined || v === "") {
        return "RM 0.00";
    }
    const n = typeof v === "number" ? v : Number(v);
    if (Number.isNaN(n)) {
        return "RM 0.00";
    }
    return `RM ${n.toFixed(2)}`;
};

const customerName = (row: DeliveryOrderListItem) => {
    if (!row.customer) {
        return "-";
    }
    const first = row.customer.first_name?.trim() ?? "";
    const last = row.customer.last_name?.trim() ?? "";
    const combined = `${first} ${last}`.trim();
    return combined.length > 0 ? combined : row.customer.email;
};

export const columns: ColumnDef<DeliveryOrderListItem>[] = [
    {
        accessorKey: "order_no",
        header: "Order No",
        cell: ({ row }) => (
            <div className="flex flex-col gap-0.5">
                <span className="font-semibold text-[#3730A3]">
                    {row.original.order_no}
                </span>
                <span className="text-xs text-slate-500">
                    Delivery Order No: {row.original.delivery_order_no ?? "—"}
                </span>
            </div>
        ),
    },
    {
        accessorKey: "created_at",
        header: "Created",
        cell: ({ row }) =>
            row.original.created_at
                ? format(new Date(row.original.created_at), "PPp")
                : row.original.order_date
                  ? format(new Date(row.original.order_date), "PPP")
                  : "",
    },
    {
        accessorKey: "customer",
        header: "Customer",
        cell: ({ row }) => {
            const c = row.original.customer;
            if (!c) {
                return "-";
            }
            return (
                <div className="flex flex-col gap-0.5">
                    <span className="font-medium text-slate-900">
                        {customerName(row.original)}
                    </span>
                    <span className="text-xs text-slate-500">{c.email}</span>
                    {c.contact_no ? (
                        <span className="text-xs text-slate-500">
                            {c.contact_no}
                        </span>
                    ) : null}
                </div>
            );
        },
    },
    {
        accessorKey: "shipping_service_name",
        header: "Courier",
        cell: ({ row }) => (
            <div className="flex items-start gap-2">
                <MapPin size={14} className="mt-0.5 text-slate-400" />
                <div className="flex flex-col gap-0.5">
                    <span className="font-medium text-slate-900">
                        {row.original.shipping_service_name ??
                            row.original.shipping_provider ??
                            "—"}
                    </span>
                    {row.original.shipping_service_code ? (
                        <span className="text-xs text-slate-500">
                            {row.original.shipping_service_code}
                        </span>
                    ) : null}
                </div>
            </div>
        ),
    },
    {
        accessorKey: "total_payment",
        header: "Total",
        cell: ({ row }) => (
            <span className="font-semibold text-slate-900">
                {formatCurrency(row.original.total_payment)}
            </span>
        ),
    },
    {
        accessorKey: "delivery_status",
        header: "Status",
        cell: ({ row }) => {
            const tone =
                statusTone[row.original.delivery_status] ?? statusTone.Default;
            return (
                <Badge className={tone.className}>
                    {row.original.delivery_status}
                </Badge>
            );
        },
    },
    {
        accessorKey: "actions",
        header: "Actions",
        cell: ({ row }) => (
            <div className="flex items-center gap-1 justify-end">
                <Button
                    size={"sm"}
                    variant="secondary"
                    onClick={() =>
                        router.visit(
                            route(
                                "delivery-orders.show",
                                row.original.order_id,
                            ),
                        )
                    }
                >
                    <Eye size={14} className="mr-1" />
                    Details
                </Button>
            </div>
        ),
    },
];

const DeliveryOrders = () => {
    return (
        <AppLayout>
            <div className="flex px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 bg-[#3730A3]/20 px-4 py-3 rounded-md">
                        <div className="flex flex-col gap-0.5">
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Delivery Orders
                            </h2>
                            <p className="text-xs text-slate-600">
                                Orders dispatched via courier, pending tracking
                                number assignment.
                            </p>
                        </div>
                    </div>
                    <div className="mt-4">
                        <DataTable
                            columns={columns}
                            endpoint="/delivery-orders/all"
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

export default DeliveryOrders;
