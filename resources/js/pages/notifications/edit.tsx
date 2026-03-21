import AppLayout from "@/layouts/AppLayout";
import { Head, router } from "@inertiajs/react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { ChevronLeft } from "lucide-react";
import { useMemo, useState } from "react";
import { toast } from "sonner";

type Notification = {
    notification_id: string;
    title: string;
    body?: string | null;
    audience: "all_users" | "user";
    user_id?: string | null;
    data?: any;
    status: "draft" | "sent";
};

const stringifyData = (data: any) => {
    if (!data) return "";
    try {
        return JSON.stringify(data, null, 2);
    } catch {
        return "";
    }
};

const EditNotification = ({ notification }: { notification: Notification }) => {
    const [title, setTitle] = useState(notification.title ?? "");
    const [body, setBody] = useState(notification.body ?? "");
    const [audience, setAudience] = useState<"all_users" | "user">(
        notification.audience ?? "all_users",
    );
    const [userId, setUserId] = useState(notification.user_id ?? "");
    const [data, setData] = useState(stringifyData(notification.data));
    const [sendNow, setSendNow] = useState(false);

    const canSubmit = useMemo(() => {
        if (!title.trim()) return false;
        if (audience === "user" && !userId.trim()) return false;
        return true;
    }, [title, audience, userId]);

    const handleSubmit = () => {
        if (!canSubmit) return;

        router.post(
            `/notifications/${notification.notification_id}`,
            {
                _method: "put",
                title,
                body: body.trim() ? body : null,
                audience,
                user_id: audience === "user" ? userId : null,
                data: data.trim() ? data : null,
                send_now: sendNow,
            } as any,
            {
                onSuccess: () => toast.success("Notification updated."),
                onError: () => toast.error("Failed to update notification."),
            },
        );
    };

    return (
        <AppLayout>
            <Head title="Edit Notification" />
            <div className="flex flex-col px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div className="flex items-center gap-4">
                            <Button
                                variant="default"
                                onClick={() =>
                                    router.visit(route("notifications.index"))
                                }
                            >
                                <ChevronLeft className="mr-2" size={20} />
                                Back
                            </Button>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Edit Notification
                            </h2>
                        </div>
                    </div>
                </div>

                <div className="mt-4 space-y-4">
                    <div className="rounded-lg border bg-white p-4 space-y-4">
                        <div className="space-y-2">
                            <div className="text-sm font-medium">Title</div>
                            <Input
                                value={title}
                                onChange={(e) => setTitle(e.target.value)}
                            />
                        </div>

                        <div className="space-y-2">
                            <div className="text-sm font-medium">Body</div>
                            <Textarea
                                value={body}
                                onChange={(e) => setBody(e.target.value)}
                            />
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <div className="text-sm font-medium">
                                    Audience
                                </div>
                                <Select
                                    value={audience}
                                    onValueChange={(v) =>
                                        setAudience(v as any)
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Audience" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all_users">
                                            All Users
                                        </SelectItem>
                                        <SelectItem value="user">
                                            Specific User
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            {audience === "user" ? (
                                <div className="space-y-2">
                                    <div className="text-sm font-medium">
                                        User ID
                                    </div>
                                    <Input
                                        value={userId}
                                        onChange={(e) =>
                                            setUserId(e.target.value)
                                        }
                                        placeholder="UUID"
                                    />
                                </div>
                            ) : null}
                        </div>

                        <div className="space-y-2">
                            <div className="text-sm font-medium">
                                Data (JSON)
                            </div>
                            <Textarea
                                value={data}
                                onChange={(e) => setData(e.target.value)}
                            />
                        </div>

                        {notification.status !== "sent" ? (
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    checked={sendNow}
                                    onCheckedChange={(v) =>
                                        setSendNow(Boolean(v))
                                    }
                                />
                                <div className="text-sm">Send now</div>
                            </div>
                        ) : null}
                    </div>

                    <div className="flex justify-end">
                        <Button
                            variant="default"
                            disabled={!canSubmit}
                            onClick={handleSubmit}
                        >
                            Save
                        </Button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
};

export default EditNotification;

