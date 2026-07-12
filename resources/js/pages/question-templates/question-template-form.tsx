import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import type { QuestionTemplate } from "@/types";
import { router } from "@inertiajs/react";
import { Plus, Trash2 } from "lucide-react";
import { Controller, useForm } from "react-hook-form";
import { toast } from "sonner";

type FormOption = {
    option_label: string;
    option_value: string;
    sort_order: number;
    is_active: boolean;
};

type FormValues = {
    question_label: string;
    question_help_text: string;
    question_type:
        | "short_text"
        | "long_text"
        | "single_select"
        | "multi_select"
        | "yes_no";
    is_required_default: boolean;
    is_active: boolean;
    options: FormOption[];
};

export function QuestionTemplateForm({
    mode,
    questionTemplate,
}: {
    mode: "create" | "edit";
    questionTemplate?: QuestionTemplate;
}) {
    const methods = useForm<FormValues>({
        defaultValues: {
            question_label: questionTemplate?.question_label ?? "",
            question_help_text: questionTemplate?.question_help_text ?? "",
            question_type: questionTemplate?.question_type ?? "short_text",
            is_required_default: Boolean(
                questionTemplate?.is_required_default ?? false,
            ),
            is_active: Boolean(questionTemplate?.is_active ?? true),
            options: Array.isArray(questionTemplate?.options)
                ? questionTemplate.options.map((option, index) => ({
                      option_label: option.option_label ?? "",
                      option_value: option.option_value ?? "",
                      sort_order: Number(option.sort_order ?? index + 1),
                      is_active: Boolean(option.is_active ?? true),
                  }))
                : [],
        },
    });

    const questionType = methods.watch("question_type");
    const options = methods.watch("options");
    const supportsOptions =
        questionType === "single_select" || questionType === "multi_select";

    const addOption = () => {
        const next = Array.isArray(options) ? options.length + 1 : 1;
        methods.setValue(
            "options",
            [
                ...(Array.isArray(options) ? options : []),
                {
                    option_label: "",
                    option_value: "",
                    sort_order: next,
                    is_active: true,
                },
            ],
            { shouldDirty: true },
        );
    };

    const removeOption = (index: number) => {
        const next = (Array.isArray(options) ? options : []).filter(
            (_, i) => i !== index,
        );
        methods.setValue(
            "options",
            next.map((option, i) => ({
                ...option,
                sort_order: i + 1,
            })),
            { shouldDirty: true },
        );
    };

    const handleSubmit = (values: FormValues) => {
        const normalizedOptions = values.options
            .map((option, index) => ({
                option_label: option.option_label.trim(),
                option_value: option.option_value.trim(),
                sort_order: Number(option.sort_order || index + 1),
                is_active: Boolean(option.is_active),
            }))
            .filter(
                (option) =>
                    option.option_label.length > 0 &&
                    option.option_value.length > 0,
            );

        if (
            (values.question_type === "single_select" ||
                values.question_type === "multi_select") &&
            normalizedOptions.length === 0
        ) {
            toast.error(
                "Please add at least one active option for select questions.",
            );
            return;
        }

        const payload = {
            question_label: values.question_label,
            question_help_text: values.question_help_text
                ? values.question_help_text
                : null,
            question_type: values.question_type,
            is_required_default: Boolean(values.is_required_default),
            is_active: Boolean(values.is_active),
            options: normalizedOptions,
        };

        if (mode === "create") {
            router.post(route("question_templates.store"), payload as any, {
                onSuccess: () => {
                    toast.success("Questionnaire template created.");
                    router.visit(route("question_templates.index"));
                },
                onError: (errors: Record<string, string>) => {
                    toast.error("Failed to create questionnaire template.");
                    Object.values(errors).forEach((error) => toast.error(error));
                },
            });
            return;
        }

        router.post(
            route("question_templates.update", questionTemplate!.question_template_id),
            { _method: "put", ...payload } as any,
            {
                onSuccess: () => {
                    toast.success("Questionnaire template updated.");
                },
                onError: (errors: Record<string, string>) => {
                    toast.error("Failed to update questionnaire template.");
                    Object.values(errors).forEach((error) => toast.error(error));
                },
            },
        );
    };

    return (
        <form
            onSubmit={methods.handleSubmit(handleSubmit)}
            className="bg-white p-6 rounded-md shadow-md"
        >
            <div className="flex flex-col gap-4">
                <div className="flex flex-col gap-2">
                    <Label htmlFor="question_label">Label</Label>
                    <Input
                        id="question_label"
                        type="text"
                        required
                        maxLength={255}
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("question_label")}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="question_help_text">Help text</Label>
                    <Textarea
                        id="question_help_text"
                        rows={3}
                        className="border border-[#D1D5DB] rounded-md"
                        {...methods.register("question_help_text")}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label>Question type</Label>
                    <Controller
                        name="question_type"
                        control={methods.control}
                        render={({ field }) => (
                            <Select
                                value={field.value}
                                onValueChange={(v) => field.onChange(v as any)}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Select" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="short_text">
                                        Short text
                                    </SelectItem>
                                    <SelectItem value="long_text">
                                        Long text
                                    </SelectItem>
                                    <SelectItem value="single_select">
                                        Single select
                                    </SelectItem>
                                    <SelectItem value="multi_select">
                                        Multi select
                                    </SelectItem>
                                    <SelectItem value="yes_no">
                                        Yes / No
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        )}
                    />
                </div>

                {supportsOptions ? (
                    <div className="flex flex-col gap-3 rounded-md border border-[#E5E7EB] p-4">
                        <div className="flex items-center justify-between gap-2">
                            <div>
                                <Label>Options</Label>
                                <p className="text-sm text-muted-foreground">
                                    These options will be copied into events when
                                    this template is attached.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                onClick={addOption}
                            >
                                <Plus className="mr-2 h-4 w-4" />
                                Add Option
                            </Button>
                        </div>

                        {options.length === 0 ? (
                            <div className="text-sm text-muted-foreground">
                                No options added yet.
                            </div>
                        ) : null}

                        <div className="space-y-3">
                            {options.map((_, index) => (
                                <div
                                    key={`option-${index}`}
                                    className="grid grid-cols-1 gap-3 rounded-md border border-[#E5E7EB] p-3 md:grid-cols-12"
                                >
                                    <div className="md:col-span-4 flex flex-col gap-2">
                                        <Label>Option label</Label>
                                        <Input
                                            type="text"
                                            maxLength={255}
                                            {...methods.register(
                                                `options.${index}.option_label`,
                                            )}
                                        />
                                    </div>

                                    <div className="md:col-span-4 flex flex-col gap-2">
                                        <Label>Option value</Label>
                                        <Input
                                            type="text"
                                            maxLength={100}
                                            {...methods.register(
                                                `options.${index}.option_value`,
                                            )}
                                        />
                                    </div>

                                    <div className="md:col-span-2 flex flex-col gap-2">
                                        <Label>Sort</Label>
                                        <Input
                                            type="number"
                                            min={1}
                                            {...methods.register(
                                                `options.${index}.sort_order`,
                                                {
                                                    valueAsNumber: true,
                                                },
                                            )}
                                        />
                                    </div>

                                    <div className="md:col-span-2 flex items-end justify-between gap-2">
                                        <div className="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                                                {...methods.register(
                                                    `options.${index}.is_active`,
                                                )}
                                            />
                                            <Label>Active</Label>
                                        </div>

                                        <Button
                                            type="button"
                                            variant="secondary"
                                            size="icon"
                                            onClick={() => removeOption(index)}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                ) : null}

                <div className="flex items-center gap-2">
                    <input
                        type="checkbox"
                        id="is_required_default"
                        className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                        {...methods.register("is_required_default")}
                    />
                    <Label htmlFor="is_required_default">
                        Required by default
                    </Label>
                </div>

                <div className="flex items-center gap-2">
                    <input
                        type="checkbox"
                        id="is_active"
                        className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                        {...methods.register("is_active")}
                    />
                    <Label htmlFor="is_active">Active</Label>
                </div>

                <div className="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={() =>
                            router.visit(route("question_templates.index"))
                        }
                    >
                        Cancel
                    </Button>
                    <Button
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
