import { Button } from "@/components/ui/button";
import { DataTable } from "@/components/datatable/data-table";
import AppLayout from "@/layouts/AppLayout";
import type { ProductDiscount } from "@/types";
import type { ColumnDef } from "@tanstack/react-table";
import { router } from "@inertiajs/react";
import { Pencil, Plus } from "lucide-react";
import { format } from "date-fns";

export const columns: ColumnDef<ProductDiscount>[] = [
    {
        accessorKey: "product_name",
        header: "Product",
        cell: ({ row }) => row.original.product?.product_name ?? "-",
    },
    {
        accessorKey: "discount_type",
        header: "Type",
        cell: ({ row }) =>
            row.original.discount_type === "P" ? "Percentage" : "Fixed Amount",
    },
    {
        accessorKey: "discount_amount",
        header: "Discount",
        cell: ({ row }) => row.original.discount_amount,
    },
    {
        accessorKey: "start_date",
        header: "Start Date",
        cell: ({ row }) =>
            row.original.discount_start_date
                ? format(row.original.discount_start_date, "PPP")
                : "-",
    },
    {
        accessorKey: "end_date",
        header: "End Date",
        cell: ({ row }) =>
            row.original.discount_end_date
                ? format(row.original.discount_end_date, "PPP")
                : "-",
    },
    {
        accessorKey: "is_active",
        header: "Status",
        cell: ({ row }) => (row.original.is_active ? "Active" : "Inactive"),
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
                            route(
                                "product_discounts.edit",
                                row.original.product_discount_id,
                            ),
                        )
                    }
                >
                    <Pencil />
                </Button>
            </div>
        ),
    },
];

const ProductDiscounts = () => {
    return (
        <AppLayout>
            <div className="flex px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Product Discounts
                            </h2>
                        </div>
                        <div>
                            <Button
                                variant="default"
                                onClick={() =>
                                    router.visit(
                                        route("product_discounts.create"),
                                    )
                                }
                            >
                                <Plus className="mr-2" size={20} />
                                Discount
                            </Button>
                        </div>
                    </div>
                    <div className="mt-4">
                        <DataTable
                            columns={columns}
                            endpoint="/product-discounts/all"
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

export default ProductDiscounts;
