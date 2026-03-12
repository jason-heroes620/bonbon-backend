import { Button } from "@/components/ui/button";
import { DataTable } from "@/components/datatable/data-table";
import AppLayout from "@/layouts/AppLayout";
import type { Order } from "@/types";
import type { ColumnDef } from "@tanstack/react-table";
import { router } from "@inertiajs/react";
import { Pencil, Plus } from "lucide-react";
import { format } from "date-fns";

export const columns: ColumnDef<Order>[] = [
    {
        accessorKey: "order_no",
        header: "Order No",
        cell: ({ row }) => row.original.order_no,
    },
    {
        accessorKey: "order_date",
        header: "Date",
        cell: ({ row }) =>
            row.original.order_date
                ? format(new Date(row.original.order_date), "PPP")
                : "",
    },
    {
        accessorKey: "total_payment",
        header: "Total",
        cell: ({ row }) => row.original.total_payment,
    },
    {
        accessorKey: "order_status",
        header: "Status",
        cell: ({ row }) => row.original.order_status,
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
                            route("orders.edit", row.original.order_id),
                        )
                    }
                >
                    <Pencil />
                </Button>
            </div>
        ),
    },
];

const Orders = () => {
    return (
        <AppLayout>
            <div className="flex px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Orders
                            </h2>
                        </div>
                        <div>
                            <Button
                                variant="default"
                                onClick={() =>
                                    router.visit(route("orders.create"))
                                }
                            >
                                <Plus className="mr-2" size={20} />
                                Order
                            </Button>
                        </div>
                    </div>
                    <div className="mt-4">
                        <DataTable
                            columns={columns}
                            endpoint="/orders/all"
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

export default Orders;
