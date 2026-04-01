import type { User } from "@/types";
import AppLayout from "@/layouts/AppLayout";
import { Head, router } from "@inertiajs/react";
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
import { ChevronLeft } from "lucide-react";
import { Controller, useForm } from "react-hook-form";
import { toast } from "sonner";

type MembershipOption = {
    membership_id: string;
    membership_code: string;
    membership_name: string;
    membership_type: string;
};

type UserMembership = {
    user_membership_id: string;
    membership_id: string;
    membership_status: "active" | "inactive" | "cancelled" | "expired";
    membership_start_date?: string | null;
    membership_end_date?: string | null;
    inactive_reason?: string | null;
    auto_renew?: boolean | null;
};

type UserEditFormValues = {
    first_name: string;
    last_name: string;
    email?: string;
    contact_no?: string;
    role: "user" | "vendor" | "admin";
    is_active: boolean;
    membership_id?: string;
    membership_status?: UserMembership["membership_status"];
    membership_start_date?: string;
    membership_end_date?: string;
    inactive_reason?: string;
    auto_renew?: boolean;
};

const roleOptions: { value: UserEditFormValues["role"]; label: string }[] = [
    { value: "user", label: "User" },
    { value: "vendor", label: "Vendor" },
    { value: "admin", label: "Admin" },
];

const membershipStatusOptions: {
    value: UserMembership["membership_status"];
    label: string;
}[] = [
    { value: "active", label: "Active" },
    { value: "inactive", label: "Inactive" },
    { value: "cancelled", label: "Cancelled" },
    { value: "expired", label: "Expired" },
];

type Props = {
    user: User & { referral_code?: string | null; email_verified_at?: string | null };
    memberships: MembershipOption[];
    userMembership?: UserMembership | null;
};

