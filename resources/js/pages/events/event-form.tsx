import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import Editor from "@/components/editor/editor";
import Location from "@/components/location/location";
import { MultiSelect } from "@/components/ui/multi-select";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { cn } from "@/lib/utils";
import type { Event } from "@/types";
import { router } from "@inertiajs/react";
import axios from "axios";
import { format } from "date-fns";
import { CalendarIcon } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { Controller, useForm } from "react-hook-form";
import { toast } from "sonner";

type LocationData = {
    location_name: string;
    latitude: number;
    longitude: number;
    place_id?: string;
    address?: string;
    viewport?: any;
    raw_place?: any;
};

type EventFormValues = {
    event_name: string;
    event_start_date: Date | null;
    event_end_date: Date | null;
    event_start_time: string;
    event_end_time: string;
    event_location: string;
    event_description: string;
    require_registration: boolean;
    registration_type: "free" | "paid";
    base_price: string;
    is_unlimited_seats: boolean;
    seat_limit: string;
    seat_hold_minutes: string;
    rsvp_open_at: string;
    rsvp_close_at: string;
    require_questionnaire: boolean;
    is_published: boolean;
    is_active: boolean;
    locations: LocationData[];
    categories: string[];
    event_image?: File | null;
    event_images?: File[];
    delete_event_image_ids?: number[];
    disabled_event_image_ids?: number[];
    disabled_event_image_ids_present?: boolean;
};

function toDateTimeLocalValue(value?: string | null): string {
    if (!value) return "";
    const normalized = String(value).replace(" ", "T");
    return normalized.length >= 16 ? normalized.slice(0, 16) : normalized;
}

type EventPricingRuleRow = {
    event_pricing_rule_id: string;
    event_id: string;
    membership_type_id: string | null;
    pricing_rule_type: "discount_percent" | "discount_fixed" | "final_price";
    pricing_value: string | number;
    starts_at?: string | null;
    ends_at?: string | null;
    is_active: boolean;
};

type MembershipTypeRow = {
    membership_type_id: string;
    membership_type: string;
};

type QuestionTemplateRow = {
    question_template_id: string;
    question_label: string;
    question_help_text?: string | null;
    question_type:
        | "short_text"
        | "long_text"
        | "single_select"
        | "multi_select"
        | "yes_no";
    is_required_default: boolean;
};

type EventQuestionOptionRow = {
    event_question_option_id: string;
    event_questionnaire_id: string;
    option_label: string;
    option_value: string;
    sort_order: number;
    is_active: boolean;
};

type EventQuestionnaireRow = {
    event_questionnaire_id: string;
    event_id: string;
    question_template_id: string | null;
    question_label_snapshot: string;
    question_help_text_snapshot: string | null;
    question_type_snapshot:
        | "short_text"
        | "long_text"
        | "single_select"
        | "multi_select"
        | "yes_no";
    is_required: boolean;
    sort_order: number;
    is_active: boolean;
    options?: EventQuestionOptionRow[];
};

