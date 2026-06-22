import AppLayout from "@/layouts/AppLayout";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Head, router, useForm } from "@inertiajs/react";
import { Pencil, Plus, QrCode, Trash2 } from "lucide-react";
import { useState } from "react";
import { format } from "date-fns";

type ChargeBreakdown = {
    charges_id: string;
    charges_type: string;
    charges_name: string;
    charges_rate: number;
    charges_description: string;
    amount: number;
};

type ContractDetail = {
    tender_compartment_id: string;
    compartment_id: string;
    rack_id: string;
    rack_name: string;
    vendor_location_name: string;
    compartment_label: string;
    vendor_name?: string | null;
    owner_vendor_name: string;
    tender_status: string;
    bid_price: string;
    durations: number;
    product_description?: string | null;
    tender_start_date?: string | null;
    tender_end_date?: string | null;
};

type ProductOption = {
    value: string;
    label: string;
};

type ContractStockProduct = {
    compartment_stock_product_id: string;
    product_id: string;
    product_name: string;
    product_code?: string | null;
    product_sku?: string | null;
    quantity: number;
    expiry_date?: string | null;
};

type ContractStock = {
    compartment_stock_id: string;
    status: string;
    created_at?: string | null;
    products: ContractStockProduct[];
};

type Props = {
    contract: ContractDetail;
    charges: ChargeBreakdown[];
    stocks: ContractStock[];
    productOptions: ProductOption[];
    summary: {
        subtotal: number;
        total_charges: number;
        total_payment: number;
    };
    canPay: boolean;
    canManageStocks: boolean;
    paymentGatewayUrl: string;
};

type StockFormValues = {
    status: string;
    items: Array<{
        product_id: string;
        quantity: number;
        expiry_date: string;
    }>;
};

type StockProductFormValues = {
    product_id: string;
    quantity: number;
    expiry_date: string;
};

type EditStockProductTarget = {
    stockId: string;
    stockStatus: string;
    product: ContractStockProduct;
};

