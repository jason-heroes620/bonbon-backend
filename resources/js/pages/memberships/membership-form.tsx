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
import type { Membership, MembershipType } from "@/types";
import { router } from "@inertiajs/react";
import { Controller, useForm } from "react-hook-form";
import { toast } from "sonner";

export type MembershipFormValues = {
    membership_code: string;
    membership_name: string;
    membership_description?: string | null;
    membership_type_id: string;
    membership_price: number;
    duration: number;
    duration_unit: "days" | "months" | "years";
    membership_start_date: string;
    membership_end_date?: string | null;
    is_active: boolean;
    sort_order: number;
    best_value: boolean;
};

const durationUnitOptions: {
    value: MembershipFormValues["duration_unit"];
    label: string;
}[] = [
    { value: "days", label: "Days" },
    { value: "months", label: "Months" },
    { value: "years", label: "Years" },
];

const toDateInput = (value?: string | null) => {
    if (!value) return "";
    return value.slice(0, 10);
};

const resolveMembershipTypeId = ({
    membership,
    membershipTypes,
}: {
    membership?: Membership;
    membershipTypes: MembershipType[];
}) => {
    if (membership?.membership_type_id) return membership.membership_type_id;
    if (!membership?.membership_type)
        return membershipTypes[0]?.membership_type_id ?? "";
    return (
        membershipTypes.find(
            (type) => type.membership_type === membership.membership_type,
        )?.membership_type_id ??
        membershipTypes[0]?.membership_type_id ??
        ""
    );
};

export function MembershipForm({
    mode,
    membership,
    membershipTypes,
}: {
    mode: "create" | "edit";
    membership?: Membership;
    membershipTypes: MembershipType[];
}) {
    const methods = useForm<MembershipFormValues>({
        defaultValues: {
            membership_code: membership?.membership_code ?? "",
            membership_name: membership?.membership_name ?? "",
            membership_description: membership?.membership_description ?? "",
            membership_type_id: resolveMembershipTypeId({
                membership,
                membershipTypes,
            }),
            membership_price: Number(membership?.membership_price ?? 0),
            duration: Number(membership?.duration ?? 1),
            duration_unit: (membership?.duration_unit ??
                "months") as MembershipFormValues["duration_unit"],
            membership_start_date: toDateInput(
                membership?.membership_start_date,
            ),
            membership_end_date: toDateInput(membership?.membership_end_date),
            is_active: membership ? Boolean(membership.is_active) : true,
            sort_order: Number(membership?.sort_order ?? 0),
            best_value: membership ? Boolean(membership.best_value) : false,
        },
        shouldUnregister: false,
    });

    const handleSubmit = (values: MembershipFormValues) => {
        return new Promise<void>((resolve) => {
            if (mode === "create") {
                router.post(route("memberships.store"), values as any, {
                    onSuccess: () => {
                        toast.success("Membership created successfully");
                        router.visit(route("memberships.index"));
                    },
                    onError: (errors: Record<string, string>) => {
                        toast.error("Membership creation failed");
                        Object.values(errors).forEach((error) =>
                            toast.error(error),
                        );
                    },
                    onFinish: () => resolve(),
                });
                return;
            }

            router.post(
                route("memberships.update", membership!.membership_id),
                { _method: "put", ...values } as any,
                {
                    onSuccess: () => {
                        toast.success("Membership updated successfully");
                        router.visit(route("memberships.index"));
                    },
                    onError: (errors: Record<string, string>) => {
                        toast.error("Failed to update membership");
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
                <div className="flex flex-col gap-2">
                    <Label htmlFor="membership_name">Name</Label>
                    <Input
                        id="membership_name"
                        type="text"
                        required
                        maxLength={100}
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("membership_name")}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="membership_code">Code</Label>
                    <Input
                        id="membership_code"
                        type="text"
                        required
                        maxLength={20}
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("membership_code")}
                    />
                </div>

                <div className="flex flex-col gap-2 md:col-span-2">
                    <Label htmlFor="membership_description">Description</Label>
                    <Textarea
                        id="membership_description"
                        required
                        maxLength={255}
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("membership_description")}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="membership_type_id">Type</Label>
                    <Controller
                        name="membership_type_id"
                        control={methods.control}
                        render={({ field }) => (
                            <Select
                                value={field.value ?? ""}
                                onValueChange={field.onChange}
                            >
                                <SelectTrigger
                                    id="membership_type_id"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Select membership type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {membershipTypes.map((item) => (
                                            <SelectItem
                                                key={item.membership_type_id}
                                                value={item.membership_type_id}
                                            >
                                                {item.membership_type}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        )}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="membership_price">Price</Label>
                    <Input
                        id="membership_price"
                        type="number"
                        step="0.01"
                        min={0}
                        required
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("membership_price", {
                            valueAsNumber: true,
                        })}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="duration">Duration</Label>
                    <Input
                        id="duration"
                        type="number"
                        min={1}
                        required
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("duration", {
                            valueAsNumber: true,
                        })}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="duration_unit">Duration Unit</Label>
                    <Controller
                        name="duration_unit"
                        control={methods.control}
                        render={({ field }) => (
                            <Select
                                value={field.value}
                                onValueChange={field.onChange}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {durationUnitOptions.map((item) => (
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
                    <Label htmlFor="membership_start_date">Start Date</Label>
                    <Input
                        id="membership_start_date"
                        type="date"
                        required
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("membership_start_date")}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="membership_end_date">End Date</Label>
                    <Input
                        id="membership_end_date"
                        type="date"
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("membership_end_date")}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="sort_order">Sort Order</Label>
                    <Input
                        id="sort_order"
                        type="number"
                        min={0}
                        required
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("sort_order", {
                            valueAsNumber: true,
                        })}
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

                <div className="flex items-center space-x-2">
                    <input
                        type="checkbox"
                        id="best_value"
                        className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                        {...methods.register("best_value")}
                    />
                    <Label htmlFor="best_value">Best Value</Label>
                </div>

                <div className="flex flex-end md:col-span-2 justify-end gap-2">
                    <Button
                        size={"sm"}
                        type="button"
                        variant="secondary"
                        onClick={() => router.visit(route("memberships.index"))}
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