const Edit = ({ user, memberships, userMembership }: Props) => {
    const isEmailVerified = Boolean(user.email_verified_at);

    const methods = useForm<UserEditFormValues>({
        defaultValues: {
            first_name: user.first_name ?? "",
            last_name: user.last_name ?? "",
            email: user.email ?? "",
            contact_no: user.contact_no ?? "",
            role: (user.role as UserEditFormValues["role"]) ?? "user",
            is_active: Boolean(user.is_active),
            membership_id: userMembership?.membership_id ?? "",
            membership_status: userMembership?.membership_status ?? "active",
            membership_start_date: userMembership?.membership_start_date ?? "",
            membership_end_date: userMembership?.membership_end_date ?? "",
            inactive_reason: userMembership?.inactive_reason ?? "",
            auto_renew: Boolean(userMembership?.auto_renew),
        },
        shouldUnregister: false,
    });

    const handleSubmit = (values: UserEditFormValues) => {
        return new Promise<void>((resolve) => {
            router.post(
                route("users.update", user.user_id),
                {
                    _method: "put",
                    ...values,
                    membership_id: values.membership_id
                        ? values.membership_id
                        : null,
                    membership_end_date: values.membership_end_date
                        ? values.membership_end_date
                        : null,
                } as any,
                {
                    onSuccess: () => {
                        toast.success("User updated successfully");
                        router.visit(route("users.index"));
                    },
                    onError: (errors: Record<string, string>) => {
                        toast.error("Failed to update user");
                        Object.values(errors).forEach((error) =>
                            toast.error(error),
                        );
                    },
                    onFinish: () => resolve(),
                },
            );
        });
    };

    const handleGenerateReferralCode = () => {
        const values = methods.getValues();
        router.post(
            route("users.update", user.user_id),
            {
                _method: "put",
                ...values,
                membership_id: values.membership_id ? values.membership_id : null,
                membership_end_date: values.membership_end_date
                    ? values.membership_end_date
                    : null,
                generate_referral_code: true,
            } as any,
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success("Referral code generated");
                    router.reload();
                },
                onError: (errors: Record<string, string>) => {
                    toast.error("Failed to generate referral code");
                    Object.values(errors).forEach((error) => toast.error(error));
                },
            },
        );
    };

    const handleResendVerificationEmail = () => {
        router.post(
            route("users.resend_verification", user.user_id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success("Verification email sent");
                },
                onError: (errors: Record<string, string>) => {
                    const message =
                        errors.email ??
                        "Failed to send verification email. Please try again.";
                    toast.error(message);
                },
            },
        );
    };

    return (
        <AppLayout>
            <Head title="Edit User" />
            <div className="flex flex-col px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div className="flex items-center gap-4">
                            <Button
                                variant="default"
                                onClick={() =>
                                    router.visit(route("users.index"))
                                }
                            >
                                <ChevronLeft className="mr" size={20} />
                                Back
                            </Button>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Edit User
                            </h2>
                        </div>
                    </div>
                </div>

                <div className="flex-1 mt-4">
                    <form
                        onSubmit={methods.handleSubmit(handleSubmit)}
                        className="bg-white p-6 rounded-md shadow-md"
                    >
                        <div className="flex flex-col md:grid md:grid-cols-2 gap-4">
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="name">First Name</Label>
                                <Input
                                    type="text"
                                    id="name"
                                    required
                                    maxLength={255}
                                    className="border border-[#D1D5DB] rounded-md px-4 py-2"
                                    {...methods.register("first_name")}
                                />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="last_name">Last Name</Label>
                                <Input
                                    type="text"
                                    id="last_name"
                                    required
                                    maxLength={255}
                                    className="border border-[#D1D5DB] rounded-md px-4 py-2"
                                    {...methods.register("last_name")}
                                />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    type="email"
                                    id="email"
                                    disabled
                                    className="border border-[#D1D5DB] rounded-md px-4 py-2"
                                    {...methods.register("email")}
                                />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label>Email verification</Label>
                                <div className="flex items-center justify-between gap-3 border border-[#D1D5DB] rounded-md px-4 py-2 bg-white">
                                    <div className="text-sm">
                                        {isEmailVerified ? "Verified" : "Not verified"}
                                    </div>
                                    <Button
                                        type="button"
                                        size={"sm"}
                                        variant="outline"
                                        disabled={isEmailVerified}
                                        onClick={handleResendVerificationEmail}
                                    >
                                        Resend
                                    </Button>
                                </div>
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="contact_no">Contact No</Label>
                                <Input
                                    type="text"
                                    id="contact_no"
                                    maxLength={255}
                                    className="border border-[#D1D5DB] rounded-md px-4 py-2"
                                    {...methods.register("contact_no")}
                                />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="role">Role</Label>
                                <Controller
                                    name="role"
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
                                                    {roleOptions.map((item) => (
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

                            <div className="flex items-center space-x-2 md:col-span-2">
                                <input
                                    type="checkbox"
                                    id="is_active"
                                    className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                                    {...methods.register("is_active")}
                                />
                                <Label htmlFor="is_active">Active</Label>
                            </div>

                            <div className="md:col-span-2 border-t pt-4">
                                <h3 className="text-md font-semibold text-gray-900">
                                    Membership
                                </h3>
                            </div>

                            <div className="flex flex-col gap-2">
                                <Label htmlFor="membership_id">Membership</Label>
                                <Controller
                                    name="membership_id"
                                    control={methods.control}
                                    render={({ field }) => (
                                        <Select
                                            value={field.value ?? ""}
                                            onValueChange={field.onChange}
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Select membership" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {memberships.map((m) => (
                                                        <SelectItem
                                                            key={m.membership_id}
                                                            value={m.membership_id}
                                                        >
                                                            {m.membership_name}{" "}
                                                            ({m.membership_type})
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                    )}
                                />
                            </div>

                            <div className="flex flex-col gap-2">
                                <Label htmlFor="membership_status">Status</Label>
                                <Controller
                                    name="membership_status"
                                    control={methods.control}
                                    render={({ field }) => (
                                        <Select
                                            value={field.value ?? "active"}
                                            onValueChange={field.onChange}
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {membershipStatusOptions.map(
                                                        (item) => (
                                                            <SelectItem
                                                                key={item.value}
                                                                value={item.value}
                                                            >
                                                                {item.label}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                    )}
                                />
                            </div>

                            <div className="flex flex-col gap-2">
                                <Label htmlFor="membership_start_date">
                                    Start Date
                                </Label>
                                <Input
                                    type="date"
                                    id="membership_start_date"
                                    className="border border-[#D1D5DB] rounded-md px-4 py-2"
                                    {...methods.register("membership_start_date")}
                                />
                            </div>

                            <div className="flex flex-col gap-2">
                                <Label htmlFor="membership_end_date">
                                    End Date
                                </Label>
                                <Input
                                    type="date"
                                    id="membership_end_date"
                                    className="border border-[#D1D5DB] rounded-md px-4 py-2"
                                    {...methods.register("membership_end_date")}
                                />
                            </div>

                            <div className="flex items-center space-x-2 md:col-span-2">
                                <input
                                    type="checkbox"
                                    id="auto_renew"
                                    className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                                    {...methods.register("auto_renew")}
                                />
                                <Label htmlFor="auto_renew">Auto renew</Label>
                            </div>

                            <div className="flex flex-col gap-2 md:col-span-2">
                                <Label htmlFor="inactive_reason">
                                    Inactive reason
                                </Label>
                                <Input
                                    type="text"
                                    id="inactive_reason"
                                    maxLength={255}
                                    className="border border-[#D1D5DB] rounded-md px-4 py-2"
                                    {...methods.register("inactive_reason")}
                                />
                            </div>

                            <div className="md:col-span-2 border-t pt-4">
                                <h3 className="text-md font-semibold text-gray-900">
                                    Referral Code
                                </h3>
                            </div>

                            <div className="flex flex-col gap-2">
                                <Label htmlFor="referral_code">
                                    Referral code
                                </Label>
                                <Input
                                    type="text"
                                    id="referral_code"
                                    disabled
                                    value={user.referral_code ?? ""}
                                    className="border border-[#D1D5DB] rounded-md px-4 py-2"
                                    readOnly
                                />
                            </div>

                            <div className="flex items-end justify-end">
                                <Button
                                    size={"sm"}
                                    type="button"
                                    variant="outline"
                                    onClick={handleGenerateReferralCode}
                                >
                                    Generate
                                </Button>
                            </div>

                            <div className="flex flex-end md:col-span-2 justify-end gap-2">
                                <Button
                                    size={"sm"}
                                    type="button"
                                    variant="secondary"
                                    onClick={() =>
                                        router.visit(route("users.index"))
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
                                        : "Update"}
                                </Button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
};

export default Edit;
