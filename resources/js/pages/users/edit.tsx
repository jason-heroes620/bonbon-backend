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

type UserEditFormValues = {
    name: string;
    email?: string;
    role: "user" | "vendor" | "admin";
    is_active: boolean;
};

const roleOptions: { value: UserEditFormValues["role"]; label: string }[] = [
    { value: "user", label: "User" },
    { value: "vendor", label: "Vendor" },
    { value: "admin", label: "Admin" },
];

const Edit = ({ user }: { user: User }) => {
    const methods = useForm<UserEditFormValues>({
        defaultValues: {
            name: user.name ?? "",
            email: user.email ?? "",
            role: (user.role as UserEditFormValues["role"]) ?? "user",
            is_active: Boolean(user.is_active),
        },
        shouldUnregister: false,
    });

    const handleSubmit = (values: UserEditFormValues) => {
        return new Promise<void>((resolve) => {
            router.post(
                route("users.update", user.user_id),
                { _method: "put", ...values } as any,
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
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    type="text"
                                    id="name"
                                    required
                                    maxLength={255}
                                    className="border border-[#D1D5DB] rounded-md px-4 py-2"
                                    {...methods.register("name")}
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
