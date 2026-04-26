import { useEffect, useState } from "react";
import { useForm, Controller } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import * as z from "zod";
import { format } from "date-fns";
import { CalendarIcon, Image as ImageIcon, Loader2 } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { MultiSelect } from "@/components/ui/multi-select";
import axios from "axios";
import Editor from "../editor/editor";
import { router } from "@inertiajs/react";

const voucherSchema = z
    .object({
        vendor_id: z.string().uuid("Invalid vendor ID").optional(),
        voucher_name: z.string().min(1, "Voucher name is required").max(200),
        voucher_short_description: z.string().max(50).optional(),
        voucher_description: z.string().optional(),
        voucher_value: z.string().max(200).optional(),
        what_you_get: z.string().optional(),
        voucher_discount: z.coerce.number().min(0).optional(),
        voucher_type: z.string().max(100).optional(),
        voucher_start_date: z.date({
            message: "Start date is required.",
        }),
        voucher_expiry_date: z.date({
            message: "Expiry date is required.",
        }),
        voucher_limit: z.coerce.number().int().min(0).default(0),
        voucher_claim_per_user: z.coerce.number().int().min(1).default(1),
        voucher_claim_period: z
            .preprocess(
                (v) => (v === "" || v === null ? undefined : v),
                z.enum(["week", "month"]).optional(),
            )
            .optional(),
        voucher_claim_per_period: z
            .preprocess(
                (v) => (v === "" || v === null ? undefined : v),
                z.coerce.number().int().min(1).optional(),
            )
            .optional(),
        membership_ids: z.array(z.string().uuid()).default([]),
        membership_ids_present: z.boolean().default(true),
        categories: z.array(z.string().uuid()).optional(),
        voucher_status: z.boolean().default(false),
        voucher_image: z.any().optional(),
        voucher_image_portrait: z.any().optional(),
        voucher_images: z.any().optional(),
        delete_voucher_image_ids: z.array(z.coerce.number().int()).optional(),
        tnc: z.string().optional(),
        how_to_use: z.string().optional(),
        is_unlimited: z.boolean().default(false),
    })
    .superRefine((values, ctx) => {
        if (values.voucher_claim_period && !values.voucher_claim_per_period) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                message:
                    "Claim per period is required when claim period is set.",
                path: ["voucher_claim_per_period"],
            });
        }
    });

export type VoucherFormValues = z.infer<typeof voucherSchema>;

interface VoucherFormProps {
    onSubmit: (data: VoucherFormValues) => void;
    isLoading?: boolean;
    defaultValues?: Partial<VoucherFormValues>;
    existingImageUrl?: string | null;
    existingImageUrlPortrait?: string | null;
    existingVoucherImages?: {
        voucher_image_id: number;
        voucher_image_path: string;
    }[];
}

