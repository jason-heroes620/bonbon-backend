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
import axios from "axios";
import Editor from "../editor/editor";
import { router } from "@inertiajs/react";

const voucherSchema = z.object({
    vendor_id: z.string().uuid("Invalid vendor ID").optional(),
    voucher_name: z.string().min(1, "Voucher name is required").max(200),
    voucher_short_description: z.string().max(100).optional(),
    voucher_description: z.string().optional(),
    duration: z.string().max(100).optional(),
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
    voucher_status: z.boolean().default(false),
    voucher_image: z.any().optional(),
});

export type VoucherFormValues = z.infer<typeof voucherSchema>;

interface VoucherFormProps {
    onSubmit: (data: VoucherFormValues) => void;
    isLoading?: boolean;
    defaultValues?: Partial<VoucherFormValues>;
    existingImageUrl?: string | null;
}

export function VoucherForm({
    onSubmit,
    isLoading,
    defaultValues,
    existingImageUrl,
    isEdit = false,
}: VoucherFormProps & { isEdit?: boolean }) {
    const {
        register,
        handleSubmit,
        control,
        watch,
        formState: { errors },
    } = useForm({
        resolver: zodResolver(voucherSchema),
        defaultValues: {
            voucher_limit: 0,
            voucher_claim_per_user: 1,
            voucher_status: false,
            ...defaultValues,
        },
    });
    const startDate = watch("voucher_start_date");
    const shortDescription = watch("voucher_short_description");
    const selectedImage = watch("voucher_image") as File | undefined;

    const [vendors, setVendors] = useState([]);
    const [localPreviewUrl, setLocalPreviewUrl] = useState<string | null>(null);

    useEffect(() => {
        axios.get(route("vendors.list")).then((res) => {
            setVendors(res.data);
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
            onSubmit={handleSubmit(onSubmit, (errors) => console.error(errors))}
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
                        maxLength={100}
                        required
                    />
                    <span className="flex justify-end text-sm text-muted-foreground">
                        ({shortDescription?.length}/100)
                    </span>
                    {errors.voucher_short_description && (
                        <p className="text-xs text-red-500">
                            {errors.voucher_short_description.message}
                        </p>
                    )}
                </div>

                {/* Duration */}
                <div className="space-y-2">
                    <Label htmlFor="duration">Duration</Label>
                    <Input
                        id="duration"
                        placeholder="e.g. 30 mins"
                        {...register("duration")}
                    />
                    {errors.duration && (
                        <p className="text-sm text-red-500">
                            {errors.duration.message}
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
                    <Label htmlFor="voucher_limit">Limit</Label>
                    <Input
                        type="number"
                        id="voucher_limit"
                        {...register("voucher_limit")}
                    />
                    <span className="text-xs text-muted-foreground">
                        Total number of vouchers available. Set 0 for unlimited.
                    </span>
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
                    disabled={isLoading}
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
