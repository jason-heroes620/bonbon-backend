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
import type { Category } from "@/types";
import { router } from "@inertiajs/react";
import { Controller, useForm } from "react-hook-form";
import { toast } from "sonner";

export type Option = { value: string; label: string };

type CategoryFormValues = {
    category_name: string;
    parent_id?: string | null;
    is_active: boolean;
};

export function CategoryForm({
    mode,
    category,
    parentCategories,
}: {
    mode: "create" | "edit";
    category?: Category;
    parentCategories: Option[];
}) {
    const methods = useForm<CategoryFormValues>({
        defaultValues: {
            category_name: category?.category_name ?? "",
            parent_id: category?.parent_id ?? "",
            is_active: category ? Boolean(category.is_active) : true,
        },
        shouldUnregister: false,
    });

    const handleSubmit = (values: CategoryFormValues) => {
        const payload = {
            ...values,
            parent_id: values.parent_id ? values.parent_id : null,
        };

        return new Promise<void>((resolve) => {
            const routeName =
                mode === "create" ? "categories.store" : "categories.update";

            const routeParams =
                mode === "create" ? [] : [category!.category_id];

            router.post(
                route(routeName, ...(routeParams as any)),
                (mode === "create"
                    ? payload
                    : { _method: "put", ...payload }) as any,
                {
                    onSuccess: () => {
                        toast.success(
                            mode === "create"
                                ? "Category created successfully"
                                : "Category updated successfully",
                        );
                        router.visit(route("categories.index"));
                    },
                    onError: (errors: Record<string, string>) => {
                        toast.error(
                            mode === "create"
                                ? "Category creation failed"
                                : "Failed to update category",
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
                <div className="flex flex-col gap-2">
                    <Label htmlFor="category_name">Name</Label>
                    <Input
                        id="category_name"
                        type="text"
                        required
                        maxLength={150}
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("category_name")}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="parent_id">Parent Category</Label>
                    <Controller
                        name="parent_id"
                        control={methods.control}
                        render={({ field }) => (
                            <Select
                                value={field.value ?? ""}
                                onValueChange={field.onChange}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Select parent category" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {parentCategories.map((item) => (
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

                <div className="flex items-center space-x-2">
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
                        onClick={() => router.visit(route("categories.index"))}
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