export function VoucherForm({
    onSubmit,
    isLoading,
    defaultValues,
    existingImageUrl,
    existingVoucherImages,
    isEdit = false,
    canEdit = false,
}: VoucherFormProps & { isEdit?: boolean; canEdit?: boolean }) {
    const {
        register,
        handleSubmit,
        control,
        watch,
        setValue,
        formState: { errors },
    } = useForm({
        resolver: zodResolver(voucherSchema),
        defaultValues: {
            voucher_limit: 0,
            voucher_claim_per_user: 1,
            membership_ids: [],
            categories: [],
            voucher_status: false,
            delete_voucher_image_ids: [],
            ...defaultValues,
        },
    });
    const startDate = watch("voucher_start_date");
    const shortDescription = watch("voucher_short_description");
    const selectedImage = watch("voucher_image") as File | undefined;
    const isUnlimited = watch("is_unlimited");
    const claimPeriod = watch("voucher_claim_period");

    const [vendors, setVendors] = useState([]);
    const [categories, setCategories] = useState<
        { value: string; label: string }[]
    >([]);
    const [membershipOptions, setMembershipOptions] = useState<
        { value: string; label: string }[]
    >([]);
    const [localPreviewUrl, setLocalPreviewUrl] = useState<string | null>(null);
    const [galleryFiles, setGalleryFiles] = useState<
        { key: string; file: File; url: string }[]
    >([]);
    const [removedExistingImageIds, setRemovedExistingImageIds] = useState<
        number[]
    >([]);

    useEffect(() => {
        if (isUnlimited) {
            setValue("voucher_limit", 0, {
                shouldDirty: true,
                shouldValidate: true,
            });
        }
    }, [isUnlimited, setValue]);

    useEffect(() => {
        if (!claimPeriod) {
            setValue("voucher_claim_per_period", undefined, {
                shouldDirty: true,
                shouldValidate: true,
            });
        }
    }, [claimPeriod, setValue]);

    useEffect(() => {
        setValue(
            "voucher_images",
            galleryFiles.map((g) => g.file),
            { shouldDirty: true },
        );
    }, [galleryFiles, setValue]);

    useEffect(() => {
        return () => {
            galleryFiles.forEach((g) => URL.revokeObjectURL(g.url));
        };
    }, [galleryFiles]);

    useEffect(() => {
        axios.get(route("vendors.list")).then((res) => {
            setVendors(res.data);
        });
    }, []);

    useEffect(() => {
        axios.get(route("categories.list")).then((res) => {
            setCategories(res.data);
        });
    }, []);

    useEffect(() => {
        axios.get(route("memberships.list")).then((res) => {
            setMembershipOptions(res.data ?? []);
        });
    }, []);

    useEffect(() => {
        if (!selectedImage) {
            setLocalPreviewUrl(null);
            return;
        }

        const url = URL.createObjectURL(selectedImage);
        setLocalPreviewUrl(url);

        return () => {
            URL.revokeObjectURL(url);
        };
    }, [selectedImage]);

    const previewUrl = localPreviewUrl ?? existingImageUrl ?? null;

    return (
        <form
            onSubmit={handleSubmit(
                (values) => {
                    const payload: any = { ...values };
                    payload.membership_ids_present = true;
                    if (!Array.isArray(payload.membership_ids)) {
                        payload.membership_ids = [];
                    }
                    if (!payload.voucher_claim_period) {
                        delete payload.voucher_claim_period;
                        delete payload.voucher_claim_per_period;
                    }
                    onSubmit(payload);
                },
                (errors) => console.error(errors),
            )}
            className="space-y-6 bg-white p-6 rounded-lg shadow-sm border"
        >
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* Vendor ID */}
                <div className="space-y-2">
                    <Label htmlFor="vendor_id">
                        Vendor ID <span className="text-red-500">*</span>
                    </Label>
                    <Controller
                        control={control}
                        name="vendor_id"
                        render={({ field }) => (
                            <Select
                                onValueChange={field.onChange}
                                defaultValue={field.value}
                                required
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Select Vendor" />
                                </SelectTrigger>
                                <SelectContent>
                                    {vendors.map((vendor: any) => (
                                        <SelectItem
                                            key={vendor.vendor_id}
                                            value={vendor.vendor_id}
                                        >
                                            {vendor.vendor_name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    />
                    {errors.vendor_id && (
                        <p className="text-sm text-red-500">
                            {errors.vendor_id.message}
                        </p>
                    )}
                </div>

                {/* Voucher Name */}
                <div className="space-y-2">
                    <Label htmlFor="voucher_name">
                        Voucher Name <span className="text-red-500">*</span>
                    </Label>
                    <Input
                        id="voucher_name"
                        placeholder="Enter voucher name"
                        {...register("voucher_name")}
                        maxLength={200}
                        required
                    />
                    {errors.voucher_name && (
                        <p className="text-sm text-red-500">
                            {errors.voucher_name.message}
                        </p>
                    )}
                </div>

                {/* Short Description */}
                <div className="space-y-2">
                    <Label htmlFor="voucher_short_description">
                        Short Description{" "}
                        <span className="text-red-500">*</span>
                    </Label>
                    <Input
                        id="voucher_short_description"
                        placeholder="Brief summary"
                        {...register("voucher_short_description")}
                        maxLength={50}
                        required
                    />
                    <span className="flex justify-end text-sm text-muted-foreground">
                        ({shortDescription?.length}/50)
                    </span>
                    {errors.voucher_short_description && (
                        <p className="text-xs text-red-500">
                            {errors.voucher_short_description.message}
                        </p>
                    )}
                </div>

                {/* Duration */}
                <div className="space-y-2">
                    <Label htmlFor="voucher_value">Voucher Value</Label>
                    <Input
                        id="voucher_value"
                        placeholder="RM10 OFF, 50% OFF"
                        {...register("voucher_value")}
                    />
                    {errors.voucher_value && (
                        <p className="text-sm text-red-500">
                            {errors.voucher_value.message}
                        </p>
                    )}
                </div>

                {/* Start Date */}
                <div className="space-y-2 flex flex-col">
                    <Label htmlFor="voucher_start_date">
                        Start Date <span className="text-red-500">*</span>
                    </Label>
                    <Controller
                        control={control}
                        name="voucher_start_date"
                        render={({ field }) => (
                            <Popover>
                                <PopoverTrigger asChild>
                                    <Button
                                        variant={"outline"}
                                        className={cn(
                                            "w-full pl-3 text-left font-normal",
                                            !field.value &&
                                                "text-muted-foreground",
                                        )}
                                    >
                                        {field.value ? (
                                            format(field.value, "PPP")
                                        ) : (
                                            <span>Pick a date</span>
                                        )}
                                        <CalendarIcon className="ml-auto h-4 w-4 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent
                                    className="w-auto p-0"
                                    align="start"
                                >
                                    <Calendar
                                        mode="single"
                                        selected={field.value}
                                        onSelect={field.onChange}
                                        disabled={(date) =>
                                            date < new Date("1900-01-01")
                                        }
                                        required
                                    />
                                </PopoverContent>
                            </Popover>
                        )}
                    />
                    {errors.voucher_start_date && (
                        <p className="text-sm text-red-500">
                            {errors.voucher_start_date.message}
                        </p>
                    )}
                </div>

                {/* Expiry Date */}
                <div className="space-y-2 flex flex-col">
                    <Label htmlFor="voucher_expiry_date">
                        Expiry Date <span className="text-red-500">*</span>
                    </Label>
                    <Controller
                        control={control}
                        name="voucher_expiry_date"
                        render={({ field }) => (
                            <Popover>
                                <PopoverTrigger asChild>
                                    <Button
                                        variant={"outline"}
                                        className={cn(
                                            "w-full pl-3 text-left font-normal",
                                            !field.value &&
                                                "text-muted-foreground",
                                        )}
                                    >
                                        {field.value ? (
                                            format(field.value, "PPP")
                                        ) : (
                                            <span>Pick a date</span>
                                        )}
                                        <CalendarIcon className="ml-auto h-4 w-4 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent
                                    className="w-auto p-0"
                                    align="start"
                                >
                                    <Calendar
                                        mode="single"
                                        selected={field.value}
                                        onSelect={field.onChange}
                                        disabled={(date) =>
                                            startDate
                                                ? date < startDate
                                                : date < new Date("1900-01-01")
                                        }
                                        required
                                    />
                                </PopoverContent>
                            </Popover>
                        )}
                    />
                    {errors.voucher_expiry_date && (
                        <p className="text-sm text-red-500">
                            {errors.voucher_expiry_date.message}
                        </p>
                    )}
                </div>

                {/* Limits */}
                <div className="space-y-2">
                    <div className="flex justify-between">
                        <Label htmlFor="voucher_limit">Limit</Label>
                        <div className="flex items-center space-x-2">
                            <input
                                type="checkbox"
                                id="is_unlimited"
                                className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                                {...register("is_unlimited")}
                            />
                            <Label htmlFor="is_unlimited">Unlimited</Label>
                        </div>
                    </div>
                    <Input
                        type="number"
                        id="voucher_limit"
                        {...register("voucher_limit")}
                        disabled={Boolean(isUnlimited)}
                    />
                    {errors.voucher_limit && (
                        <p className="text-sm text-red-500">
                            {errors.voucher_limit.message}
                        </p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="voucher_claim_per_user">
                        Claim Per User
                    </Label>
                    <Input
                        type="number"
                        id="voucher_claim_per_user"
                        {...register("voucher_claim_per_user")}
                        min={1}
                    />
                    {errors.voucher_claim_per_user && (
                        <p className="text-sm text-red-500">
                            {errors.voucher_claim_per_user.message}
                        </p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="voucher_claim_period">Claim Period</Label>
                    <Controller
                        control={control}
                        name="voucher_claim_period"
                        render={({ field }) => (
                            <Select
                                onValueChange={(v) =>
                                    field.onChange(v === "none" ? "" : v)
                                }
                                value={
                                    (field.value as string | undefined) ?? ""
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="No period limit" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        No period limit
                                    </SelectItem>
                                    <SelectItem value="week">
                                        Per week
                                    </SelectItem>
                                    <SelectItem value="month">
                                        Per month
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        )}
                    />
                    {errors.voucher_claim_period && (
                        <p className="text-sm text-red-500">
                            {String(errors.voucher_claim_period.message)}
                        </p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="voucher_claim_per_period">
                        Claims Per Period
                    </Label>
                    <Input
                        type="number"
                        id="voucher_claim_per_period"
                        {...register("voucher_claim_per_period")}
                        min={1}
                        disabled={!claimPeriod}
                    />
                    {errors.voucher_claim_per_period && (
                        <p className="text-sm text-red-500">
                            {String(errors.voucher_claim_per_period.message)}
                        </p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label>Categories</Label>
                    <Controller
                        name="categories"
                        control={control}
                        render={({ field }) => (
                            <MultiSelect
                                defaultValue={field.value || []}
                                options={categories}
                                onValueChange={field.onChange}
                                placeholder="Choose categories"
                            />
                        )}
                    />
                    {errors.categories && (
                        <p className="text-sm text-red-500">
                            {String(errors.categories.message)}
                        </p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label>Claimable Memberships</Label>
                    <Controller
                        name="membership_ids"
                        control={control}
                        render={({ field }) => (
                            <MultiSelect
                                defaultValue={field.value || []}
                                options={membershipOptions}
                                onValueChange={field.onChange}
                                placeholder="All memberships"
                                searchable={true}
                            />
                        )}
                    />
                    {errors.membership_ids && (
                        <p className="text-sm text-red-500">
                            {String(errors.membership_ids.message)}
                        </p>
                    )}
                </div>

                <div className="space-y-2 md:col-span-2">
                    <div className="flex items-center justify-between">
                        <Label htmlFor="voucher_image">Voucher Image</Label>
                        {previewUrl ? (
                            <Button
                                type="button"
                                size={"sm"}
                                variant="secondary"
                                onClick={() =>
                                    window.open(previewUrl, "_blank")
                                }
                            >
                                <ImageIcon className="mr-2 h-4 w-4" />
                                Preview
                            </Button>
                        ) : null}
                    </div>
                    <Controller
                        control={control}
                        name="voucher_image"
                        render={({ field }) => (
                            <Input
                                id="voucher_image"
                                type="file"
                                accept="image/*"
                                onChange={(e) => {
                                    const file = e.target.files?.[0];
                                    field.onChange(file);
                                }}
                            />
                        )}
                    />
                    {errors.voucher_image && (
                        <p className="text-sm text-red-500">
                            {String(
                                errors.voucher_image.message ?? "Invalid image",
                            )}
                        </p>
                    )}
                </div>
            </div>

            {/* Full Description */}
            <div className="space-y-2">
                <Label htmlFor="voucher_description">
                    Description <span className="text-red-500">*</span>
                </Label>
                {/* <Textarea id="voucher_description" placeholder="Full details..." {...register("voucher_description")} /> */}
                <Editor
                    placeholder="Full details..."
                    control={control}
                    name="voucher_description"
                />
                {errors.voucher_description && (
                    <p className="text-sm text-red-500">
                        {errors.voucher_description.message}
                    </p>
                )}
            </div>

            {/* What You Get */}
            <div className="space-y-2">
                <Label htmlFor="what_you_get">
                    What You Get <span className="text-red-500">*</span>
                </Label>
                {/* <Textarea id="what_you_get" placeholder="List of benefits..." {...register("what_you_get")} /> */}
                <Editor
                    placeholder="List of benefits..."
                    control={control}
                    name="what_you_get"
                />
                {errors.what_you_get && (
                    <p className="text-sm text-red-500">
                        {errors.what_you_get.message}
                    </p>
                )}
            </div>

            <div className="space-y-2">
                <Label htmlFor="tnc">T&amp;C</Label>
                <Editor
                    placeholder="Terms and conditions..."
                    control={control}
                    name="tnc"
                />
                {errors.tnc && (
                    <p className="text-sm text-red-500">
                        {String(errors.tnc.message)}
                    </p>
                )}
            </div>

            <div className="space-y-2">
                <Label htmlFor="how_to_use">How To Use</Label>
                <Editor
                    placeholder="How to use..."
                    control={control}
                    name="how_to_use"
                />
                {errors.how_to_use && (
                    <p className="text-sm text-red-500">
                        {String(errors.how_to_use.message)}
                    </p>
                )}
            </div>

            <div className="space-y-2">
                <Label htmlFor="voucher_images">Voucher Images</Label>
                <Input
                    id="voucher_images"
                    type="file"
                    accept="image/*"
                    multiple
                    onChange={(e) => {
                        const files = Array.from(e.target.files ?? []);
                        if (files.length === 0) {
                            return;
                        }

                        setGalleryFiles((prev) => [
                            ...prev,
                            ...files.map((file) => ({
                                key: `${file.name}-${file.size}-${file.lastModified}`,
                                file,
                                url: URL.createObjectURL(file),
                            })),
                        ]);

                        e.target.value = "";
                    }}
                />

                <div className="grid grid-cols-3 md:grid-cols-6 gap-2">
                    {(existingVoucherImages ?? [])
                        .filter(
                            (img) =>
                                !removedExistingImageIds.includes(
                                    img.voucher_image_id,
                                ),
                        )
                        .map((img) => (
                            <div
                                key={img.voucher_image_id}
                                className="border rounded-md p-1"
                            >
                                <img
                                    src={img.voucher_image_path}
                                    alt=""
                                    className="w-full h-16 object-cover rounded"
                                />
                                <Button
                                    type="button"
                                    size={"sm"}
                                    variant="secondary"
                                    className="w-full mt-1"
                                    onClick={() => {
                                        const next = Array.from(
                                            new Set([
                                                ...removedExistingImageIds,
                                                img.voucher_image_id,
                                            ]),
                                        );
                                        setRemovedExistingImageIds(next);
                                        setValue(
                                            "delete_voucher_image_ids",
                                            next,
                                            { shouldDirty: true },
                                        );
                                    }}
                                >
                                    Delete
                                </Button>
                            </div>
                        ))}

                    {galleryFiles.map((g) => (
                        <div key={g.key} className="border rounded-md p-1">
                            <img
                                src={g.url}
                                alt=""
                                className="w-full h-16 object-cover rounded"
                            />
                            <Button
                                type="button"
                                size={"sm"}
                                variant="secondary"
                                className="w-full mt-1"
                                onClick={() => {
                                    setGalleryFiles((prev) => {
                                        const target = prev.find(
                                            (p) => p.key === g.key,
                                        );
                                        if (target) {
                                            URL.revokeObjectURL(target.url);
                                        }
                                        return prev.filter(
                                            (p) => p.key !== g.key,
                                        );
                                    });
                                }}
                            >
                                Delete
                            </Button>
                        </div>
                    ))}
                </div>
            </div>

            {/* Status Checkbox */}
            <div className="flex items-center space-x-2">
                <input
                    type="checkbox"
                    id="voucher_status"
                    className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                    {...register("voucher_status")}
                />
                <Label htmlFor="voucher_status">Active</Label>
            </div>

            <div className="flex justify-end gap-2">
                <Button
                    size={"sm"}
                    type="button"
                    variant="secondary"
                    onClick={() => router.visit(route("vouchers.index"))}
                >
                    Cancel
                </Button>
                <Button
                    size={"sm"}
                    type="submit"
                    disabled={isLoading || !canEdit}
                    className="w-full md:w-auto"
                >
                    {isLoading && (
                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    )}
                    {isEdit ? "Update" : "Save"}
                </Button>
            </div>
        </form>
    );
}
