import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { EvCategory } from "@/types";
import { router } from "@inertiajs/react";
import { useForm } from "react-hook-form";
import { toast } from "sonner";

type EventCategoryFormValues = {
    category_name: string;
    is_active: boolean;
};

export function EventCategoryForm({
    mode,
    evCategory,
}: {
    mode: "create" | "edit";
    evCategory?: EvCategory;
}) {
    const methods = useForm<EventCategoryFormValues>({
        defaultValues: {
            category_name: evCategory?.category_name ?? "",
            is_active: evCategory ? Boolean(evCategory.is_active) : true,
        },
        shouldUnregister: false,
    });

    const handleSubmit = (values: EventCategoryFormValues) => {
        return new Promise<void>((resolve) => {
            const routeName =
                mode === "create"
                    ? "ev_categories.store"
                    : "ev_categories.update";

            const routeParams =
                mode === "create" ? [] : [evCategory!.category_id];

            router.post(
                route(routeName, ...(routeParams as any)),
                (mode === "create"
                    ? values
                    : { _method: "put", ...values }) as any,
                {
                    onSuccess: () => {
                        toast.success(
                            mode === "create"
                                ? "Event category created successfully"
                                : "Event category updated successfully",
                        );
                        router.visit(route("ev_categories.index"));
                    },
                    onError: (errors: Record<string, string>) => {
                        toast.error(
                            mode === "create"
                                ? "Event category creation failed"
                                : "Failed to update event category",
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

    return (
        <form
            onSubmit={methods.handleSubmit(handleSubmit)}
            className="bg-white p-6 rounded-md shadow-md"
        >
            <div className="flex flex-col md:grid md:grid-cols-2 gap-4">
                <div className="flex flex-col gap-2 md:col-span-2">
                    <Label htmlFor="category_name">Category Name</Label>
                    <Input
                        id="category_name"
                        type="text"
                        required
                        maxLength={50}
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("category_name")}
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
                            router.visit(route("ev_categories.index"))
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
