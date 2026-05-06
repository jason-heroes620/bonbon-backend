import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import AppLayout from "@/layouts/AppLayout";
import { Head, router } from "@inertiajs/react";
import { ChevronLeft } from "lucide-react";
import { useForm } from "react-hook-form";
import { toast } from "sonner";

type FormValues = {
    session_name: string;
    session_status: "pending" | "completed";
    winners_count: number;
    session_start_time: string;
    session_end_time: string;
};

const CreateLuckyDrawSession = () => {
    const methods = useForm<FormValues>({
        defaultValues: {
            session_name: "",
            session_status: "pending",
            winners_count: 1,
            session_start_time: "",
            session_end_time: "",
        },
    });

    const onSubmit = (values: FormValues) => {
        router.post(route("lucky_draw.sessions.store"), values as any, {
            onSuccess: () => {
                toast.success("Lucky draw session created");
                router.visit(route("lucky_draw.sessions"));
            },
            onError: (errors: Record<string, string>) => {
                toast.error("Failed to create lucky draw session");
                Object.values(errors).forEach((e) => toast.error(e));
            },
        });
    };

    return (
        <AppLayout>
            <Head title="Create Lucky Draw Session" />
            <div className="flex flex-col px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div className="flex items-center gap-4">
                            <Button
                                variant="default"
                                onClick={() =>
                                    router.visit(route("lucky_draw.sessions"))
                                }
                            >
                                <ChevronLeft className="mr" size={20} />
                                Back
                            </Button>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Create Lucky Draw Session
                            </h2>
                        </div>
                    </div>
                </div>

                <div className="flex-1 mt-4 bg-white p-6 rounded-md shadow-md">
                    <form
                        onSubmit={methods.handleSubmit(onSubmit)}
                        className="grid grid-cols-1 md:grid-cols-2 gap-4"
                    >
                        <div className="flex flex-col gap-2 md:col-span-2">
                            <Label htmlFor="session_name">Session Name</Label>
                            <Input
                                id="session_name"
                                type="text"
                                required
                                maxLength={255}
                                {...methods.register("session_name")}
                            />
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="session_status">Status</Label>
                            <select
                                id="session_status"
                                className="border border-[#D1D5DB] rounded-md px-3 py-2"
                                {...methods.register("session_status")}
                            >
                                <option value="pending">pending</option>
                                <option value="completed">completed</option>
                            </select>
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="winners_count">Winners Count</Label>
                            <Input
                                id="winners_count"
                                type="number"
                                min={1}
                                required
                                {...methods.register("winners_count", {
                                    valueAsNumber: true,
                                })}
                            />
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="session_start_time">
                                Session Start Time
                            </Label>
                            <Input
                                id="session_start_time"
                                type="datetime-local"
                                required
                                {...methods.register("session_start_time")}
                            />
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="session_end_time">
                                Session End Time
                            </Label>
                            <Input
                                id="session_end_time"
                                type="datetime-local"
                                required
                                {...methods.register("session_end_time")}
                            />
                        </div>

                        <div className="flex flex-end md:col-span-2 justify-end gap-2">
                            <Button
                                size={"sm"}
                                type="button"
                                variant="secondary"
                                onClick={() =>
                                    router.visit(route("lucky_draw.sessions"))
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
                                    : "Save"}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
};

export default CreateLuckyDrawSession;

