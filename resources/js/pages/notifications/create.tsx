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

const CreateNotification = () => {
    const [title, setTitle] = useState("");
    const [body, setBody] = useState("");
    const [audience, setAudience] = useState<"all_users" | "user">("all_users");
    const [userId, setUserId] = useState("");
    const [data, setData] = useState("");
    const [sendNow, setSendNow] = useState(true);

    const canSubmit = useMemo(() => {
        if (!title.trim()) return false;
        if (audience === "user" && !userId.trim()) return false;
        return true;
    }, [title, audience, userId]);

    const handleSubmit = () => {
        if (!canSubmit) return;

        router.post(
            route("notifications.store"),
            {
                title,
                body: body.trim() ? body : null,
                audience,
                user_id: audience === "user" ? userId : null,
                data: data.trim() ? data : null,
                send_now: sendNow,
            } as any,
            {
                onSuccess: () => toast.success("Notification saved."),
                onError: () => toast.error("Failed to save notification."),
            },
        );
    };

    return (
        <AppLayout>
            <Head title="Create Notification" />
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
                                Create Notification
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
                                placeholder="e.g. New voucher available"
                            />
                        </div>

                        <div className="space-y-2">
                            <div className="text-sm font-medium">Body</div>
                            <Textarea
                                value={body}
                                onChange={(e) => setBody(e.target.value)}
                                placeholder="Optional message"
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
                                placeholder='e.g. {"screen":"VoucherDetails","voucher_id":"..."}'
                            />
                        </div>

                        <div className="flex items-center gap-2">
                            <Checkbox
                                checked={sendNow}
                                onCheckedChange={(v) => setSendNow(Boolean(v))}
                            />
                            <div className="text-sm">Send now</div>
                        </div>
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

export default CreateNotification;

