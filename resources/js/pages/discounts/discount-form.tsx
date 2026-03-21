import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { MultiSelect } from "@/components/ui/multi-select";
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import axios from "axios";
import { router } from "@inertiajs/react";
import { Controller, useForm } from "react-hook-form";
import { useEffect, useState } from "react";
import { toast } from "sonner";

type DiscountFormValues = {
    discount_code?: string;
    user_id?: string;
    discount_name: string;
    discount_description: string;
    discount_type: "P" | "F";
    discount_amount: number;
    discount_start_date: string;
    discount_end_date: string;
    is_active: boolean;
    applies_to: "all" | "specific";
    discount_usage_limit: number;
    is_unlimited: boolean;
    products: string[];
};

const discountTypeOptions = [
    { value: "P", label: "Percentage" },
    { value: "F", label: "Fixed Amount" },
];

const appliesToOptions = [
    { value: "all", label: "All Products" },
    { value: "specific", label: "Specific Products" },
];

export function DiscountForm({
    mode,
    discount,
}: {
    mode: "create" | "edit";
    discount?: any;
}) {
    const [productOptions, setProductOptions] = useState<
        { value: string; label: string }[]
    >([]);
    const [userOptions, setUserOptions] = useState<
        { value: string; label: string }[]
    >([]);

    const methods = useForm<DiscountFormValues>({
        defaultValues: {
            discount_code: discount?.discount_code ?? "",
            user_id: discount?.user_id ?? "",
            discount_name: discount?.discount_name ?? "",
            discount_description: discount?.discount_description ?? "",
            discount_type: discount?.discount_type ?? "P",
            discount_amount: Number(discount?.discount_amount ?? 0),
            discount_start_date: discount?.discount_start_date ?? "",
            discount_end_date: discount?.discount_end_date ?? "",
            is_active: discount ? Boolean(discount.is_active) : true,
            applies_to: discount?.applies_to ?? "all",
            discount_usage_limit: Number(discount?.discount_usage_limit ?? 0),
            is_unlimited: discount ? Boolean(discount.is_unlimited) : false,
            products: Array.isArray(discount?.products)
                ? discount.products
                : [],
        },
        shouldUnregister: false,
    });
    const appliesTo = methods.watch("applies_to");
    const isUnlimited = methods.watch("is_unlimited");

    useEffect(() => {
        axios.get(route("products.list")).then((res) => {
            setProductOptions(res.data ?? []);
        });
    }, []);

    useEffect(() => {
        axios.get(route("users.list")).then((res) => {
            setUserOptions(res.data ?? []);
        });
    }, []);

    useEffect(() => {
        if (appliesTo !== "specific") {
            methods.setValue("products", [], { shouldDirty: true });
        }
    }, [appliesTo, methods]);

    useEffect(() => {
        if (isUnlimited) {
            methods.setValue("discount_usage_limit", 0, {
                shouldDirty: true,
                shouldValidate: true,
            });
        }
    }, [isUnlimited, methods]);

    const onSubmit = (values: DiscountFormValues) => {
        if (mode === "create") {
            router.post(route("discounts.store"), values as any, {
                onSuccess: () => {
                    toast.success("Discount created successfully");
                    router.visit(route("discounts.index"));
                },
                onError: () => toast.error("Failed to create discount"),
            });
            return;
        }

        router.put(
            route("discounts.update", discount.discount_id),
            values as any,
            {
                onSuccess: () => {
                    toast.success("Discount updated successfully");
                    router.visit(route("discounts.index"));
                },
                onError: () => toast.error("Failed to update discount"),
            },
        );
    };

    return (
        <form
            onSubmit={methods.handleSubmit(onSubmit)}
            className="space-y-6 bg-white p-6 rounded-lg shadow-sm border"
        >
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="space-y-2">
                    <Label htmlFor="discount_code">Discount Code</Label>
                    <Input
                        id="discount_code"
                        placeholder="Auto-generated if empty"
                        maxLength={10}
                        {...methods.register("discount_code")}
                    />
                </div>

                <div className="space-y-2">
                    <Label>User</Label>
                    <Controller
                        name="user_id"
                        control={methods.control}
                        render={({ field }) => (
                            <MultiSelect
                                defaultValue={field.value ? [field.value] : []}
                                options={userOptions}
                                onValueChange={(ids) => {
                                    const next = ids.slice(-1);
                                    field.onChange(next[0] ?? "");
                                }}
                                placeholder="Choose user (optional)"
                                searchable={true}
                                maxCount={1}
                                hideSelectAll={true}
                                closeOnSelect={true}
                            />
                        )}
                    />
                </div>

                <div className="space-y-2">
                    <Label htmlFor="discount_name">Discount Name</Label>
                    <Input
                        id="discount_name"
                        maxLength={150}
                        required
                        {...methods.register("discount_name", {
                            required: true,
                        })}
                    />
                </div>

                <div className="space-y-2 md:col-span-2">
                    <Label htmlFor="discount_description">
                        Discount Description
                    </Label>
                    <Input
                        id="discount_description"
                        maxLength={250}
                        required
                        {...methods.register("discount_description", {
                            required: true,
                        })}
                    />
                </div>

                <div className="space-y-2">
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
                                    <SelectValue placeholder="Select type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {discountTypeOptions.map((opt) => (
                                            <SelectItem
                                                key={opt.value}
                                                value={opt.value}
                                            >
                                                {opt.label}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        )}
                    />
                </div>

                <div className="space-y-2">
                    <Label htmlFor="discount_amount">Discount Amount</Label>
                    <Input
                        id="discount_amount"
                        type="number"
                        min={0}
                        step="0.01"
                        required
                        {...methods.register("discount_amount", {
                            valueAsNumber: true,
                            required: true,
                        })}
                    />
                </div>

                <div className="space-y-2">
                    <Label htmlFor="discount_start_date">Start Date</Label>
                    <Input
                        id="discount_start_date"
                        type="date"
                        required
                        {...methods.register("discount_start_date", {
                            required: true,
                        })}
                    />
                </div>

                <div className="space-y-2">
                    <Label htmlFor="discount_end_date">End Date</Label>
                    <Input
                        id="discount_end_date"
                        type="date"
                        required
                        {...methods.register("discount_end_date", {
                            required: true,
                        })}
                    />
                </div>

                <div className="space-y-2">
                    <Label htmlFor="discount_usage_limit">Usage Limit</Label>
                    <div className="flex items-center gap-4">
                        <Input
                            id="discount_usage_limit"
                            type="number"
                            min={0}
                            disabled={Boolean(isUnlimited)}
                            {...methods.register("discount_usage_limit", {
                                setValueAs: (v) =>
                                    v === "" ||
                                    v === null ||
                                    typeof v === "undefined"
                                        ? 0
                                        : Number(v),
                            })}
                        />
                        <div className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                id="is_unlimited"
                                className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                                {...methods.register("is_unlimited")}
                            />
                            <Label htmlFor="is_unlimited">Unlimited</Label>
                        </div>
                    </div>
                </div>

                <div className="space-y-2">
                    <Label htmlFor="applies_to">Applies To</Label>
                    <Controller
                        name="applies_to"
                        control={methods.control}
                        rules={{ required: true }}
                        render={({ field }) => (
                            <Select
                                value={field.value ?? ""}
                                onValueChange={field.onChange}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Select scope" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {appliesToOptions.map((opt) => (
                                            <SelectItem
                                                key={opt.value}
                                                value={opt.value}
                                            >
                                                {opt.label}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        )}
                    />
                </div>

                {appliesTo === "specific" ? (
                    <div className="space-y-2 md:col-span-2">
                        <Label>Applicable Products</Label>
                        <Controller
                            name="products"
                            control={methods.control}
                            render={({ field }) => (
                                <MultiSelect
                                    defaultValue={field.value || []}
                                    options={productOptions}
                                    onValueChange={field.onChange}
                                    placeholder="Choose products"
                                    searchable={true}
                                />
                            )}
                        />
                    </div>
                ) : null}

                <div className="flex items-center space-x-2 md:col-span-2">
                    <input
                        type="checkbox"
                        id="is_active"
                        className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                        {...methods.register("is_active")}
                    />
                    <Label htmlFor="is_active">Active</Label>
                </div>
            </div>

            <div className="flex justify-end gap-2">
                <Button
                    size={"sm"}
                    type="button"
                    variant="secondary"
                    onClick={() => router.visit(route("discounts.index"))}
                >
                    Cancel
                </Button>
                <Button size={"sm"} type="submit">
                    {mode === "edit" ? "Update" : "Save"}
                </Button>
            </div>
        </form>
    );
}
