import { Button } from "@/components/ui/button";
import { DataTable } from "@/components/datatable/data-table";
import AppLayout from "@/layouts/AppLayout";
import type { Product } from "@/types";
import type { ColumnDef } from "@tanstack/react-table";
import { router } from "@inertiajs/react";
import { Pencil, Plus } from "lucide-react";

export const columns: ColumnDef<Product>[] = [
    {
        accessorKey: "product_name",
        header: "Name",
        cell: ({ row }) => row.original.product_name,
    },
    {
        accessorKey: "product_code",
        header: "Code",
        cell: ({ row }) => row.original.product_code ?? "-",
    },
    {
        accessorKey: "product_sku",
        header: "SKU",
        cell: ({ row }) => row.original.product_sku ?? "-",
    },
    {
        accessorKey: "sale_price",
        header: "Sale Price",
        cell: ({ row }) => row.original.sale_price,
    },
    {
        accessorKey: "pricing",
        header: "Pricing",
        cell: ({ row }) => {
            const n = row.original.active_pricing_tiers_count ?? 0;
            return n > 0 ? `Tiered (${n})` : "Base";
        },
    },
    {
        accessorKey: "stock_quantity",
        header: "Stock",
        cell: ({ row }) => row.original.stock_quantity,
    },
    {
        accessorKey: "is_active",
        header: "Status",
        cell: ({ row }) => (row.original.is_active ? "Active" : "Inactive"),
    },
    {
        accessorKey: "is_visible",
        header: "Visible",
        cell: ({ row }) => (row.original.is_visible ? "Yes" : "No"),
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
                            route("products.edit", row.original.product_id),
                        )
                    }
                >
                    <Pencil />
                </Button>
            </div>
        ),
    },
];

const Products = () => {
    return (
        <AppLayout>
            <div className="flex px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Products
                            </h2>
                        </div>
                        <div>
                            <Button
                                variant="default"
                                onClick={() =>
                                    router.visit(route("products.create"))
                                }
                            >
                                <Plus className="mr-2" size={20} />
                                Product
                            </Button>
                        </div>
                    </div>
                    <div className="mt-4">
                        <DataTable
                            columns={columns}
                            endpoint="/products/all"
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

export default Products;
