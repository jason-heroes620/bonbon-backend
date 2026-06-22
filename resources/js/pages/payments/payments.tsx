import { Button } from "@/components/ui/button";
import { DataTable } from "@/components/datatable/data-table";
import AppLayout from "@/layouts/AppLayout";
import type { Payment } from "@/types";
import type { ColumnDef } from "@tanstack/react-table";
import { router } from "@inertiajs/react";
import { Pencil, Plus } from "lucide-react";
import { format } from "date-fns/format";

export const columns: ColumnDef<Payment>[] = [
    {
        accessorKey: "order_no",
        header: "Order No",
        cell: ({ row }) => row.original.order_no,
    },
    {
        accessorKey: "payment_date",
        header: "Date",
        cell: ({ row }) => format(row.original.payment_date, "PPP"),
    },
    {
        accessorKey: "payment_method",
        header: "Method",
        cell: ({ row }) => row.original.payment_method,
    },
    {
        accessorKey: "payment_amount",
        header: "Amount",
        cell: ({ row }) => row.original.payment_amount,
    },
    {
        accessorKey: "payment_status",
        header: "Status",
        cell: ({ row }) => String(row.original.payment_status),
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
                        router.visit(
                            route("payments.edit", row.original.payment_id),
                        )
                    }
                >
                    <Pencil />
                </Button>
            </div>
        ),
    },
];

const Payments = () => {
    return (
        <AppLayout>
            <div className="flex px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Payments
                            </h2>
                        </div>
                        <div>
                            <Button
                                variant="default"
                                onClick={() =>
                                    router.visit(route("payments.create"))
                                }
                            >
                                <Plus className="mr-2" size={20} />
                                Payment
                            </Button>
                        </div>
                    </div>
                    <div className="mt-4">
                        <DataTable
                            columns={columns}
                            endpoint="/payments/all"
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

export default Payments;
