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
import type { Product, ProductImage, ProductPricingTier } from "@/types";
import axios from "axios";
import { router } from "@inertiajs/react";
import { Controller, useForm } from "react-hook-form";
import { toast } from "sonner";
import { useEffect, useMemo, useState } from "react";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { Pencil, Save, Trash2, Upload, X } from "lucide-react";

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

type PendingImage = {
    id: string;
    file: File;
    previewUrl: string;
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

const MAX_IMAGE_BYTES = 2048 * 2048;
const MAX_IMAGE_SIZE = 2048;

const loadImageDimensions = (
    file: File,
): Promise<{ width: number; height: number }> =>
    new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => {
            resolve({
                width: img.naturalWidth,
                height: img.naturalHeight,
            });
            URL.revokeObjectURL(url);
        };
        img.onerror = () => {
            reject(new Error(`Unable to read image "${file.name}".`));
            URL.revokeObjectURL(url);
        };
        img.src = url;
    });

const validateImageFile = async (file: File) => {
    if (!["image/jpeg", "image/png", "image/webp"].includes(file.type)) {
        throw new Error(
            `${file.name}: only JPG, PNG, and WEBP files are allowed.`,
        );
    }

    if (file.size > MAX_IMAGE_BYTES) {
        throw new Error(`${file.name}: file size must be 2 MB or less.`);
    }

    const { width, height } = await loadImageDimensions(file);
    if (width !== height) {
        throw new Error(`${file.name}: image must be in a 1:1 square ratio.`);
    }

    if (width > MAX_IMAGE_SIZE || height > MAX_IMAGE_SIZE) {
        throw new Error(
            `${file.name}: maximum resolution is 2048 x 2048 pixels.`,
        );
    }
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
            images: pendingImages.map((image) => image.file),
            removed_image_ids: removedImageIds,
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
                    forceFormData: true,
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

    const [tiers, setTiers] = useState<ProductPricingTier[]>([]);
    const [tiersLoading, setTiersLoading] = useState(false);
    const [tiersError, setTiersError] = useState<string | null>(null);

    const activeMode = useMemo(() => {
        const t = tiers.find((x) => x.is_active);
        return t?.pricing_mode ?? null;
    }, [tiers]);

    const [newTierMode, setNewTierMode] = useState<
        "unit_price" | "percentage_discount"
    >("unit_price");
    const [newTierMinQty, setNewTierMinQty] = useState<number>(1);
    const [newTierUnitPrice, setNewTierUnitPrice] = useState<string>("");
    const [newTierDiscountPercent, setNewTierDiscountPercent] =
        useState<string>("");

    const [editingTierId, setEditingTierId] = useState<string | null>(null);
    const [editTierMinQty, setEditTierMinQty] = useState<number>(1);
    const [editTierUnitPrice, setEditTierUnitPrice] = useState<string>("");
    const [editTierDiscountPercent, setEditTierDiscountPercent] =
        useState<string>("");
    const [pendingImages, setPendingImages] = useState<PendingImage[]>([]);
    const [removedImageIds, setRemovedImageIds] = useState<string[]>([]);

    const canManageTiers = Boolean(product?.product_id) && mode === "edit";
    const existingImages = useMemo(
        () =>
            (product?.images ?? []).filter(
                (image) => !removedImageIds.includes(image.product_image_id),
            ),
        [product?.images, removedImageIds],
    );

    const fetchTiers = async () => {
        if (!product?.product_id) return;
        setTiersLoading(true);
        setTiersError(null);
        try {
            const res = await axios.get(
                route("products.pricing_tiers.index", product.product_id),
            );
            setTiers(Array.isArray(res.data?.data) ? res.data.data : []);
        } catch (e: any) {
            setTiers([]);
            setTiersError(
                e?.response?.data?.message ?? "Failed to load tiers.",
            );
        } finally {
            setTiersLoading(false);
        }
    };

    useEffect(() => {
        if (!canManageTiers) return;
        fetchTiers();
    }, [canManageTiers, product?.product_id]);

    useEffect(() => {
        if (activeMode) setNewTierMode(activeMode);
    }, [activeMode]);

    useEffect(() => {
        return () => {
            pendingImages.forEach((image) =>
                URL.revokeObjectURL(image.previewUrl),
            );
        };
    }, [pendingImages]);

    const addImages = async (files: FileList | null) => {
        if (!files || files.length === 0) return;

        const next: PendingImage[] = [];
        for (const file of Array.from(files)) {
            try {
                await validateImageFile(file);
                next.push({
                    id: `${file.name}-${file.size}-${file.lastModified}`,
                    file,
                    previewUrl: URL.createObjectURL(file),
                });
            } catch (error: any) {
                toast.error(error?.message ?? `Invalid image: ${file.name}`);
            }
        }

        if (next.length > 0) {
            setPendingImages((current) => [...current, ...next]);
        }
    };

    const removePendingImage = (id: string) => {
        setPendingImages((current) => {
            const found = current.find((image) => image.id === id);
            if (found) URL.revokeObjectURL(found.previewUrl);
            return current.filter((image) => image.id !== id);
        });
    };

    const removeExistingImage = (image: ProductImage) => {
        setRemovedImageIds((current) =>
            current.includes(image.product_image_id)
                ? current
                : [...current, image.product_image_id],
        );
    };

    const startEditTier = (tier: ProductPricingTier) => {
        setEditingTierId(tier.product_pricing_tier_id);
        setEditTierMinQty(Number(tier.min_qty ?? 1));
        setEditTierUnitPrice(tier.unit_price ? String(tier.unit_price) : "");
        setEditTierDiscountPercent(
            tier.discount_percent ? String(tier.discount_percent) : "",
        );
    };

    const cancelEditTier = () => {
        setEditingTierId(null);
        setEditTierMinQty(1);
        setEditTierUnitPrice("");
        setEditTierDiscountPercent("");
    };

    const createTier = async () => {
        if (!product?.product_id) return;
        if (!Number.isFinite(newTierMinQty) || newTierMinQty < 1) {
            toast.error("Min quantity must be at least 1.");
            return;
        }

        if (newTierMode === "unit_price") {
            const v = Number(newTierUnitPrice);
            if (!Number.isFinite(v) || v < 0) {
                toast.error("Unit price must be a valid number.");
                return;
            }
        } else {
            const v = Number(newTierDiscountPercent);
            if (!Number.isFinite(v) || v < 0 || v > 100) {
                toast.error("Discount percent must be between 0 and 100.");
                return;
            }
        }

        try {
            await axios.post(
                route("products.pricing_tiers.store", product.product_id),
                {
                    pricing_mode: newTierMode,
                    min_qty: newTierMinQty,
                    unit_price:
                        newTierMode === "unit_price"
                            ? Number(newTierUnitPrice)
                            : null,
                    discount_percent:
                        newTierMode === "percentage_discount"
                            ? Number(newTierDiscountPercent)
                            : null,
                    is_active: true,
                },
            );
            toast.success("Pricing tier created.");
            setNewTierMinQty(1);
            setNewTierUnitPrice("");
            setNewTierDiscountPercent("");
            await fetchTiers();
        } catch (e: any) {
            toast.error(
                e?.response?.data?.message ?? "Failed to create pricing tier.",
            );
        }
    };

    const saveTier = async (tier: ProductPricingTier) => {
        if (!product?.product_id) return;
        if (editingTierId !== tier.product_pricing_tier_id) return;

        if (!Number.isFinite(editTierMinQty) || editTierMinQty < 1) {
            toast.error("Min quantity must be at least 1.");
            return;
        }

        if (tier.pricing_mode === "unit_price") {
            const v = Number(editTierUnitPrice);
            if (!Number.isFinite(v) || v < 0) {
                toast.error("Unit price must be a valid number.");
                return;
            }
        } else {
            const v = Number(editTierDiscountPercent);
            if (!Number.isFinite(v) || v < 0 || v > 100) {
                toast.error("Discount percent must be between 0 and 100.");
                return;
            }
        }

        try {
            await axios.put(
                route("products.pricing_tiers.update", [
                    product.product_id,
                    tier.product_pricing_tier_id,
                ]),
                {
                    pricing_mode: tier.pricing_mode,
                    min_qty: editTierMinQty,
                    unit_price:
                        tier.pricing_mode === "unit_price"
                            ? Number(editTierUnitPrice)
                            : null,
                    discount_percent:
                        tier.pricing_mode === "percentage_discount"
                            ? Number(editTierDiscountPercent)
                            : null,
                    is_active: tier.is_active,
                },
            );
            toast.success("Pricing tier updated.");
            cancelEditTier();
            await fetchTiers();
        } catch (e: any) {
            toast.error(
                e?.response?.data?.message ?? "Failed to update pricing tier.",
            );
        }
    };

    const deactivateTier = async (tier: ProductPricingTier) => {
        if (!product?.product_id) return;
        try {
            await axios.delete(
                route("products.pricing_tiers.destroy", [
                    product.product_id,
                    tier.product_pricing_tier_id,
                ]),
            );
            toast.success("Pricing tier deactivated.");
            if (editingTierId === tier.product_pricing_tier_id)
                cancelEditTier();
            await fetchTiers();
        } catch (e: any) {
            toast.error(
                e?.response?.data?.message ??
                    "Failed to deactivate pricing tier.",
            );
        }
    };

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

                <div className="flex flex-col gap-3 md:col-span-2">
                    <div className="flex flex-col gap-1">
                        <Label htmlFor="images">Product Images</Label>
                        <p className="text-sm text-muted-foreground">
                            Upload multiple square images. Each file must be
                            JPG, PNG, or WEBP, up to 2 MB, in a 1:1 ratio, and
                            no larger than 2048 x 2048.
                        </p>
                    </div>

                    <Input
                        id="images"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        multiple
                        onChange={(event) => {
                            void addImages(event.target.files);
                            event.currentTarget.value = "";
                        }}
                    />

                    {existingImages.length > 0 ? (
                        <div className="space-y-2">
                            <div className="text-sm font-medium">
                                Existing Images
                            </div>
                            <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                                {existingImages.map((image) => (
                                    <div
                                        key={image.product_image_id}
                                        className="rounded-md border p-2"
                                    >
                                        <div className="aspect-square overflow-hidden rounded-md bg-muted">
                                            <img
                                                src={
                                                    image.mobile_image_url ??
                                                    image.image_url
                                                }
                                                alt="Product"
                                                className="h-full w-full object-cover"
                                            />
                                        </div>
                                        <div className="mt-2 flex items-center justify-between gap-2">
                                            <div className="text-xs text-muted-foreground">
                                                {image.is_primary
                                                    ? "Primary image"
                                                    : "Active image"}
                                            </div>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    removeExistingImage(image)
                                                }
                                            >
                                                <X className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ) : null}

                    {pendingImages.length > 0 ? (
                        <div className="space-y-2">
                            <div className="text-sm font-medium">
                                New Uploads
                            </div>
                            <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                                {pendingImages.map((image, index) => (
                                    <div
                                        key={image.id}
                                        className="rounded-md border p-2"
                                    >
                                        <div className="aspect-square overflow-hidden rounded-md bg-muted">
                                            <img
                                                src={image.previewUrl}
                                                alt={image.file.name}
                                                className="h-full w-full object-cover"
                                            />
                                        </div>
                                        <div className="mt-2 flex items-center justify-between gap-2">
                                            <div className="text-xs text-muted-foreground">
                                                {existingImages.length === 0 &&
                                                index === 0
                                                    ? "Will become primary"
                                                    : image.file.name}
                                            </div>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    removePendingImage(image.id)
                                                }
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ) : (
                        <div className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
                            <div className="flex items-center gap-2">
                                <Upload className="h-4 w-4" />
                                No new images selected.
                            </div>
                        </div>
                    )}
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

                <div className="md:col-span-2">
                    <div className="rounded-md border border-border p-4">
                        <div className="flex items-center justify-between">
                            <div className="font-semibold">Pricing Tiers</div>
                            {mode === "create" ? (
                                <div className="text-sm text-muted-foreground">
                                    Save product to manage tiers.
                                </div>
                            ) : null}
                        </div>

                        {mode === "edit" ? (
                            <div className="mt-3 space-y-3">
                                {tiersLoading ? (
                                    <div className="text-sm text-muted-foreground">
                                        Loading tiers...
                                    </div>
                                ) : tiersError ? (
                                    <div className="text-sm text-red-600">
                                        {tiersError}
                                    </div>
                                ) : null}

                                <div className="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                                    <div className="flex flex-col gap-2">
                                        <Label>Mode</Label>
                                        <Select
                                            value={newTierMode}
                                            onValueChange={(v) =>
                                                setNewTierMode(
                                                    v as
                                                        | "unit_price"
                                                        | "percentage_discount",
                                                )
                                            }
                                            disabled={Boolean(activeMode)}
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectItem value="unit_price">
                                                        Unit Price
                                                    </SelectItem>
                                                    <SelectItem value="percentage_discount">
                                                        Percentage Discount
                                                    </SelectItem>
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="flex flex-col gap-2">
                                        <Label>Min Qty</Label>
                                        <Input
                                            type="number"
                                            min={1}
                                            value={newTierMinQty}
                                            onChange={(e) =>
                                                setNewTierMinQty(
                                                    Number(e.target.value),
                                                )
                                            }
                                        />
                                    </div>

                                    <div className="flex flex-col gap-2">
                                        <Label>
                                            {newTierMode === "unit_price"
                                                ? "Unit Price"
                                                : "Discount %"}
                                        </Label>
                                        <Input
                                            type="number"
                                            min={0}
                                            step="0.01"
                                            value={
                                                newTierMode === "unit_price"
                                                    ? newTierUnitPrice
                                                    : newTierDiscountPercent
                                            }
                                            onChange={(e) => {
                                                if (
                                                    newTierMode === "unit_price"
                                                ) {
                                                    setNewTierUnitPrice(
                                                        e.target.value,
                                                    );
                                                } else {
                                                    setNewTierDiscountPercent(
                                                        e.target.value,
                                                    );
                                                }
                                            }}
                                        />
                                    </div>

                                    <div className="flex justify-end">
                                        <Button
                                            type="button"
                                            variant="default"
                                            onClick={createTier}
                                            disabled={tiersLoading}
                                        >
                                            Add Tier
                                        </Button>
                                    </div>
                                </div>

                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Min Qty</TableHead>
                                            <TableHead>Mode</TableHead>
                                            <TableHead>Value</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="text-right">
                                                Actions
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {tiers.map((t) => {
                                            const isEditing =
                                                editingTierId ===
                                                t.product_pricing_tier_id;
                                            const value =
                                                t.pricing_mode === "unit_price"
                                                    ? (t.unit_price ?? "-")
                                                    : (t.discount_percent ??
                                                      "-");

                                            return (
                                                <TableRow
                                                    key={
                                                        t.product_pricing_tier_id
                                                    }
                                                >
                                                    <TableCell>
                                                        {isEditing ? (
                                                            <Input
                                                                type="number"
                                                                min={1}
                                                                value={
                                                                    editTierMinQty
                                                                }
                                                                onChange={(e) =>
                                                                    setEditTierMinQty(
                                                                        Number(
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        ),
                                                                    )
                                                                }
                                                            />
                                                        ) : (
                                                            t.min_qty
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        {t.pricing_mode ===
                                                        "unit_price"
                                                            ? "Unit Price"
                                                            : "Percent Discount"}
                                                    </TableCell>
                                                    <TableCell>
                                                        {isEditing ? (
                                                            <Input
                                                                type="number"
                                                                min={0}
                                                                step="0.01"
                                                                value={
                                                                    t.pricing_mode ===
                                                                    "unit_price"
                                                                        ? editTierUnitPrice
                                                                        : editTierDiscountPercent
                                                                }
                                                                onChange={(
                                                                    e,
                                                                ) => {
                                                                    if (
                                                                        t.pricing_mode ===
                                                                        "unit_price"
                                                                    ) {
                                                                        setEditTierUnitPrice(
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        );
                                                                    } else {
                                                                        setEditTierDiscountPercent(
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        );
                                                                    }
                                                                }}
                                                            />
                                                        ) : t.pricing_mode ===
                                                          "unit_price" ? (
                                                            value
                                                        ) : (
                                                            `${value}%`
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        {t.is_active
                                                            ? "Active"
                                                            : "Inactive"}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <div className="flex items-center justify-end gap-2">
                                                            {isEditing ? (
                                                                <>
                                                                    <Button
                                                                        size="sm"
                                                                        variant="default"
                                                                        type="button"
                                                                        onClick={() =>
                                                                            saveTier(
                                                                                t,
                                                                            )
                                                                        }
                                                                    >
                                                                        <Save className="h-4 w-4" />
                                                                    </Button>
                                                                    <Button
                                                                        size="sm"
                                                                        variant="secondary"
                                                                        type="button"
                                                                        onClick={
                                                                            cancelEditTier
                                                                        }
                                                                    >
                                                                        Cancel
                                                                    </Button>
                                                                </>
                                                            ) : (
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                    type="button"
                                                                    onClick={() =>
                                                                        startEditTier(
                                                                            t,
                                                                        )
                                                                    }
                                                                >
                                                                    <Pencil className="h-4 w-4" />
                                                                </Button>
                                                            )}

                                                            <Button
                                                                size="sm"
                                                                variant="destructive"
                                                                type="button"
                                                                disabled={
                                                                    !t.is_active
                                                                }
                                                                onClick={() =>
                                                                    deactivateTier(
                                                                        t,
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}

                                        {tiers.length === 0 && !tiersLoading ? (
                                            <TableRow>
                                                <TableCell
                                                    colSpan={5}
                                                    className="text-muted-foreground"
                                                >
                                                    No tiers yet. Add a tier to
                                                    enable quantity-based
                                                    pricing.
                                                </TableCell>
                                            </TableRow>
                                        ) : null}
                                    </TableBody>
                                </Table>
                            </div>
                        ) : null}
                    </div>
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
