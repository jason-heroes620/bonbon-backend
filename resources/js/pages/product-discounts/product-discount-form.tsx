import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { ProductDiscount } from "@/types";
import axios from "axios";
import { router } from "@inertiajs/react";
import { useEffect, useMemo, useRef, useState } from "react";
import { Controller, useForm } from "react-hook-form";
import { toast } from "sonner";
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";

const discountType = [
    { value: "P", label: "Percentage" },
    { value: "F", label: "Fixed Amount" },
];

type ProductSearchItem = {
    product_id: string;
    product_name: string;
    product_code?: string | null;
    product_sku?: string | null;
};

type ProductDiscountFormValues = {
    product_id: string;
    discount_type: "P" | "F";
    discount_amount: number;
    discount_start_date: string;
    discount_end_date: string;
    is_active: boolean;
};

function formatProductLabel(p: ProductSearchItem) {
    const parts = [
        p.product_code ? `Code: ${p.product_code}` : null,
        p.product_sku ? `SKU: ${p.product_sku}` : null,
    ].filter(Boolean);
    return parts.length
        ? `${p.product_name} (${parts.join(" • ")})`
        : p.product_name;
}

export function ProductDiscountForm({
    mode,
    productDiscount,
}: {
    mode: "create" | "edit";
    productDiscount?: ProductDiscount;
}) {
    const [search, setSearch] = useState<string>(
        productDiscount?.product?.product_name ?? "",
    );
    const [results, setResults] = useState<ProductSearchItem[]>([]);
    const [isOpen, setIsOpen] = useState(false);
    const latestRequestIdRef = useRef(0);

    const methods = useForm<ProductDiscountFormValues>({
        defaultValues: {
            product_id: productDiscount?.product_id ?? "",
            discount_type: productDiscount?.discount_type ?? "P",
            discount_amount: Number(productDiscount?.discount_amount ?? 0),
            discount_start_date: productDiscount?.discount_start_date ?? "",
            discount_end_date: productDiscount?.discount_end_date ?? "",
            is_active: productDiscount
                ? Boolean(productDiscount.is_active)
                : true,
        },
        shouldUnregister: false,
    });

    const selectedProductId = methods.watch("product_id");

    const selectedProductLabel = useMemo(() => {
        if (
            productDiscount?.product &&
            productDiscount.product_id === selectedProductId
        ) {
            return productDiscount.product.product_name;
        }
        const match = results.find((r) => r.product_id === selectedProductId);
        return match ? formatProductLabel(match) : "";
    }, [productDiscount, results, selectedProductId]);

    useEffect(() => {
        if (!isOpen) return;
        const q = search.trim();
        const requestId = ++latestRequestIdRef.current;

        const timer = window.setTimeout(async () => {
            try {
                const res = await axios.get(
                    route("product_discounts.products.search"),
                    {
                        params: { q },
                    },
                );
                if (latestRequestIdRef.current !== requestId) return;
                setResults(res.data?.data ?? []);
            } catch {
                if (latestRequestIdRef.current !== requestId) return;
                setResults([]);
            }
        }, 300);

        return () => window.clearTimeout(timer);
    }, [search, isOpen]);

    const handleSubmit = (values: ProductDiscountFormValues) => {
        const payload = {
            ...values,
            discount_amount: Number(values.discount_amount ?? 0),
        };

        return new Promise<void>((resolve) => {
            if (mode === "create") {
                router.post(route("product_discounts.store"), payload as any, {
                    onSuccess: () => {
                        toast.success("Product discount created successfully");
                        router.visit(route("product_discounts.index"));
                    },
                    onError: (errors: Record<string, string>) => {
                        toast.error("Failed to create product discount");
                        Object.values(errors).forEach((error) =>
                            toast.error(error),
                        );
                    },
                    onFinish: () => resolve(),
                });
                return;
            }

            router.post(
                route(
                    "product_discounts.update",
                    productDiscount!.product_discount_id,
                ),
                { _method: "put", ...payload } as any,
                {
                    onSuccess: () => {
                        toast.success("Product discount updated successfully");
                        router.visit(route("product_discounts.index"));
                    },
                    onError: (errors: Record<string, string>) => {
                        toast.error("Failed to update product discount");
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
                <div className="flex flex-col gap-2 md:col-span-2">
                    <Label htmlFor="product_search">Product</Label>
                    <div className="relative">
                        <Input
                            id="product_search"
                            type="text"
                            placeholder="Search products..."
                            value={search}
                            onFocus={() => setIsOpen(true)}
                            onBlur={() => {
                                window.setTimeout(() => setIsOpen(false), 150);
                            }}
                            onChange={(e) => {
                                setSearch(e.target.value);
                                setIsOpen(true);
                            }}
                            className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        />
                        {isOpen && results.length > 0 ? (
                            <div className="absolute z-50 mt-1 w-full rounded-md border bg-white shadow">
                                <div className="max-h-64 overflow-auto">
                                    {results.map((p) => (
                                        <button
                                            key={p.product_id}
                                            type="button"
                                            className="w-full px-3 py-2 text-left hover:bg-gray-50"
                                            onMouseDown={(e) =>
                                                e.preventDefault()
                                            }
                                            onClick={() => {
                                                methods.setValue(
                                                    "product_id",
                                                    p.product_id,
                                                    {
                                                        shouldDirty: true,
                                                        shouldValidate: true,
                                                    },
                                                );
                                                setSearch(p.product_name);
                                                setIsOpen(false);
                                            }}
                                        >
                                            <div className="font-medium">
                                                {p.product_name}
                                            </div>
                                            <div className="text-xs text-gray-500">
                                                {p.product_code
                                                    ? `Code: ${p.product_code}`
                                                    : ""}
                                                {p.product_code && p.product_sku
                                                    ? " • "
                                                    : ""}
                                                {p.product_sku
                                                    ? `SKU: ${p.product_sku}`
                                                    : ""}
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            </div>
                        ) : null}
                    </div>
                    <input
                        type="hidden"
                        {...methods.register("product_id", { required: true })}
                    />
                    {selectedProductId ? (
                        <div className="text-xs text-gray-600">
                            Selected:{" "}
                            {selectedProductLabel || selectedProductId}
                        </div>
                    ) : null}
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="discount_type">Discount Type</Label>
                    <Controller
                        name="discount_type"
                        control={methods.control}
                        rules={{ required: true }}
                        render={({ field }) => (
                            <Select
                                value={field.value ?? ""}
                                onValueChange={field.onChange}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Select parent category" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {discountType.map((item) => (
                                            <SelectItem
                                                key={item.value}
                                                value={item.value}
                                            >
                                                {item.label}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        )}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="discount_amount">Discount Amount</Label>
                    <Input
                        id="discount_amount"
                        type="number"
                        step="0.01"
                        min={0}
                        required
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("discount_amount", {
                            valueAsNumber: true,
                        })}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="discount_start_date">Start Date</Label>
                    <Input
                        id="discount_start_date"
                        type="date"
                        required
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("discount_start_date")}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="discount_end_date">End Date</Label>
                    <Input
                        id="discount_end_date"
                        type="date"
                        required
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("discount_end_date")}
                    />
                </div>

                <div className="flex items-center space-x-2">
                    <input
                        type="checkbox"
                        id="is_active"
                        className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                        {...methods.register("is_active")}
                    />
                    <Label htmlFor="is_active">Active</Label>
                </div>

                <div className="flex flex-end md:col-span-2 justify-end gap-2">
                    <Button
                        size={"sm"}
                        type="button"
                        variant="secondary"
                        onClick={() =>
                            router.visit(route("product_discounts.index"))
                        }
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