export function EventForm({
    mode,
    event,
}: {
    mode: "create" | "edit";
    event?: Event;
}) {
    const [evCategories, setEvCategories] = useState<
        { value: string; label: string }[]
    >([]);

    const canManageCommerce = Boolean(event?.event_id) && mode === "edit";

    const methods = useForm<EventFormValues>({
        defaultValues: {
            event_name: event?.event_name ?? "",
            event_image: null,
            event_images: [],
            delete_event_image_ids: [],
            disabled_event_image_ids: [],
            disabled_event_image_ids_present: true,
            event_start_date: event?.event_start_date
                ? new Date(`${event.event_start_date.slice(0, 10)}T00:00:00`)
                : null,
            event_end_date: event?.event_end_date
                ? new Date(`${event.event_end_date.slice(0, 10)}T00:00:00`)
                : null,
            event_start_time: event?.event_start_time ?? "",
            event_end_time: event?.event_end_time ?? "",
            event_location: event?.event_location ?? "",
            event_description: event?.event_description ?? "",
            require_registration: Boolean(
                (event as any)?.require_registration ?? false,
            ),
            registration_type: (event as any)?.registration_type ?? "free",
            base_price:
                (event as any)?.base_price !== undefined &&
                (event as any)?.base_price !== null
                    ? String((event as any)?.base_price)
                    : "0",
            is_unlimited_seats:
                (event as any)?.is_unlimited_seats !== undefined
                    ? Boolean((event as any)?.is_unlimited_seats)
                    : true,
            seat_limit:
                (event as any)?.seat_limit !== undefined &&
                (event as any)?.seat_limit !== null
                    ? String((event as any)?.seat_limit)
                    : "",
            seat_hold_minutes:
                (event as any)?.seat_hold_minutes !== undefined &&
                (event as any)?.seat_hold_minutes !== null
                    ? String((event as any)?.seat_hold_minutes)
                    : "15",
            rsvp_open_at: toDateTimeLocalValue((event as any)?.rsvp_open_at),
            rsvp_close_at: toDateTimeLocalValue((event as any)?.rsvp_close_at),
            require_questionnaire: Boolean(
                (event as any)?.require_questionnaire ?? false,
            ),
            is_published: event ? Boolean(event.is_published) : false,
            is_active: event ? Boolean(event.is_active) : true,
            categories: event?.categories ?? [],
            locations: [
                {
                    location_name: event?.location_name ?? "",
                    latitude: Number(event?.location_latitude ?? 0),
                    longitude: Number(event?.location_longitude ?? 0),
                    place_id: event?.place_id ?? "",
                    address: event?.event_location ?? "",
                },
            ],
        },
        shouldUnregister: false,
    });

    const eventImageRegister = methods.register("event_image");
    const setValue = methods.setValue;
    const locations = methods.watch("locations");
    const selectedImage = methods.watch("event_image");
    const startDate = methods.watch("event_start_date");
    const endDate = methods.watch("event_end_date");
    const requireRegistration = methods.watch("require_registration");
    const registrationType = methods.watch("registration_type");
    const isUnlimitedSeats = methods.watch("is_unlimited_seats");
    const [localPreviewUrl, setLocalPreviewUrl] = useState<string | null>(null);
    const [galleryFiles, setGalleryFiles] = useState<
        { key: string; file: File; url: string }[]
    >([]);
    const [removedExistingImageIds, setRemovedExistingImageIds] = useState<
        number[]
    >([]);
    const [disabledExistingImageIds, setDisabledExistingImageIds] = useState<
        number[]
    >(() =>
        (event?.event_images ?? [])
            .filter((img) => img.is_enabled === false)
            .map((img) => img.event_image_id),
    );

    const existingEventImages = event?.event_images ?? [];

    useEffect(() => {
        axios.get(route("ev_categories.list")).then((res) => {
            setEvCategories(res.data);
        });
    }, []);

    const [pricingRules, setPricingRules] = useState<EventPricingRuleRow[]>([]);
    const [pricingMembershipTypes, setPricingMembershipTypes] = useState<
        MembershipTypeRow[]
    >([]);
    const [pricingRulesLoading, setPricingRulesLoading] = useState(false);
    const [pricingRulesError, setPricingRulesError] = useState<string | null>(
        null,
    );

    const [newRuleMembershipTypeId, setNewRuleMembershipTypeId] =
        useState<string>("");
    const [newRuleType, setNewRuleType] = useState<
        "discount_percent" | "discount_fixed" | "final_price"
    >("discount_percent");
    const [newRuleValue, setNewRuleValue] = useState<string>("");
    const [newRuleIsActive, setNewRuleIsActive] = useState(true);

    const [editingRuleId, setEditingRuleId] = useState<string | null>(null);
    const [editRuleMembershipTypeId, setEditRuleMembershipTypeId] =
        useState<string>("");
    const [editRuleType, setEditRuleType] = useState<
        "discount_percent" | "discount_fixed" | "final_price"
    >("discount_percent");
    const [editRuleValue, setEditRuleValue] = useState<string>("");
    const [editRuleIsActive, setEditRuleIsActive] = useState(true);

    const pricingMembershipTypeMap = useMemo(() => {
        const map = new Map<string, string>();
        for (const m of pricingMembershipTypes) {
            map.set(m.membership_type_id, m.membership_type);
        }
        return map;
    }, [pricingMembershipTypes]);

    const fetchPricingRules = async () => {
        if (!event?.event_id) return;
        setPricingRulesLoading(true);
        setPricingRulesError(null);
        try {
            const res = await axios.get(
                route("events.pricing_rules.index", event.event_id),
            );
            setPricingRules(Array.isArray(res.data?.data) ? res.data.data : []);
            setPricingMembershipTypes(
                Array.isArray(res.data?.membership_types)
                    ? res.data.membership_types
                    : [],
            );
        } catch (e: any) {
            setPricingRules([]);
            setPricingMembershipTypes([]);
            setPricingRulesError(
                e?.response?.data?.message ?? "Failed to load pricing rules.",
            );
        } finally {
            setPricingRulesLoading(false);
        }
    };

    useEffect(() => {
        if (!canManageCommerce || !requireRegistration) return;
        fetchPricingRules();
    }, [canManageCommerce, event?.event_id, requireRegistration]);

    const startEditRule = (rule: EventPricingRuleRow) => {
        setEditingRuleId(rule.event_pricing_rule_id);
        setEditRuleMembershipTypeId(rule.membership_type_id ?? "");
        setEditRuleType(rule.pricing_rule_type);
        setEditRuleValue(String(rule.pricing_value ?? ""));
        setEditRuleIsActive(Boolean(rule.is_active));
    };

    const cancelEditRule = () => {
        setEditingRuleId(null);
        setEditRuleMembershipTypeId("");
        setEditRuleType("discount_percent");
        setEditRuleValue("");
        setEditRuleIsActive(true);
    };

    const createPricingRule = async () => {
        if (!event?.event_id) return;
        const value = Number(newRuleValue);
        if (!Number.isFinite(value) || value < 0) {
            toast.error("Pricing value must be a valid number.");
            return;
        }
        if (newRuleType === "discount_percent" && value > 100) {
            toast.error("Discount percent must be between 0 and 100.");
            return;
        }
        if (newRuleType === "final_price" && value <= 0) {
            toast.error("Final price must be greater than 0.");
            return;
        }

        try {
            await axios.post(
                route("events.pricing_rules.store", event.event_id),
                {
                    membership_type_id: newRuleMembershipTypeId
                        ? newRuleMembershipTypeId
                        : null,
                    pricing_rule_type: newRuleType,
                    pricing_value: value,
                    starts_at: null,
                    ends_at: null,
                    is_active: Boolean(newRuleIsActive),
                },
            );
            toast.success("Pricing rule created.");
            setNewRuleMembershipTypeId("");
            setNewRuleType("discount_percent");
            setNewRuleValue("");
            setNewRuleIsActive(true);
            await fetchPricingRules();
        } catch (e: any) {
            toast.error(
                e?.response?.data?.message ?? "Failed to create pricing rule.",
            );
        }
    };

    const savePricingRule = async (rule: EventPricingRuleRow) => {
        if (!event?.event_id) return;
        if (editingRuleId !== rule.event_pricing_rule_id) return;
        const value = Number(editRuleValue);
        if (!Number.isFinite(value) || value < 0) {
            toast.error("Pricing value must be a valid number.");
            return;
        }
        if (editRuleType === "discount_percent" && value > 100) {
            toast.error("Discount percent must be between 0 and 100.");
            return;
        }
        if (editRuleType === "final_price" && value <= 0) {
            toast.error("Final price must be greater than 0.");
            return;
        }

        try {
            await axios.put(
                route("events.pricing_rules.update", [
                    event.event_id,
                    rule.event_pricing_rule_id,
                ]),
                {
                    membership_type_id: editRuleMembershipTypeId
                        ? editRuleMembershipTypeId
                        : null,
                    pricing_rule_type: editRuleType,
                    pricing_value: value,
                    starts_at: rule.starts_at ?? null,
                    ends_at: rule.ends_at ?? null,
                    is_active: Boolean(editRuleIsActive),
                },
            );
            toast.success("Pricing rule updated.");
            cancelEditRule();
            await fetchPricingRules();
        } catch (e: any) {
            toast.error(
                e?.response?.data?.message ?? "Failed to update pricing rule.",
            );
        }
    };

    const deactivatePricingRule = async (rule: EventPricingRuleRow) => {
        if (!event?.event_id) return;
        try {
            await axios.delete(
                route("events.pricing_rules.destroy", [
                    event.event_id,
                    rule.event_pricing_rule_id,
                ]),
            );
            toast.success("Pricing rule deactivated.");
            if (editingRuleId === rule.event_pricing_rule_id) cancelEditRule();
            await fetchPricingRules();
        } catch (e: any) {
            toast.error(
                e?.response?.data?.message ??
                    "Failed to deactivate pricing rule.",
            );
        }
    };

    const [questionTemplates, setQuestionTemplates] = useState<
        QuestionTemplateRow[]
    >([]);
    const [templatePickerIds, setTemplatePickerIds] = useState<string[]>([]);
    const [questions, setQuestions] = useState<EventQuestionnaireRow[]>([]);
    const [questionsLoading, setQuestionsLoading] = useState(false);
    const [questionsError, setQuestionsError] = useState<string | null>(null);

    const fetchQuestionTemplates = async () => {
        setQuestionTemplates([]);
        try {
            const res = await axios.get(route("question_templates.list"));
            setQuestionTemplates(
                Array.isArray(res.data?.data) ? res.data.data : [],
            );
        } catch {
            setQuestionTemplates([]);
        }
    };

    const fetchQuestions = async () => {
        if (!event?.event_id) return;
        setQuestionsLoading(true);
        setQuestionsError(null);
        try {
            const res = await axios.get(
                route("events.questionnaires.index", event.event_id),
            );
            setQuestions(Array.isArray(res.data?.data) ? res.data.data : []);
        } catch (e: any) {
            setQuestions([]);
            setQuestionsError(
                e?.response?.data?.message ??
                    "Failed to load event questionnaires.",
            );
        } finally {
            setQuestionsLoading(false);
        }
    };

    useEffect(() => {
        if (!canManageCommerce || !requireRegistration) return;
        fetchQuestionTemplates();
        fetchQuestions();
    }, [canManageCommerce, event?.event_id, requireRegistration]);

    const attachTemplatesToEvent = async () => {
        if (!event?.event_id) return;
        if (templatePickerIds.length === 0) {
            toast.error("Please select at least one template.");
            return;
        }
        try {
            await axios.post(
                route("events.questionnaires.attach_templates", event.event_id),
                { template_ids: templatePickerIds },
            );
            toast.success("Templates attached.");
            setTemplatePickerIds([]);
            await fetchQuestions();
        } catch (e: any) {
            toast.error(
                e?.response?.data?.message ?? "Failed to attach templates.",
            );
        }
    };

    const deactivateQuestion = async (q: EventQuestionnaireRow) => {
        if (!event?.event_id) return;
        try {
            await axios.delete(
                route("events.questionnaires.destroy", [
                    event.event_id,
                    q.event_questionnaire_id,
                ]),
            );
            toast.success("Question deactivated.");
            await fetchQuestions();
        } catch (e: any) {
            toast.error(
                e?.response?.data?.message ?? "Failed to deactivate question.",
            );
        }
    };

    useEffect(() => {
        if (!startDate || !endDate) {
            return;
        }
        if (endDate < startDate) {
            methods.setValue("event_end_date", null, {
                shouldDirty: true,
                shouldValidate: true,
            });
        }
    }, [endDate, methods, startDate]);

    useEffect(() => {
        if (!requireRegistration) {
            methods.setValue("registration_type", "free", {
                shouldDirty: true,
                shouldValidate: true,
            });
            methods.setValue("base_price", "0", {
                shouldDirty: true,
                shouldValidate: true,
            });
            methods.setValue("is_unlimited_seats", true, {
                shouldDirty: true,
                shouldValidate: true,
            });
            methods.setValue("seat_limit", "", {
                shouldDirty: true,
                shouldValidate: true,
            });
            methods.setValue("seat_hold_minutes", "15", {
                shouldDirty: true,
                shouldValidate: true,
            });
            methods.setValue("rsvp_open_at", "", {
                shouldDirty: true,
                shouldValidate: true,
            });
            methods.setValue("rsvp_close_at", "", {
                shouldDirty: true,
                shouldValidate: true,
            });
            methods.setValue("require_questionnaire", false, {
                shouldDirty: true,
                shouldValidate: true,
            });
        }
    }, [methods, requireRegistration]);

    useEffect(() => {
        if (registrationType === "free") {
            methods.setValue("base_price", "0", {
                shouldDirty: true,
                shouldValidate: true,
            });
        }
    }, [methods, registrationType]);

    useEffect(() => {
        if (isUnlimitedSeats) {
            methods.setValue("seat_limit", "", {
                shouldDirty: true,
                shouldValidate: true,
            });
        }
    }, [isUnlimitedSeats, methods]);

    useEffect(() => {
        setValue(
            "event_images",
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
        if (!(selectedImage instanceof File)) {
            setLocalPreviewUrl(null);
            return;
        }

        const url = URL.createObjectURL(selectedImage);
        setLocalPreviewUrl(url);

        return () => {
            URL.revokeObjectURL(url);
        };
    }, [selectedImage]);

    useEffect(() => {
        const nextDisabled = (event?.event_images ?? [])
            .filter((img) => img.is_enabled === false)
            .map((img) => img.event_image_id);
        setDisabledExistingImageIds(nextDisabled);
        methods.setValue("disabled_event_image_ids", nextDisabled, {
            shouldDirty: false,
        });
        methods.setValue("disabled_event_image_ids_present", true, {
            shouldDirty: false,
        });
    }, [event?.event_id, methods]);

    const handleSubmit = (values: EventFormValues) => {
        const loc = values.locations?.[0];
        const payload = {
            event_name: values.event_name,
            event_start_date: values.event_start_date
                ? format(values.event_start_date, "yyyy-MM-dd")
                : "",
            event_end_date: values.event_end_date
                ? format(values.event_end_date, "yyyy-MM-dd")
                : "",
            event_start_time: values.event_start_time,
            event_end_time: values.event_end_time,
            event_location: values.event_location,
            event_description: values.event_description,
            location_name: loc?.location_name || "",
            location_latitude: loc?.latitude ?? 0,
            location_longitude: loc?.longitude ?? 0,
            place_id: loc?.place_id || "",
            require_registration: Boolean(values.require_registration),
            registration_type: values.registration_type,
            base_price: Number(values.base_price || 0),
            is_unlimited_seats: Boolean(values.is_unlimited_seats),
            seat_limit: values.is_unlimited_seats
                ? null
                : values.seat_limit
                  ? Number(values.seat_limit)
                  : null,
            seat_hold_minutes: Number(values.seat_hold_minutes || 15),
            rsvp_open_at: values.rsvp_open_at ? values.rsvp_open_at : null,
            rsvp_close_at: values.rsvp_close_at ? values.rsvp_close_at : null,
            require_questionnaire: Boolean(values.require_questionnaire),
            is_published: Boolean(values.is_published),
            is_active: Boolean(values.is_active),
            categories: values.categories,
            event_image: values.event_image ?? undefined,
            event_images:
                values.event_images && values.event_images.length > 0
                    ? values.event_images
                    : undefined,
            delete_event_image_ids:
                values.delete_event_image_ids &&
                values.delete_event_image_ids.length > 0
                    ? values.delete_event_image_ids
                    : undefined,
            disabled_event_image_ids_present: true,
            disabled_event_image_ids:
                disabledExistingImageIds.length > 0
                    ? disabledExistingImageIds
                    : undefined,
        };

        return new Promise<void>((resolve) => {
            if (mode === "create") {
                router.post(route("events.store"), payload as any, {
                    forceFormData: true,
                    onSuccess: () => {
                        toast.success("Event created successfully");
                        router.visit(route("events.index"));
                    },
                    onError: (errors: Record<string, string>) => {
                        toast.error("Event creation failed");
                        Object.values(errors).forEach((error) =>
                            toast.error(error),
                        );
                    },
                    onFinish: () => resolve(),
                });
                return;
            }

            router.post(
                route("events.update", event!.event_id),
                { _method: "put", ...payload } as any,
                {
                    forceFormData: true,
                    onSuccess: () => {
                        toast.success("Event updated successfully");
                    },
                    onError: (errors: Record<string, string>) => {
                        toast.error("Failed to update event");
                        Object.values(errors).forEach((error) =>
                            toast.error(error),
                        );
                    },
                    onFinish: () => resolve(),
                },
            );
        });
    };

    const previewUrl = localPreviewUrl ?? event?.event_image_path ?? null;

    return (
        <form
            onSubmit={methods.handleSubmit(handleSubmit)}
            className="bg-white p-6 rounded-md shadow-md"
        >
            <div className="flex flex-col md:grid md:grid-cols-2 gap-4">
                <div className="flex flex-col gap-2 md:col-span-2">
                    <Label htmlFor="event_name">Event Name</Label>
                    <Input
                        id="event_name"
                        type="text"
                        required
                        maxLength={100}
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("event_name")}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label>Start Date</Label>
                    <Controller
                        name="event_start_date"
                        control={methods.control}
                        rules={{ required: true }}
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
                                        selected={field.value ?? undefined}
                                        onSelect={(d) =>
                                            field.onChange(d ?? null)
                                        }
                                        disabled={(date) => date < new Date()}
                                        required
                                    />
                                </PopoverContent>
                            </Popover>
                        )}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label>End Date</Label>
                    <Controller
                        name="event_end_date"
                        control={methods.control}
                        rules={{ required: true }}
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
                                        selected={field.value ?? undefined}
                                        onSelect={(d) =>
                                            field.onChange(d ?? null)
                                        }
                                        startMonth={
                                            new Date(
                                                methods.getValues()
                                                    .event_start_date ?? "",
                                            )
                                        }
                                        disabled={(date) =>
                                            startDate ? date < startDate : false
                                        }
                                        required
                                    />
                                </PopoverContent>
                            </Popover>
                        )}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="event_start_time">Start Time</Label>
                    <Input
                        id="event_start_time"
                        type="time"
                        required
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("event_start_time")}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="event_end_time">End Time</Label>
                    <Input
                        id="event_end_time"
                        type="time"
                        required
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("event_end_time")}
                    />
                </div>

                <div className="flex flex-col gap-2">
                    <Label htmlFor="event_location">Location</Label>
                    <Input
                        id="event_location"
                        type="text"
                        required
                        maxLength={100}
                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                        {...methods.register("event_location")}
                    />
                </div>

                <div className="flex flex-col gap-2 md:col-span-2">
                    <Label>Google Location Search</Label>
                    <Location
                        data={{ locations: locations ?? [] }}
                        setData={(key: string, value: any) => {
                            if (key === "locations") {
                                methods.setValue("locations", value, {
                                    shouldDirty: true,
                                });
                            }
                        }}
                        single
                    />
                </div>

                <div className="flex flex-col gap-2 md:col-span-2">
                    <Label htmlFor="event_description">Description</Label>
                    <Editor
                        placeholder="Full details..."
                        control={methods.control}
                        name="event_description"
                        defaultValue={methods.getValues("event_description")}
                    />
                </div>

                <div className="flex flex-col gap-2 md:col-span-2">
                    <Label>Categories</Label>
                    <Controller
                        name="categories"
                        control={methods.control}
                        render={({ field }) => (
                            <MultiSelect
                                defaultValue={field.value || []}
                                options={evCategories}
                                onValueChange={field.onChange}
                                placeholder="Choose categories"
                            />
                        )}
                    />
                </div>

                <div className="flex flex-col gap-4 md:col-span-2 border rounded-md p-4">
                    <div className="flex items-center space-x-2">
                        <input
                            type="checkbox"
                            id="require_registration"
                            className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                            {...methods.register("require_registration")}
                        />
                        <Label htmlFor="require_registration">
                            Require Registration
                        </Label>
                    </div>

                    {requireRegistration ? (
                        <div className="flex md:grid grid-cols-2 gap-4">
                            <div className="flex flex-col gap-2">
                                <Label>Registration Type</Label>
                                <Controller
                                    name="registration_type"
                                    control={methods.control}
                                    render={({ field }) => (
                                        <Select
                                            value={field.value}
                                            onValueChange={(v) =>
                                                field.onChange(
                                                    v as "free" | "paid",
                                                )
                                            }
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue placeholder="Select" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="free">
                                                    Free
                                                </SelectItem>
                                                <SelectItem value="paid">
                                                    Paid
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    )}
                                />
                            </div>

                            <div className="flex flex-col gap-2">
                                <Label htmlFor="base_price">
                                    Base Price (MYR)
                                </Label>
                                <Input
                                    id="base_price"
                                    type="number"
                                    inputMode="decimal"
                                    step="0.01"
                                    min={0}
                                    disabled={registrationType === "free"}
                                    className="border border-[#D1D5DB] rounded-md px-4 py-2"
                                    {...methods.register("base_price")}
                                />
                            </div>

                            <div className="flex items-center space-x-2 md:col-span-2">
                                <input
                                    type="checkbox"
                                    id="is_unlimited_seats"
                                    className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                                    {...methods.register("is_unlimited_seats")}
                                />
                                <Label htmlFor="is_unlimited_seats">
                                    Unlimited Seats
                                </Label>
                            </div>

                            <div className="flex flex-col gap-2">
                                <Label htmlFor="seat_limit">Seat Limit</Label>
                                <Input
                                    id="seat_limit"
                                    type="number"
                                    inputMode="numeric"
                                    step="1"
                                    min={1}
                                    disabled={isUnlimitedSeats}
                                    className="border border-[#D1D5DB] rounded-md px-4 py-2"
                                    {...methods.register("seat_limit")}
                                />
                            </div>

                            <div className="flex flex-col gap-2">
                                <Label htmlFor="seat_hold_minutes">
                                    Seat Hold (Minutes)
                                </Label>
                                <Input
                                    id="seat_hold_minutes"
                                    type="number"
                                    inputMode="numeric"
                                    step="1"
                                    min={1}
                                    className="border border-[#D1D5DB] rounded-md px-4 py-2"
                                    {...methods.register("seat_hold_minutes")}
                                />
                            </div>

                            <div className="flex flex-col gap-2">
                                <Label htmlFor="rsvp_open_at">
                                    RSVP Open At
                                </Label>
                                <Input
                                    id="rsvp_open_at"
                                    type="datetime-local"
                                    className="border border-[#D1D5DB] rounded-md px-4 py-2"
                                    {...methods.register("rsvp_open_at")}
                                />
                            </div>

                            <div className="flex flex-col gap-2">
                                <Label htmlFor="rsvp_close_at">
                                    RSVP Close At
                                </Label>
                                <Input
                                    id="rsvp_close_at"
                                    type="datetime-local"
                                    className="border border-[#D1D5DB] rounded-md px-4 py-2"
                                    {...methods.register("rsvp_close_at")}
                                />
                            </div>

                            <div className="flex items-center space-x-2 md:col-span-2">
                                <input
                                    type="checkbox"
                                    id="require_questionnaire"
                                    className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                                    {...methods.register(
                                        "require_questionnaire",
                                    )}
                                />
                                <Label htmlFor="require_questionnaire">
                                    Require Questionnaire Before Join/Payment
                                </Label>
                            </div>
                        </div>
                    ) : null}
                </div>

                {canManageCommerce && requireRegistration ? (
                    <div className="md:col-span-2 border rounded-md p-4">
                        <div className="flex items-center justify-between gap-2">
                            <div className="text-sm font-semibold">
                                Pricing Rules (By Membership)
                            </div>
                            <Button
                                type="button"
                                size={"sm"}
                                variant="secondary"
                                onClick={fetchPricingRules}
                                disabled={pricingRulesLoading}
                            >
                                Refresh
                            </Button>
                        </div>

                        {pricingRulesError ? (
                            <div className="text-sm text-red-600 mt-2">
                                {pricingRulesError}
                            </div>
                        ) : null}

                        <div className="grid grid-cols-1 md:grid-cols-4 gap-2 mt-3">
                            <div className="flex flex-col gap-1">
                                <Label>Membership Type</Label>
                                <Select
                                    value={
                                        newRuleMembershipTypeId || "__none__"
                                    }
                                    onValueChange={(v) =>
                                        setNewRuleMembershipTypeId(
                                            v === "__none__" ? "" : v,
                                        )
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Select" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="__none__">
                                            No membership
                                        </SelectItem>
                                        {pricingMembershipTypes.map((m) => (
                                            <SelectItem
                                                key={m.membership_type_id}
                                                value={m.membership_type_id}
                                            >
                                                {m.membership_type}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="flex flex-col gap-1">
                                <Label>Rule Type</Label>
                                <Select
                                    value={newRuleType}
                                    onValueChange={(v) =>
                                        setNewRuleType(v as any)
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Select" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="discount_percent">
                                            Discount (%)
                                        </SelectItem>
                                        <SelectItem value="discount_fixed">
                                            Discount (Fixed)
                                        </SelectItem>
                                        <SelectItem value="final_price">
                                            Final Price
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="flex flex-col gap-1">
                                <Label>Value</Label>
                                <Input
                                    type="number"
                                    inputMode="decimal"
                                    step="0.01"
                                    min={0}
                                    value={newRuleValue}
                                    onChange={(e) =>
                                        setNewRuleValue(e.target.value)
                                    }
                                />
                            </div>

                            <div className="flex flex-col gap-1">
                                <Label>Active</Label>
                                <div className="flex items-center gap-2 h-9">
                                    <input
                                        type="checkbox"
                                        className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                                        checked={newRuleIsActive}
                                        onChange={(e) =>
                                            setNewRuleIsActive(e.target.checked)
                                        }
                                    />
                                    <Button
                                        type="button"
                                        size={"sm"}
                                        onClick={createPricingRule}
                                    >
                                        Add
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <div className="mt-4 overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-left border-b">
                                        <th className="py-2 pr-2">
                                            Membership
                                        </th>
                                        <th className="py-2 pr-2">Type</th>
                                        <th className="py-2 pr-2">Value</th>
                                        <th className="py-2 pr-2">Active</th>
                                        <th className="py-2 pr-2">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {pricingRules.map((r) => {
                                        const isEditing =
                                            editingRuleId ===
                                            r.event_pricing_rule_id;
                                        const membershipLabel =
                                            r.membership_type_id
                                                ? (pricingMembershipTypeMap.get(
                                                      r.membership_type_id,
                                                  ) ?? r.membership_type_id)
                                                : "No membership";
                                        return (
                                            <tr
                                                key={r.event_pricing_rule_id}
                                                className="border-b"
                                            >
                                                <td className="py-2 pr-2">
                                                    {isEditing ? (
                                                        <Select
                                                            value={
                                                                editRuleMembershipTypeId ||
                                                                "__none__"
                                                            }
                                                            onValueChange={(
                                                                v,
                                                            ) =>
                                                                setEditRuleMembershipTypeId(
                                                                    v ===
                                                                        "__none__"
                                                                        ? ""
                                                                        : v,
                                                                )
                                                            }
                                                        >
                                                            <SelectTrigger className="w-full">
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem value="__none__">
                                                                    No
                                                                    membership
                                                                </SelectItem>
                                                                {pricingMembershipTypes.map(
                                                                    (m) => (
                                                                        <SelectItem
                                                                            key={
                                                                                m.membership_type_id
                                                                            }
                                                                            value={
                                                                                m.membership_type_id
                                                                            }
                                                                        >
                                                                            {
                                                                                m.membership_type
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                                )}
                                                            </SelectContent>
                                                        </Select>
                                                    ) : (
                                                        membershipLabel
                                                    )}
                                                </td>
                                                <td className="py-2 pr-2">
                                                    {isEditing ? (
                                                        <Select
                                                            value={editRuleType}
                                                            onValueChange={(
                                                                v,
                                                            ) =>
                                                                setEditRuleType(
                                                                    v as any,
                                                                )
                                                            }
                                                        >
                                                            <SelectTrigger className="w-full">
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem value="discount_percent">
                                                                    Discount (%)
                                                                </SelectItem>
                                                                <SelectItem value="discount_fixed">
                                                                    Discount
                                                                    (Fixed)
                                                                </SelectItem>
                                                                <SelectItem value="final_price">
                                                                    Final Price
                                                                </SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    ) : (
                                                        r.pricing_rule_type
                                                    )}
                                                </td>
                                                <td className="py-2 pr-2">
                                                    {isEditing ? (
                                                        <Input
                                                            type="number"
                                                            inputMode="decimal"
                                                            step="0.01"
                                                            min={0}
                                                            value={
                                                                editRuleValue
                                                            }
                                                            onChange={(e) =>
                                                                setEditRuleValue(
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    ) : (
                                                        String(r.pricing_value)
                                                    )}
                                                </td>
                                                <td className="py-2 pr-2">
                                                    {isEditing ? (
                                                        <input
                                                            type="checkbox"
                                                            className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                                                            checked={
                                                                editRuleIsActive
                                                            }
                                                            onChange={(e) =>
                                                                setEditRuleIsActive(
                                                                    e.target
                                                                        .checked,
                                                                )
                                                            }
                                                        />
                                                    ) : r.is_active ? (
                                                        "Yes"
                                                    ) : (
                                                        "No"
                                                    )}
                                                </td>
                                                <td className="py-2 pr-2">
                                                    <div className="flex items-center gap-2">
                                                        {isEditing ? (
                                                            <>
                                                                <Button
                                                                    type="button"
                                                                    size={"sm"}
                                                                    onClick={() =>
                                                                        savePricingRule(
                                                                            r,
                                                                        )
                                                                    }
                                                                >
                                                                    Save
                                                                </Button>
                                                                <Button
                                                                    type="button"
                                                                    size={"sm"}
                                                                    variant="secondary"
                                                                    onClick={
                                                                        cancelEditRule
                                                                    }
                                                                >
                                                                    Cancel
                                                                </Button>
                                                            </>
                                                        ) : (
                                                            <>
                                                                <Button
                                                                    type="button"
                                                                    size={"sm"}
                                                                    variant="secondary"
                                                                    onClick={() =>
                                                                        startEditRule(
                                                                            r,
                                                                        )
                                                                    }
                                                                >
                                                                    Edit
                                                                </Button>
                                                                <Button
                                                                    type="button"
                                                                    size={"sm"}
                                                                    variant="secondary"
                                                                    onClick={() =>
                                                                        deactivatePricingRule(
                                                                            r,
                                                                        )
                                                                    }
                                                                >
                                                                    Deactivate
                                                                </Button>
                                                            </>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ) : null}

                {canManageCommerce && requireRegistration ? (
                    <div className="md:col-span-2 border rounded-md p-4">
                        <div className="flex items-center justify-between gap-2">
                            <div className="text-sm font-semibold">
                                Questionnaire Templates
                            </div>
                            <div className="flex items-center gap-2">
                                <Button
                                    type="button"
                                    size={"sm"}
                                    variant="secondary"
                                    onClick={() =>
                                        router.visit(
                                            route("question_templates.index"),
                                        )
                                    }
                                >
                                    Manage Templates
                                </Button>
                                <Button
                                    type="button"
                                    size={"sm"}
                                    variant="secondary"
                                    onClick={fetchQuestions}
                                    disabled={questionsLoading}
                                >
                                    Refresh
                                </Button>
                            </div>
                        </div>

                        {questionsError ? (
                            <div className="text-sm text-red-600 mt-2">
                                {questionsError}
                            </div>
                        ) : null}

                        <div className="mt-2 text-sm text-muted-foreground">
                            Templates are managed under Configurations. Events
                            can only attach existing questionnaire templates.
                        </div>

                        <div className="mt-3 grid grid-cols-1 md:grid-cols-3 gap-2">
                            <div className="flex flex-col gap-1">
                                <Label>Attach Templates</Label>
                                <Select
                                    value={
                                        templatePickerIds[0] ??
                                        "__select_placeholder__"
                                    }
                                    onValueChange={(v) => {
                                        if (
                                            v === "__select_placeholder__" ||
                                            !v
                                        )
                                            return;
                                        setTemplatePickerIds((prev) =>
                                            Array.from(new Set([...prev, v])),
                                        );
                                    }}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Pick template" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="__select_placeholder__">
                                            Select a template
                                        </SelectItem>
                                        {questionTemplates.map((t) => (
                                            <SelectItem
                                                key={t.question_template_id}
                                                value={t.question_template_id}
                                            >
                                                {t.question_label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="flex items-center gap-2 md:col-span-2">
                                <div className="flex flex-wrap gap-2">
                                    {templatePickerIds.map((id) => {
                                        const label =
                                            questionTemplates.find(
                                                (t) =>
                                                    t.question_template_id ===
                                                    id,
                                            )?.question_label ?? id;
                                        return (
                                            <Button
                                                key={id}
                                                type="button"
                                                size={"sm"}
                                                variant="secondary"
                                                onClick={() =>
                                                    setTemplatePickerIds(
                                                        (prev) =>
                                                            prev.filter(
                                                                (x) => x !== id,
                                                            ),
                                                    )
                                                }
                                            >
                                                {label}
                                            </Button>
                                        );
                                    })}
                                </div>
                                <Button
                                    type="button"
                                    size={"sm"}
                                    onClick={attachTemplatesToEvent}
                                    disabled={templatePickerIds.length === 0}
                                >
                                    Attach
                                </Button>
                            </div>
                        </div>

                        <div className="mt-4 space-y-3">
                            {questions.map((q) => (
                                <div
                                    key={q.event_questionnaire_id}
                                    className="border rounded-md p-3"
                                >
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="space-y-2">
                                            <div className="text-sm font-medium">
                                                {q.question_label_snapshot}
                                            </div>
                                            <div className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                                                <span>
                                                    Type:{" "}
                                                    {q.question_type_snapshot}
                                                </span>
                                                <span>
                                                    Required:{" "}
                                                    {q.is_required
                                                        ? "Yes"
                                                        : "No"}
                                                </span>
                                                <span>
                                                    Sort: {q.sort_order}
                                                </span>
                                            </div>
                                            {q.question_help_text_snapshot ? (
                                                <div className="text-sm text-muted-foreground">
                                                    {
                                                        q.question_help_text_snapshot
                                                    }
                                                </div>
                                            ) : null}
                                            {Array.isArray(q.options) &&
                                            q.options.length > 0 ? (
                                                <div className="flex flex-wrap gap-2">
                                                    {q.options
                                                        .filter(
                                                            (option) =>
                                                                option.is_active,
                                                        )
                                                        .map((option) => (
                                                            <span
                                                                key={
                                                                    option.event_question_option_id
                                                                }
                                                                className="inline-flex items-center rounded-md border px-2 py-1 text-xs"
                                                            >
                                                                {
                                                                    option.option_label
                                                                }
                                                            </span>
                                                        ))}
                                                </div>
                                            ) : null}
                                        </div>

                                        <div className="flex items-center gap-2">
                                            <Button
                                                type="button"
                                                size={"sm"}
                                                variant="secondary"
                                                onClick={() =>
                                                    deactivateQuestion(q)
                                                }
                                            >
                                                Remove
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            ))}
                            {questions.length === 0 ? (
                                <div className="text-sm text-muted-foreground">
                                    No questionnaire templates attached to this
                                    event yet.
                                </div>
                            ) : null}
                        </div>
                    </div>
                ) : null}

                <div className="flex flex-col gap-2 md:col-span-2">
                    <Label htmlFor="event_image">Event Image</Label>
                    <div className="flex items-center gap-2">
                        <Input
                            id="event_image"
                            type="file"
                            accept="image/*"
                            className="border border-[#D1D5DB] rounded-md"
                            onChange={(e) => {
                                eventImageRegister.onChange(e);
                                const file = e.target.files?.[0] ?? null;
                                methods.setValue("event_image", file, {
                                    shouldDirty: true,
                                });
                            }}
                            name={eventImageRegister.name}
                            ref={eventImageRegister.ref}
                        />
                        {previewUrl ? (
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() => {
                                    window.open(
                                        previewUrl,
                                        "_blank",
                                        "noopener,noreferrer",
                                    );
                                }}
                            >
                                Preview
                            </Button>
                        ) : null}
                    </div>
                </div>
                {previewUrl ? (
                    <div className="md:col-span-2">
                        <img
                            src={previewUrl}
                            alt={methods.getValues("event_name")}
                            className="w-32 h-32 object-cover rounded-md border"
                        />
                    </div>
                ) : null}

                <div className="flex flex-col gap-2 md:col-span-2">
                    <Label htmlFor="event_images">Event Images</Label>
                    <Input
                        id="event_images"
                        type="file"
                        accept="image/*"
                        multiple
                        className="border border-[#D1D5DB] rounded-md"
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
                        {existingEventImages
                            .filter(
                                (img) =>
                                    !removedExistingImageIds.includes(
                                        img.event_image_id,
                                    ),
                            )
                            .map((img) => (
                                <div
                                    key={img.event_image_id}
                                    className="border rounded-md p-1"
                                >
                                    <img
                                        src={img.event_image_path}
                                        alt=""
                                        className={cn(
                                            "w-full h-16 object-cover rounded cursor-pointer",
                                            disabledExistingImageIds.includes(
                                                img.event_image_id,
                                            ) && "opacity-50",
                                        )}
                                        onClick={() => {
                                            window.open(
                                                img.event_image_path,
                                                "_blank",
                                                "noopener,noreferrer",
                                            );
                                        }}
                                    />
                                    <div className="flex items-center justify-between gap-2 mt-1">
                                        <label className="flex items-center gap-2 text-xs">
                                            <input
                                                type="checkbox"
                                                checked={
                                                    !disabledExistingImageIds.includes(
                                                        img.event_image_id,
                                                    )
                                                }
                                                onChange={(e) => {
                                                    const isEnabled =
                                                        e.target.checked;
                                                    const nextDisabled =
                                                        isEnabled
                                                            ? disabledExistingImageIds.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      img.event_image_id,
                                                              )
                                                            : Array.from(
                                                                  new Set([
                                                                      ...disabledExistingImageIds,
                                                                      img.event_image_id,
                                                                  ]),
                                                              );
                                                    setDisabledExistingImageIds(
                                                        nextDisabled,
                                                    );
                                                    methods.setValue(
                                                        "disabled_event_image_ids",
                                                        nextDisabled,
                                                        {
                                                            shouldDirty: true,
                                                        },
                                                    );
                                                    methods.setValue(
                                                        "disabled_event_image_ids_present",
                                                        true,
                                                        {
                                                            shouldDirty: true,
                                                        },
                                                    );
                                                }}
                                            />
                                            Enabled
                                        </label>
                                        <Button
                                            type="button"
                                            size={"sm"}
                                            variant="secondary"
                                            onClick={() => {
                                                window.open(
                                                    img.event_image_path,
                                                    "_blank",
                                                    "noopener,noreferrer",
                                                );
                                            }}
                                        >
                                            Preview
                                        </Button>
                                    </div>
                                    <Button
                                        type="button"
                                        size={"sm"}
                                        variant="secondary"
                                        className="w-full mt-1"
                                        onClick={() => {
                                            const next = Array.from(
                                                new Set([
                                                    ...removedExistingImageIds,
                                                    img.event_image_id,
                                                ]),
                                            );
                                            setRemovedExistingImageIds(next);
                                            const nextDisabled =
                                                disabledExistingImageIds.filter(
                                                    (id) =>
                                                        id !==
                                                        img.event_image_id,
                                                );
                                            setDisabledExistingImageIds(
                                                nextDisabled,
                                            );
                                            methods.setValue(
                                                "delete_event_image_ids",
                                                next,
                                                { shouldDirty: true },
                                            );
                                            methods.setValue(
                                                "disabled_event_image_ids",
                                                nextDisabled,
                                                { shouldDirty: true },
                                            );
                                            methods.setValue(
                                                "disabled_event_image_ids_present",
                                                true,
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

                <div className="flex items-center space-x-2">
                    <input
                        type="checkbox"
                        id="is_published"
                        className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                        {...methods.register("is_published")}
                    />
                    <Label htmlFor="is_published">Published</Label>
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
                        onClick={() => router.visit(route("events.index"))}
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
