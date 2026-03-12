import { MultiSelect } from "@/components/ui/multi-select";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import type { Product } from "@/types";
import { router } from "@inertiajs/react";
import { Controller, useForm } from "react-hook-form";
import { toast } from "sonner";

export type Option = { value: string; label: string };

type ProductFormValues = {
    product_name: string;
    product_code?: string | null;
    product_sku?: string | null;
    product_description: string;
    category_ids: string[];
    stock_quantity: number;
    uom: string;
    product_weight?: number | null;
    product_dimensions?: string | null;
    is_featured: boolean;
    is_visible: boolean;
    is_taxable: boolean;
    tax_rate_id: string;
    retail_price: number;
    sale_price: number;
    is_active: boolean;
    is_unlimited: boolean;
};

const uoms = [
    { value: "unit", label: "Unit" },
    { value: "pax", label: "Pax" },
];

const normalizeNumberOrNull = (value: unknown) => {
    if (typeof value === "number") return Number.isFinite(value) ? value : null;
    return null;
};

const toNumberOrNull = (value?: string | null) => {
    if (value === null || typeof value === "undefined") return null;
    const n = Number(value);
    return Number.isFinite(n) ? n : null;
};

export function ProductForm({
    mode,
    product,
    categories,
    taxRates,
    selectedCategoryIds = [],
}: {
    mode: "create" | "edit";
    product?: Product;
    categories: Option[];
    taxRates: Option[];
    selectedCategoryIds?: string[];
}) {
    const methods = useForm<ProductFormValues>({
        defaultValues: {
            product_name: product?.product_name ?? "",
            product_code: product?.product_code ?? "",
            product_sku: product?.product_sku ?? "",
            product_description: product?.product_description ?? "",
            category_ids: selectedCategoryIds,
            stock_quantity: Number(product?.stock_quantity ?? 0),
            uom: product?.uom ?? "",
            product_weight: toNumberOrNull(product?.product_weight),
            product_dimensions: product?.product_dimensions ?? "",
            is_featured: Boolean(product?.is_featured),
            is_visible: product ? Boolean(product.is_visible) : true,
            is_taxable: Boolean(product?.is_taxable),
            tax_rate_id: product?.tax_rate_id ?? taxRates[0]?.value ?? "",
            retail_price: Number(product?.retail_price ?? 0),
            sale_price: Number(product?.sale_price ?? 0),
            is_active: product ? Boolean(product.is_active) : true,
            is_unlimited: Boolean(product?.is_unlimited),
        },
        shouldUnregister: false,
    });

    const handleSubmit = (values: ProductFormValues) => {
        const payload = {
            ...values,
            category_ids: Array.isArray(values.category_ids)
                ? values.category_ids
                : [],
            product_code: values.product_code ? values.product_code : null,
            product_sku: values.product_sku ? values.product_sku : null,
            product_dimensions: values.product_dimensions
                ? values.product_dimensions
                : null,
            product_weight: normalizeNumberOrNull(values.product_weight),
        };

        return new Promise<void>((resolve) => {
            const routeName =
                mode === "create" ? "products.store" : "products.update";

            const routeParams = mode === "create" ? [] : [product!.product_id];

            router.post(
                route(routeName, ...(routeParams as any)),
                (mode === "create"
                    ? payload
                    : { _method: "put", ...payload }) as any,
                {
                    onSuccess: () => {
                        toast.success(
                            mode === "create"
                                ? "Product created successfully"
                                : "Product updated successfully",
                        );
                        router.visit(route("products.index"));
                    },
                    onError: (errors: Record<string, string>) => {
                        toast.error(
                            mode === "create"
                                ? "Product creation failed"
                                : "Failed to update product",
                        );
                        Object.values(errors).forEach((error) =>
                            toast.error(error),
                        );
                    },
                    onFinish: () => resolve(),
                },
            );
        });
    };

    const categoryOptions = categories.map((c) => ({
        label: c.label,
        value: c.value,
    }));

    return (
        <form
            onSubmit={methods.handleSubmit(handleSubmit)}
            className="bg-white p-6 rounded-md shadow-md"
        >
            <div className="flex flex-col md:grid md:grid-cols-2 gap-4">
                <div className="flex flex-col gap-2 md:col-span-2">
                    <Label htmlFor="product_name">Name</Label>
                    <Input
                        id="product_name"
                        type="text"
                        required
                        maxLength={150}
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("product_name")}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="product_code">Code</Label>
                    <Input
                        id="product_code"
                        type="text"
                        maxLength={50}
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("product_code")}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="product_sku">SKU</Label>
                    <Input
                        id="product_sku"
                        type="text"
                        maxLength={100}
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("product_sku")}
                    />
                </div>

                <div className="flex flex-col gap-2 md:col-span-2">
                    <Label htmlFor="product_description">Description</Label>
                    <Textarea
                        id="product_description"
                        required
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("product_description")}
                    />
                </div>

                <div className="flex flex-col gap-2 md:col-span-2">
                    <Label>Categories</Label>
                    <Controller
                        name="category_ids"
                        control={methods.control}
                        render={({ field }) => (
                            <MultiSelect
                                options={categoryOptions}
                                defaultValue={field.value ?? []}
                                onValueChange={field.onChange}
                                placeholder="Select categories"
                                maxCount={4}
                                searchable
                                className="w-full"
                            />
                        )}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="tax_rate_id">Tax Rate</Label>
                    <Controller
                        name="tax_rate_id"
                        control={methods.control}
                        render={({ field }) => (
                            <Select
                                value={field.value}
                                onValueChange={field.onChange}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Select tax rate" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {taxRates.map((item) => (
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
                    <Label htmlFor="stock_quantity">Stock</Label>
                    <Input
                        id="stock_quantity"
                        type="number"
                        min={0}
                        required
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("stock_quantity", {
                            valueAsNumber: true,
                        })}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="uom">UOM</Label>
                    <Controller
                        name="uom"
                        control={methods.control}
                        render={({ field }) => (
                            <Select
                                value={field.value}
                                onValueChange={field.onChange}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Select UOM" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {uoms.map((item) => (
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
                    <Label htmlFor="product_weight">Weight</Label>
                    <Input
                        id="product_weight"
                        type="number"
                        step="0.01"
                        min={0}
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("product_weight", {
                            valueAsNumber: true,
                        })}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="product_dimensions">Dimensions</Label>
                    <Input
                        id="product_dimensions"
                        type="text"
                        maxLength={100}
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("product_dimensions")}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="retail_price">Retail Price</Label>
                    <Input
                        id="retail_price"
                        type="number"
                        step="0.01"
                        min={0}
                        required
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("retail_price", {
                            valueAsNumber: true,
                        })}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="sale_price">Sale Price</Label>
                    <Input
                        id="sale_price"
                        type="number"
                        step="0.01"
                        min={0}
                        required
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("sale_price", {
                            valueAsNumber: true,
                        })}
                    />
                </div>

                <hr className=" md:col-span-2" />

                <div className="flex items-center space-x-2">
                    <input
                        type="checkbox"
                        id="is_active"
                        className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                        {...methods.register("is_active")}
                    />
                    <Label htmlFor="is_active">Active</Label>
                </div>

                <div className="flex items-center space-x-2">
                    <input
                        type="checkbox"
                        id="is_visible"
                        className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                        {...methods.register("is_visible")}
                    />
                    <Label htmlFor="is_visible">Visible</Label>
                </div>

                <div className="flex items-center space-x-2">
                    <input
                        type="checkbox"
                        id="is_featured"
                        className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                        {...methods.register("is_featured")}
                    />
                    <Label htmlFor="is_featured">Featured</Label>
                </div>

                <div className="flex items-center space-x-2">
                    <input
                        type="checkbox"
                        id="is_taxable"
                        className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                        {...methods.register("is_taxable")}
                    />
                    <Label htmlFor="is_taxable">Taxable</Label>
                </div>

                <div className="flex items-center space-x-2">
                    <input
                        type="checkbox"
                        id="is_unlimited"
                        className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                        {...methods.register("is_unlimited")}
                    />
                    <Label htmlFor="is_unlimited">Unlimited Stock</Label>
                </div>

                <div className="flex flex-end md:col-span-2 justify-end gap-2">
                    <Button
                        size={"sm"}
                        type="button"
                        variant="secondary"
                        onClick={() => router.visit(route("products.index"))}
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