export default function ContractShow({
    contract,
    charges,
    stocks,
    productOptions,
    summary,
    canPay,
    canManageStocks,
}: Props) {
    const isPaid = contract.tender_status === "paid";
    const [createOpen, setCreateOpen] = useState(false);
    const [qrStock, setQrStock] = useState<ContractStock | null>(null);
    const [editTarget, setEditTarget] = useState<EditStockProductTarget | null>(
        null,
    );
    const { data, setData, post, processing, errors, reset } =
        useForm<StockFormValues>({
            status: "prepared",
            items: [{ product_id: "", quantity: 1, expiry_date: "" }],
        });
    const {
        data: editData,
        setData: setEditData,
        put,
        processing: editProcessing,
        errors: editErrors,
        reset: resetEditForm,
        clearErrors: clearEditErrors,
    } = useForm<StockProductFormValues>({
        product_id: "",
        quantity: 1,
        expiry_date: "",
    });

    const canManageStockProducts = (stockStatus: string) =>
        ["prepared", "remove", "removed"].includes(stockStatus);

    const addItem = () => {
        setData("items", [
            ...data.items,
            { product_id: "", quantity: 1, expiry_date: "" },
        ]);
    };

    const updateItem = (
        index: number,
        field: "product_id" | "quantity" | "expiry_date",
        value: string | number,
    ) => {
        setData(
            "items",
            data.items.map((item, itemIndex) =>
                itemIndex === index ? { ...item, [field]: value } : item,
            ),
        );
    };

    const removeItem = (index: number) => {
        if (data.items.length === 1) {
            setData("items", [
                { product_id: "", quantity: 1, expiry_date: "" },
            ]);
            return;
        }

        setData(
            "items",
            data.items.filter((_, itemIndex) => itemIndex !== index),
        );
    };

    const submitStock = () => {
        post(route("contracts.stocks.store", contract.tender_compartment_id), {
            preserveScroll: true,
            onSuccess: () => {
                setCreateOpen(false);
                reset();
                setData({
                    status: "prepared",
                    items: [{ product_id: "", quantity: 1, expiry_date: "" }],
                });
            },
        });
    };

    const deleteStock = (stockId: string) => {
        if (!confirm("Are you sure you want to delete this stock?")) {
            return;
        }
        router.delete(
            route("contracts.stocks.destroy", [
                contract.tender_compartment_id,
                stockId,
            ]),
            {
                preserveScroll: true,
            },
        );
    };

    const openEditProduct = (
        stock: ContractStock,
        product: ContractStockProduct,
    ) => {
        setEditTarget({
            stockId: stock.compartment_stock_id,
            stockStatus: stock.status,
            product,
        });
        setEditData({
            product_id: product.product_id,
            quantity: product.quantity,
            expiry_date: product.expiry_date ?? "",
        });
    };

    const closeEditProduct = () => {
        setEditTarget(null);
        resetEditForm();
        clearEditErrors();
    };

    const submitEditProduct = () => {
        if (!editTarget) {
            return;
        }

        put(
            route("contracts.stocks.products.update", [
                contract.tender_compartment_id,
                editTarget.stockId,
                editTarget.product.compartment_stock_product_id,
            ]),
            {
                preserveScroll: true,
                onSuccess: () => {
                    closeEditProduct();
                },
            },
        );
    };

    const deleteStockProduct = (stockId: string, stockProductId: string) => {
        if (!confirm("Are you sure you want to delete this stock product?")) {
            return;
        }

        router.delete(
            route("contracts.stocks.products.destroy", [
                contract.tender_compartment_id,
                stockId,
                stockProductId,
            ]),
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout>
            <Head title="My Contract" />
            <div className="flex flex-col px-4 py-2 w-full">
                <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                    <div>
                        <h2 className="text-lg font-bold text-[#3730A3]">
                            My Contract
                        </h2>
                        <div className="text-sm text-gray-700">
                            {contract.vendor_location_name} -{" "}
                            {contract.rack_name}
                        </div>
                    </div>
                    <Button
                        variant="default"
                        onClick={() => router.visit(route("contracts.index"))}
                    >
                        Back
                    </Button>
                </div>

                <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <div className="lg:col-span-2 rounded-lg border bg-white p-4">
                        <div className="text-sm font-medium">
                            Contract Details
                        </div>
                        <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <div className="text-xs text-gray-500">
                                    Compartment
                                </div>
                                <div className="text-sm font-medium">
                                    {contract.compartment_label}
                                </div>
                            </div>
                            <div>
                                <div className="text-xs text-gray-500">
                                    Status
                                </div>
                                <div className="text-sm font-medium capitalize">
                                    {contract.tender_status}
                                </div>
                            </div>
                            <div>
                                <div className="text-xs text-gray-500">
                                    Vendor
                                </div>
                                <div className="text-sm font-medium">
                                    {contract.vendor_name ?? "-"}
                                </div>
                            </div>
                            <div>
                                <div className="text-xs text-gray-500">
                                    Rack Owner
                                </div>
                                <div className="text-sm font-medium">
                                    {contract.owner_vendor_name}
                                </div>
                            </div>
                            <div>
                                <div className="text-xs text-gray-500">
                                    Bid Price
                                </div>
                                <div className="text-sm font-medium">
                                    RM {Number(contract.bid_price).toFixed(2)}
                                </div>
                            </div>
                            <div>
                                <div className="text-xs text-gray-500">
                                    Duration
                                </div>
                                <div className="text-sm font-medium">
                                    {contract.durations} months
                                </div>
                            </div>
                            <div>
                                <div className="text-xs text-gray-500">
                                    Start Date
                                </div>
                                <div className="text-sm font-medium">
                                    {contract.tender_start_date ?? "-"}
                                </div>
                            </div>
                            <div>
                                <div className="text-xs text-gray-500">
                                    End Date
                                </div>
                                <div className="text-sm font-medium">
                                    {contract.tender_end_date ?? "-"}
                                </div>
                            </div>
                            <div className="md:col-span-2">
                                <div className="text-xs text-gray-500">
                                    Product Description
                                </div>
                                <div className="text-sm font-medium">
                                    {contract.product_description ?? "-"}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="rounded-lg border bg-white p-4">
                        <div className="text-sm font-medium">
                            Payment Summary
                        </div>
                        <div className="mt-4 space-y-3 text-sm">
                            <div className="flex items-center justify-between">
                                <span>Contract Amount</span>
                                <span>RM {summary.subtotal.toFixed(2)}</span>
                            </div>
                            {charges.length === 0 ? (
                                <div className="flex items-center justify-between">
                                    <span>Charges</span>
                                    <span>RM 0.00</span>
                                </div>
                            ) : (
                                charges.map((charge) => (
                                    <div
                                        key={charge.charges_id}
                                        className="flex items-center justify-between"
                                    >
                                        <span>{charge.charges_name}</span>
                                        <span>
                                            RM {charge.amount.toFixed(2)}
                                        </span>
                                    </div>
                                ))
                            )}
                            <div className="border-t pt-3 flex items-center justify-between font-semibold">
                                <span>Total Payment</span>
                                <span>
                                    RM {summary.total_payment.toFixed(2)}
                                </span>
                            </div>
                        </div>
                        <Button
                            className="mt-4 w-full"
                            variant="default"
                            disabled={!canPay || isPaid}
                            onClick={() =>
                                router.post(
                                    route(
                                        "contracts.pay",
                                        contract.tender_compartment_id,
                                    ),
                                )
                            }
                        >
                            {isPaid ? "Paid" : "Pay Now"}
                        </Button>
                        {!canPay && !isPaid ? (
                            <div className="mt-3 text-xs text-gray-500">
                                Only the assigned vendor can pay this contract.
                            </div>
                        ) : null}
                    </div>
                </div>

                {isPaid ? (
                    <div className="mt-4 rounded-lg border bg-white p-4">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <div className="text-sm font-medium">
                                    Compartment Stocks
                                </div>
                                <div className="text-xs text-gray-500">
                                    Manage stock batches and products assigned
                                    to this paid compartment.
                                </div>
                            </div>
                            {canManageStocks ? (
                                <Button
                                    type="button"
                                    onClick={() => setCreateOpen(true)}
                                >
                                    Create
                                </Button>
                            ) : null}
                        </div>

                        <div className="mt-4 space-y-4">
                            {stocks.length === 0 ? (
                                <div className="rounded-md border border-dashed p-4 text-sm text-gray-500">
                                    No compartment stock records created yet.
                                </div>
                            ) : (
                                stocks.map((stock) => (
                                    <div
                                        key={stock.compartment_stock_id}
                                        className="rounded-md border"
                                    >
                                        <div className="flex flex-col gap-2 border-b bg-gray-50 px-4 py-3 text-sm md:flex-row md:items-center md:justify-between">
                                            <div className="flex items-center justify-between gap-2">
                                                <div className="font-medium">
                                                    Stock{" "}
                                                    {stock.compartment_stock_id.slice(
                                                        0,
                                                        8,
                                                    )}
                                                </div>
                                                {stock.status === "prepared" ? (
                                                    <div className="flex items-center gap-1">
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                setQrStock(
                                                                    stock,
                                                                )
                                                            }
                                                        >
                                                            <QrCode className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                deleteStock(
                                                                    stock.compartment_stock_id,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                ) : null}
                                            </div>
                                            <div className="flex flex-col gap-1 text-xs text-gray-600 md:flex-row md:items-center md:gap-4">
                                                <span className="capitalize">
                                                    Status: {stock.status}
                                                </span>
                                                <span>
                                                    Created:{" "}
                                                    {stock.created_at
                                                        ? format(
                                                              new Date(
                                                                  stock.created_at,
                                                              ),
                                                              "d MMM, y",
                                                          )
                                                        : "-"}
                                                </span>
                                            </div>
                                        </div>
                                        <div className="overflow-x-auto">
                                            <table className="min-w-full text-sm">
                                                <thead className="bg-white text-left text-xs uppercase tracking-wide text-gray-500">
                                                    <tr>
                                                        <th className="px-4 py-3">
                                                            Product
                                                        </th>
                                                        <th className="px-4 py-3">
                                                            Quantity
                                                        </th>
                                                        <th className="px-4 py-3">
                                                            Expiry Date
                                                        </th>
                                                        {canManageStocks ? (
                                                            <th className="px-4 py-3 text-right">
                                                                Actions
                                                            </th>
                                                        ) : null}
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {stock.products.length ===
                                                    0 ? (
                                                        <tr>
                                                            <td
                                                                colSpan={
                                                                    canManageStocks
                                                                        ? 4
                                                                        : 3
                                                                }
                                                                className="px-4 py-4 text-sm text-gray-500"
                                                            >
                                                                No stock
                                                                products added.
                                                            </td>
                                                        </tr>
                                                    ) : (
                                                        stock.products.map(
                                                            (product) => (
                                                                <tr
                                                                    key={
                                                                        product.compartment_stock_product_id
                                                                    }
                                                                    className="border-t"
                                                                >
                                                                    <td className="px-4 py-3">
                                                                        <div className="font-medium">
                                                                            {
                                                                                product.product_name
                                                                            }
                                                                        </div>
                                                                        <div className="text-xs text-gray-500">
                                                                            {[
                                                                                product.product_code,
                                                                                product.product_sku,
                                                                            ]
                                                                                .filter(
                                                                                    Boolean,
                                                                                )
                                                                                .join(
                                                                                    " / ",
                                                                                ) ||
                                                                                "-"}
                                                                        </div>
                                                                    </td>
                                                                    <td className="px-4 py-3">
                                                                        {
                                                                            product.quantity
                                                                        }
                                                                    </td>
                                                                    <td className="px-4 py-3">
                                                                        {product.expiry_date ??
                                                                            "-"}
                                                                    </td>
                                                                    {canManageStocks ? (
                                                                        <td className="px-4 py-3">
                                                                            <div className="flex items-center justify-end gap-1">
                                                                                {canManageStockProducts(
                                                                                    stock.status,
                                                                                ) ? (
                                                                                    <>
                                                                                        <Button
                                                                                            type="button"
                                                                                            variant="ghost"
                                                                                            size="icon"
                                                                                            onClick={() =>
                                                                                                openEditProduct(
                                                                                                    stock,
                                                                                                    product,
                                                                                                )
                                                                                            }
                                                                                        >
                                                                                            <Pencil className="h-4 w-4" />
                                                                                        </Button>
                                                                                        <Button
                                                                                            type="button"
                                                                                            variant="ghost"
                                                                                            size="icon"
                                                                                            onClick={() =>
                                                                                                deleteStockProduct(
                                                                                                    stock.compartment_stock_id,
                                                                                                    product.compartment_stock_product_id,
                                                                                                )
                                                                                            }
                                                                                        >
                                                                                            <Trash2 className="h-4 w-4" />
                                                                                        </Button>
                                                                                    </>
                                                                                ) : null}
                                                                            </div>
                                                                        </td>
                                                                    ) : null}
                                                                </tr>
                                                            ),
                                                        )
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                ) : null}

                <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                    <DialogContent className="sm:max-w-3xl">
                        <DialogHeader>
                            <DialogTitle>Create Compartment Stock</DialogTitle>
                            <DialogDescription>
                                Add a stock batch and the products placed in
                                this paid compartment.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="stock-status">Status</Label>
                                <Select
                                    value={data.status}
                                    onValueChange={(value) =>
                                        setData("status", value)
                                    }
                                >
                                    <SelectTrigger
                                        id="stock-status"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Select status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="prepared">
                                            Prepared
                                        </SelectItem>
                                        <SelectItem value="removed">
                                            Remove
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.status ? (
                                    <div className="text-xs text-red-600">
                                        {errors.status}
                                    </div>
                                ) : null}
                            </div>

                            <div className="flex items-center justify-between">
                                <div className="text-sm font-medium">
                                    Stock Products
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={addItem}
                                >
                                    <Plus className="mr-2 h-4 w-4" />
                                    Add Item
                                </Button>
                            </div>

                            <div className="space-y-4">
                                {data.items.map((item, index) => (
                                    <div
                                        key={`stock-item-${index}`}
                                        className="grid gap-4 rounded-md border p-4 md:grid-cols-[2fr_1fr_1fr_auto]"
                                    >
                                        <div className="grid gap-2">
                                            <Label>Product</Label>
                                            <Select
                                                value={item.product_id}
                                                onValueChange={(value) =>
                                                    updateItem(
                                                        index,
                                                        "product_id",
                                                        value,
                                                    )
                                                }
                                            >
                                                <SelectTrigger className="w-full">
                                                    <SelectValue placeholder="Select product" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {productOptions.map(
                                                        (product) => (
                                                            <SelectItem
                                                                key={
                                                                    product.value
                                                                }
                                                                value={
                                                                    product.value
                                                                }
                                                            >
                                                                {product.label}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                            {errors[
                                                `items.${index}.product_id`
                                            ] ? (
                                                <div className="text-xs text-red-600">
                                                    {
                                                        errors[
                                                            `items.${index}.product_id`
                                                        ]
                                                    }
                                                </div>
                                            ) : null}
                                        </div>

                                        <div className="grid gap-2">
                                            <Label>Quantity</Label>
                                            <Input
                                                type="number"
                                                min={1}
                                                value={item.quantity}
                                                onChange={(event) =>
                                                    updateItem(
                                                        index,
                                                        "quantity",
                                                        Number(
                                                            event.target.value,
                                                        ) || 0,
                                                    )
                                                }
                                            />
                                            {errors[
                                                `items.${index}.quantity`
                                            ] ? (
                                                <div className="text-xs text-red-600">
                                                    {
                                                        errors[
                                                            `items.${index}.quantity`
                                                        ]
                                                    }
                                                </div>
                                            ) : null}
                                        </div>

                                        <div className="grid gap-2">
                                            <Label>Expiry Date</Label>
                                            <Input
                                                type="date"
                                                value={item.expiry_date}
                                                onChange={(event) =>
                                                    updateItem(
                                                        index,
                                                        "expiry_date",
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            {errors[
                                                `items.${index}.expiry_date`
                                            ] ? (
                                                <div className="text-xs text-red-600">
                                                    {
                                                        errors[
                                                            `items.${index}.expiry_date`
                                                        ]
                                                    }
                                                </div>
                                            ) : null}
                                        </div>

                                        <div className="flex items-end">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    removeItem(index)
                                                }
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            {errors.items ? (
                                <div className="text-xs text-red-600">
                                    {errors.items}
                                </div>
                            ) : null}

                            {productOptions.length === 0 ? (
                                <div className="rounded-md border border-dashed p-3 text-xs text-gray-500">
                                    No active products available to add yet.
                                </div>
                            ) : null}
                        </div>

                        <DialogFooter showCloseButton>
                            <Button
                                type="button"
                                onClick={submitStock}
                                disabled={
                                    processing || productOptions.length === 0
                                }
                            >
                                Create
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <Dialog
                    open={editTarget !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            closeEditProduct();
                        }
                    }}
                >
                    <DialogContent className="sm:max-w-xl">
                        <DialogHeader>
                            <DialogTitle>Edit Stock Product</DialogTitle>
                            <DialogDescription>
                                Update the product, quantity, or expiry date for
                                this compartment stock item.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-4">
                            <div className="grid gap-2">
                                <Label>Product</Label>
                                <Select
                                    value={editData.product_id}
                                    onValueChange={(value) =>
                                        setEditData("product_id", value)
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Select product" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {productOptions.map((product) => (
                                            <SelectItem
                                                key={product.value}
                                                value={product.value}
                                            >
                                                {product.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {editErrors.product_id ? (
                                    <div className="text-xs text-red-600">
                                        {editErrors.product_id}
                                    </div>
                                ) : null}
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label>Quantity</Label>
                                    <Input
                                        type="number"
                                        min={1}
                                        value={editData.quantity}
                                        onChange={(event) =>
                                            setEditData(
                                                "quantity",
                                                Number(event.target.value) || 0,
                                            )
                                        }
                                    />
                                    {editErrors.quantity ? (
                                        <div className="text-xs text-red-600">
                                            {editErrors.quantity}
                                        </div>
                                    ) : null}
                                </div>

                                <div className="grid gap-2">
                                    <Label>Expiry Date</Label>
                                    <Input
                                        type="date"
                                        value={editData.expiry_date}
                                        onChange={(event) =>
                                            setEditData(
                                                "expiry_date",
                                                event.target.value,
                                            )
                                        }
                                        min={
                                            new Date()
                                                .toISOString()
                                                .split("T")[0]
                                        }
                                    />
                                    {editErrors.expiry_date ? (
                                        <div className="text-xs text-red-600">
                                            {editErrors.expiry_date}
                                        </div>
                                    ) : null}
                                </div>
                            </div>
                        </div>

                        <DialogFooter showCloseButton>
                            <Button
                                type="button"
                                onClick={submitEditProduct}
                                disabled={
                                    editProcessing ||
                                    productOptions.length === 0 ||
                                    !editTarget ||
                                    !canManageStockProducts(
                                        editTarget.stockStatus,
                                    )
                                }
                            >
                                Save Changes
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <Dialog
                    open={qrStock !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setQrStock(null);
                        }
                    }}
                >
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Compartment Stock QR Code</DialogTitle>
                            <DialogDescription>
                                Scan this QR code to identify the compartment
                                stock record.
                            </DialogDescription>
                        </DialogHeader>

                        {qrStock ? (
                            <div className="flex flex-col items-center gap-4">
                                <div className="rounded-lg border bg-white p-4">
                                    <img
                                        src={`https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=${encodeURIComponent(qrStock.compartment_stock_id)}`}
                                        alt={`QR code for ${qrStock.compartment_stock_id}`}
                                        className="h-60 w-60"
                                    />
                                </div>
                            </div>
                        ) : null}
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
