import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { MembershipType } from "@/types";
import { router } from "@inertiajs/react";
import { useForm } from "react-hook-form";
import { toast } from "sonner";

type MembershipTypeFormValues = {
    membership_type: string;
    is_active: boolean;
};

export function MembershipTypeForm({
    mode,
    membershipType,
}: {
    mode: "create" | "edit";
    membershipType?: MembershipType;
}) {
    const methods = useForm<MembershipTypeFormValues>({
        defaultValues: {
            membership_type: membershipType?.membership_type ?? "",
            is_active: membershipType
                ? Boolean(membershipType.is_active)
                : true,
        },
        shouldUnregister: false,
    });

    const handleSubmit = (values: MembershipTypeFormValues) => {
        return new Promise<void>((resolve) => {
            if (mode === "create") {
                router.post(route("membership_types.store"), values as any, {
                    onSuccess: () => {
                        toast.success("Membership type created successfully");
                        router.visit(route("membership_types.index"));
                    },
                    onError: (errors: Record<string, string>) => {
                        toast.error("Membership type creation failed");
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
                    "membership_types.update",
                    membershipType!.membership_type_id,
                ),
                { _method: "put", ...values } as any,
                {
                    onSuccess: () => {
                        toast.success("Membership type updated successfully");
                        router.visit(route("membership_types.index"));
                    },
                    onError: (errors: Record<string, string>) => {
                        toast.error("Failed to update membership type");
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
                    <Label htmlFor="membership_type">Membership Type</Label>
                    <Input
                        id="membership_type"
                        type="text"
                        required
                        maxLength={255}
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("membership_type")}
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
                            router.visit(route("membership_types.index"))
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
