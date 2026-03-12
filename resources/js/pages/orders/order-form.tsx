import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { Order } from "@/types";
import { router } from "@inertiajs/react";
import { useForm } from "react-hook-form";
import { toast } from "sonner";
import { useEffect, useMemo, useState } from "react";
import { Trash2 } from "lucide-react";

type OrderFormValues = {
    user_id: string;
    order_no: string;
    order_date: string;
    total_price: number;
    total_tax: number;
    total_discount: number;
    total_payment: number;
    shipping_method: string;
    shipping_address: string;
    billing_address: string;
    discount_code?: string | null;
    wallet_credit_used?: number | null;
    order_status: Order["order_status"];
    order_items?: Array<{
        product_id: string;
        product_name: string;
        quantity: number;
        uom?: string | null;
        unit_price: number;
        tax?: number;
        discount?: number;
        tax_rate?: number | null;
        discount_type?: "P" | "F" | null;
        discount_value?: number | null;
        tax_per_unit?: number | null;
        discount_per_unit?: number | null;
        total_price: number;
    }>;
};

export function OrderForm({
    mode,
    order,
}: {
    mode: "create" | "edit";
    order?: Order;
}) {
    const methods = useForm<OrderFormValues>({
        defaultValues: {
            user_id: order?.user_id ?? "",
            order_no: order?.order_no ?? "",
            order_date:
                order?.order_date?.slice(0, 10) ??
                new Date().toISOString().slice(0, 10),
            total_price: Number(order?.total_price ?? 0),
            total_tax: Number(order?.total_tax ?? 0),
            total_discount: Number(order?.total_discount ?? 0),
            total_payment: Number(order?.total_payment ?? 0),
            shipping_method: order?.shipping_method ?? "",
            shipping_address: order?.shipping_address ?? "",
            billing_address: order?.billing_address ?? "",
            discount_code: order?.discount_code ?? "",
            wallet_credit_used: order?.wallet_credit_used
                ? Number(order.wallet_credit_used)
                : 0,
            order_status: order?.order_status ?? "pending",
            order_items: (order?.order_items ?? []).map((item) => {
                const quantity = Number(item.quantity ?? 1);
                const tax = Number(item.tax ?? 0);
                const discount = Number(item.discount ?? 0);
                return {
                    product_id: item.product_id,
                    product_name: item.product?.product_name ?? "",
                    quantity,
                    uom: item.uom ?? item.product?.uom ?? "unit",
                    unit_price: Number(item.unit_price ?? 0),
                    tax,
                    discount,
                    tax_per_unit:
                        quantity > 0
                            ? Number((tax / quantity).toFixed(2))
                            : tax,
                    discount_per_unit:
                        quantity > 0
                            ? Number((discount / quantity).toFixed(2))
                            : discount,
                    total_price: Number(item.total_price ?? 0),
                };
            }),
        },
        shouldUnregister: false,
    });

    const [userQuery, setUserQuery] = useState("");
    const [userOptions, setUserOptions] = useState<
        Array<{ user_id: string; name: string; email: string }>
    >([]);
    const [productQuery, setProductQuery] = useState("");
    const [productOptions, setProductOptions] = useState<
        Array<{
            product_id: string;
            product_name: string;
            product_code?: string;
            product_sku?: string;
            uom?: string;
            unit_price: number;
            tax?: number;
            discount?: number;
            tax_rate?: number;
            discount_type?: "P" | "F" | null;
            discount_value?: number | null;
        }>
    >([]);

    useEffect(() => {
        const t = setTimeout(async () => {
            const url =
                userQuery.trim() === ""
                    ? "/users/options"
                    : `/users/options?q=${encodeURIComponent(userQuery)}`;
            const res = await fetch(url, { method: "GET" });
            const data = await res.json();
            setUserOptions(data.data ?? []);
        }, 250);
        return () => clearTimeout(t);
    }, [userQuery]);

    useEffect(() => {
        const t = setTimeout(async () => {
            const url =
                productQuery.trim() === ""
                    ? "/product-discounts/products/search"
                    : `/product-discounts/products/search?q=${encodeURIComponent(productQuery)}`;
            const res = await fetch(url, { method: "GET" });
            const data = await res.json();
            setProductOptions(data.data ?? []);
        }, 250);
        return () => clearTimeout(t);
    }, [productQuery]);

    const items = methods.watch("order_items") ?? [];

    const totals = useMemo(() => {
        const totalPrice = items.reduce(
            (sum, it) =>
                sum + Number(it.quantity ?? 0) * Number(it.unit_price ?? 0),
            0,
        );
        const totalTax = items.reduce(
            (sum, it) => sum + Number(it.tax ?? 0),
            0,
        );
        const totalDiscount = items.reduce(
            (sum, it) => sum + Number(it.discount ?? 0),
            0,
        );
        const totalPayment = totalPrice + totalTax - totalDiscount;
        return {
            totalPrice,
            totalTax,
            totalDiscount,
            totalPayment,
        };
    }, [items]);

    useEffect(() => {
        methods.setValue("total_price", Number(totals.totalPrice.toFixed(2)));
        methods.setValue("total_tax", Number(totals.totalTax.toFixed(2)));
        methods.setValue(
            "total_discount",
            Number(totals.totalDiscount.toFixed(2)),
        );
        methods.setValue(
            "total_payment",
            Number(totals.totalPayment.toFixed(2)),
        );
    }, [totals]);

    const handleSubmit = (values: OrderFormValues) => {
        return new Promise<void>((resolve) => {
            if (mode === "create") {
                router.post(route("orders.store"), values as any, {
                    onSuccess: () => {
                        toast.success("Order created successfully");
                        router.visit(route("orders.index"));
                    },
                    onError: (errors: Record<string, string>) => {
                        toast.error("Order creation failed");
                        Object.values(errors).forEach((error) =>
                            toast.error(error),
                        );
                    },
                    onFinish: () => resolve(),
                });
                return;
            }

            router.post(
                route("orders.update", order!.order_id),
                { _method: "put", ...values } as any,
                {
                    onSuccess: () => {
                        toast.success("Order updated successfully");
                        router.visit(route("orders.index"));
                    },
                    onError: (errors: Record<string, string>) => {
                        toast.error("Failed to update order");
                        Object.values(errors).forEach((error) =>
                            toast.error(error),
                        );
                    },
                    onFinish: () => resolve(),
                },
            );
        });
    };

    return (
        <form
            onSubmit={methods.handleSubmit(handleSubmit)}
            className="bg-white p-6 rounded-md shadow-md"
        >
            <div className="flex flex-col md:grid md:grid-cols-2 gap-4">
                <div className="flex flex-col gap-2 relative">
                    <Label htmlFor="user_search">User</Label>
                    <Input
                        id="user_search"
                        type="text"
                        placeholder="Search users by name or email"
                        value={userQuery}
                        onChange={(e) => setUserQuery(e.target.value)}
                    />
                    <div className="border rounded-md mt-1 max-h-48 overflow-auto bg-white">
                        {userOptions.map((u) => (
                            <button
                                key={u.user_id}
                                type="button"
                                className="w-full text-left px-3 py-2 hover:bg-gray-100"
                                onClick={() => {
                                    methods.setValue("user_id", u.user_id);
                                    setUserQuery(`${u.name} (${u.email})`);
                                }}
                            >
                                {u.name} ({u.email})
                            </button>
                        ))}
                    </div>
                    <Input
                        id="user_id"
                        type="hidden"
                        {...methods.register("user_id", { required: true })}
                    />
                </div>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="order_no">Order No</Label>
                    <Input
                        id="order_no"
                        type="text"
                        required
                        maxLength={20}
                        {...methods.register("order_no")}
                    />
                </div>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="order_date">Order Date</Label>
                    <Input
                        id="order_date"
                        type="date"
                        required
                        {...methods.register("order_date")}
                    />
                </div>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="total_price">Total Price</Label>
                    <Input
                        id="total_price"
                        type="number"
                        step="0.01"
                        min={0}
                        required
                        {...methods.register("total_price", {
                            valueAsNumber: true,
                        })}
                    />
                </div>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="total_tax">Total Tax</Label>
                    <Input
                        id="total_tax"
                        type="number"
                        step="0.01"
                        min={0}
                        required
                        {...methods.register("total_tax", {
                            valueAsNumber: true,
                        })}
                    />
                </div>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="total_discount">Total Discount</Label>
                    <Input
                        id="total_discount"
                        type="number"
                        step="0.01"
                        min={0}
                        required
                        {...methods.register("total_discount", {
                            valueAsNumber: true,
                        })}
                    />
                </div>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="total_payment">Total Payment</Label>
                    <Input
                        id="total_payment"
                        type="number"
                        step="0.01"
                        min={0}
                        required
                        {...methods.register("total_payment", {
                            valueAsNumber: true,
                        })}
                    />
                </div>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="shipping_method">Shipping Method</Label>
                    <Input
                        id="shipping_method"
                        type="text"
                        maxLength={50}
                        {...methods.register("shipping_method")}
                    />
                </div>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="shipping_address">Shipping Address</Label>
                    <Input
                        id="shipping_address"
                        type="text"
                        maxLength={255}
                        {...methods.register("shipping_address")}
                    />
                </div>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="billing_address">Billing Address</Label>
                    <Input
                        id="billing_address"
                        type="text"
                        maxLength={255}
                        {...methods.register("billing_address")}
                    />
                </div>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="discount_code">Discount Code</Label>
                    <Input
                        id="discount_code"
                        type="text"
                        maxLength={50}
                        {...methods.register("discount_code")}
                    />
                </div>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="wallet_credit_used">
                        Wallet Credit Used
                    </Label>
                    <Input
                        id="wallet_credit_used"
                        type="number"
                        step="0.01"
                        min={0}
                        {...methods.register("wallet_credit_used", {
                            valueAsNumber: true,
                        })}
                    />
                </div>
                <div className="flex flex-col gap-2">
                    <Label htmlFor="order_status">Order Status</Label>
                    <Input
                        id="order_status"
                        type="text"
                        required
                        {...methods.register("order_status")}
                    />
                </div>

                <div className="md:col-span-2">
                    <div className="flex items-end gap-2">
                        <div className="flex-1">
                            <Label htmlFor="product_search">Add Product</Label>
                            <Input
                                id="product_search"
                                type="text"
                                placeholder="Search products"
                                value={productQuery}
                                onChange={(e) =>
                                    setProductQuery(e.target.value)
                                }
                            />
                        </div>
                    </div>
                    <div className="border rounded-md mt-2 max-h-48 overflow-auto bg-white">
                        {productOptions.map((p) => (
                            <button
                                key={p.product_id}
                                type="button"
                                className="w-full text-left px-3 py-2 hover:bg-gray-100"
                                onClick={() => {
                                    const quantity = 1;
                                    const unitPrice = Number(p.unit_price ?? 0);
                                    const taxPerUnit = Number(p.tax ?? 0);
                                    const discountPerUnit = Number(
                                        p.discount ?? 0,
                                    );
                                    const tax = Number(
                                        (taxPerUnit * quantity).toFixed(2),
                                    );
                                    const discount = Number(
                                        (discountPerUnit * quantity).toFixed(2),
                                    );
                                    const totalPrice = Number(
                                        (
                                            quantity * unitPrice +
                                            tax -
                                            discount
                                        ).toFixed(2),
                                    );
                                    const next = [
                                        ...(methods.getValues("order_items") ??
                                            []),
                                        {
                                            product_id: p.product_id,
                                            product_name: p.product_name,
                                            quantity,
                                            uom: p.uom ?? "unit",
                                            unit_price: unitPrice,
                                            tax,
                                            discount,
                                            tax_rate: p.tax_rate ?? null,
                                            discount_type:
                                                p.discount_type ?? null,
                                            discount_value:
                                                p.discount_value ?? null,
                                            tax_per_unit: taxPerUnit,
                                            discount_per_unit: discountPerUnit,
                                            total_price: totalPrice,
                                        },
                                    ];
                                    methods.setValue("order_items", next);
                                    setProductQuery("");
                                }}
                            >
                                {p.product_name}
                            </button>
                        ))}
                    </div>

                    <div className="mt-4 border rounded-md">
                        <div className="grid grid-cols-12 gap-2 p-2 bg-gray-50">
                            <div className="col-span-4">Product</div>
                            <div className="col-span-1">Qty</div>
                            <div className="col-span-1">UOM</div>
                            <div className="col-span-2">Unit Price</div>
                            <div className="col-span-1">Tax</div>
                            <div className="col-span-1">Discount</div>
                            <div className="col-span-1">Total</div>
                            <div className="col-span-1"></div>
                        </div>
                        {(methods.watch("order_items") ?? []).map(
                            (it, idx, arr) => {
                                type OrderItem = NonNullable<
                                    typeof arr
                                >[number];
                                const onValueChange = (
                                    field: keyof OrderItem,
                                    v: number | string,
                                ) => {
                                    const next = [...arr] as OrderItem[];
                                    const item = { ...next[idx] } as OrderItem;
                                    switch (field) {
                                        case "quantity":
                                            item.quantity = Number(v);
                                            break;
                                        case "uom":
                                            item.uom = String(v);
                                            break;
                                        case "unit_price":
                                            item.unit_price = Number(v);
                                            break;
                                        case "tax":
                                            item.tax = Number(v);
                                            break;
                                        case "discount":
                                            item.discount = Number(v);
                                            break;
                                        case "uom":
                                            item.uom = String(v);
                                            break;
                                        default:
                                            break;
                                    }
                                    const q = Number(item.quantity ?? 0);
                                    const up = Number(item.unit_price ?? 0);
                                    const round2 = (n: number) =>
                                        Number(n.toFixed(2));

                                    if (
                                        field === "quantity" ||
                                        field === "unit_price"
                                    ) {
                                        const taxRate = Number(
                                            item.tax_rate ?? 0,
                                        );
                                        if (taxRate > 0) {
                                            item.tax_per_unit = round2(
                                                up * (taxRate / 100),
                                            );
                                            item.tax = round2(
                                                Number(item.tax_per_unit) * q,
                                            );
                                        } else if (item.tax_per_unit != null) {
                                            item.tax = round2(
                                                Number(item.tax_per_unit) * q,
                                            );
                                        }

                                        const discountValue =
                                            item.discount_value;
                                        if (
                                            item.discount_type &&
                                            discountValue != null
                                        ) {
                                            const perUnitDiscount =
                                                item.discount_type === "P"
                                                    ? up *
                                                      (Number(discountValue) /
                                                          100)
                                                    : Number(discountValue);
                                            item.discount_per_unit = round2(
                                                Math.min(
                                                    Math.max(
                                                        perUnitDiscount,
                                                        0,
                                                    ),
                                                    up,
                                                ),
                                            );
                                            item.discount = round2(
                                                Number(item.discount_per_unit) *
                                                    q,
                                            );
                                        } else if (
                                            item.discount_per_unit != null
                                        ) {
                                            item.discount = round2(
                                                Number(item.discount_per_unit) *
                                                    q,
                                            );
                                        }
                                    }

                                    if (field === "tax") {
                                        item.tax_per_unit =
                                            q > 0
                                                ? round2(
                                                      Number(item.tax ?? 0) / q,
                                                  )
                                                : round2(Number(item.tax ?? 0));
                                    }
                                    if (field === "discount") {
                                        item.discount_per_unit =
                                            q > 0
                                                ? round2(
                                                      Number(
                                                          item.discount ?? 0,
                                                      ) / q,
                                                  )
                                                : round2(
                                                      Number(
                                                          item.discount ?? 0,
                                                      ),
                                                  );
                                    }

                                    const tax = Number(item.tax ?? 0);
                                    const disc = Number(item.discount ?? 0);
                                    item.total_price = Number(
                                        (q * up + tax - disc).toFixed(2),
                                    );
                                    next[idx] = item;
                                    methods.setValue("order_items", next, {
                                        shouldDirty: true,
                                        shouldValidate: false,
                                    });
                                };
                                return (
                                    <div
                                        key={`${it.product_id}-${idx}`}
                                        className="grid grid-cols-12 gap-2 p-2 border-t"
                                    >
                                        <div className="col-span-4">
                                            <Input
                                                value={it.product_name}
                                                readOnly
                                            />
                                        </div>
                                        <div className="col-span-1">
                                            <Input
                                                type="number"
                                                min={1}
                                                value={it.quantity}
                                                onChange={(e) =>
                                                    onValueChange(
                                                        "quantity",
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                        <div className="col-span-1">
                                            <Input
                                                value={it.uom ?? ""}
                                                readOnly
                                            />
                                        </div>
                                        <div className="col-span-2">
                                            <Input
                                                type="number"
                                                step="0.01"
                                                min={0}
                                                value={it.unit_price}
                                                onChange={(e) =>
                                                    onValueChange(
                                                        "unit_price",
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                        <div className="col-span-1">
                                            <Input
                                                type="number"
                                                step="0.01"
                                                min={0}
                                                value={it.tax ?? 0}
                                                onChange={(e) =>
                                                    onValueChange(
                                                        "tax",
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                        <div className="col-span-1">
                                            <Input
                                                type="number"
                                                step="0.01"
                                                min={0}
                                                value={it.discount ?? 0}
                                                onChange={(e) =>
                                                    onValueChange(
                                                        "discount",
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                        <div className="col-span-1">
                                            <Input
                                                type="number"
                                                step="0.01"
                                                min={0}
                                                value={it.total_price}
                                                readOnly
                                            />
                                        </div>
                                        <div className="col-span-1 flex justify-end">
                                            <Button
                                                type="button"
                                                size={"sm"}
                                                variant="secondary"
                                                onClick={() => {
                                                    const next = [
                                                        ...(methods.getValues(
                                                            "order_items",
                                                        ) ?? []),
                                                    ];
                                                    next.splice(idx, 1);
                                                    methods.setValue(
                                                        "order_items",
                                                        next,
                                                    );
                                                }}
                                            >
                                                <Trash2 size={16} />
                                            </Button>
                                        </div>
                                    </div>
                                );
                            },
                        )}
                    </div>
                </div>
                <div className="flex flex-end md:col-span-2 justify-end gap-2">
                    <Button
                        size={"sm"}
                        type="button"
                        variant="secondary"
                        onClick={() => history.back()}
                    >
                        Cancel
                    </Button>
                    <Button
                        size={"sm"}
                        type="submit"
                        disabled={methods.formState.isSubmitting}
                    >
                        {methods.formState.isSubmitting
                            ? "Saving..."
                            : mode === "create"
                              ? "Save"
                              : "Update"}
                    </Button>
                </div>
            </div>
        </form>
    );
}
