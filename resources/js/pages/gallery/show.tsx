import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { UserEmailTagger } from "@/components/gallery/user-email-tagger";
import AppLayout from "@/layouts/AppLayout";
import type { GalleryImage } from "@/types";
import { router } from "@inertiajs/react";
import { ArrowLeft, Trash2, UserMinus } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

const Show = ({ image }: { image: GalleryImage }) => {
    const [title, setTitle] = useState(image.title ?? "");
    const [caption, setCaption] = useState(image.caption ?? "");
    const [isActive, setIsActive] = useState(Boolean(image.is_active));
    const [emails, setEmails] = useState<string[]>([]);
    const [saving, setSaving] = useState(false);

    const taggedUsers = image.tagged_users ?? [];

    const saveDetails = () => {
        setSaving(true);
        router.put(
            route("gallery.update", image.gallery_image_id),
            { title: title || null, caption: caption || null, is_active: isActive } as any,
            {
                onSuccess: () => toast.success("Image updated"),
                onError: () => toast.error("Failed to update image"),
                onFinish: () => setSaving(false),
            },
        );
    };

    const tagUsers = () => {
        if (emails.length === 0) {
            toast.error("Add at least one email.");
            return;
        }
        router.post(
            route("gallery.tags.store", image.gallery_image_id),
            { emails } as any,
            {
                onSuccess: () => {
                    toast.success("Users tagged");
                    setEmails([]);
                },
                onError: () => toast.error("Failed to tag users"),
            },
        );
    };

    const untagUser = (email: string) => {
        router.delete(route("gallery.tags.destroy", image.gallery_image_id), {
            data: { email },
            onSuccess: () => toast.success("User untagged"),
            onError: () => toast.error("Failed to untag user"),
        });
    };

    const deleteImage = () => {
        if (
            !window.confirm(
                "Delete this image permanently? This cannot be undone.",
            )
        ) {
            return;
        }
        router.delete(route("gallery.destroy", image.gallery_image_id), {
            onSuccess: () => {
                toast.success("Image deleted");
                router.visit(route("gallery.index"));
            },
            onError: () => toast.error("Failed to delete image"),
        });
    };

    return (
        <AppLayout>
            <div className="flex flex-col gap-4 px-4 py-2">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => router.visit(route("gallery.index"))}
                        >
                            <ArrowLeft />
                        </Button>
                        <h2 className="text-lg font-bold text-[#3730A3]">
                            Image Details
                        </h2>
                    </div>
                    <Button variant="destructive" onClick={deleteImage}>
                        <Trash2 className="mr-2" size={18} />
                        Delete
                    </Button>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Preview</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <img
                                src={image.optimized_url ?? image.original_url}
                                alt={image.title ?? ""}
                                className="max-h-[480px] w-full rounded-md object-contain"
                            />
                            <div className="mt-2 flex flex-wrap gap-4 text-sm text-gray-500">
                                <span>Status: {image.status}</span>
                                {image.image_width && image.image_height && (
                                    <span>
                                        {image.image_width} ×{" "}
                                        {image.image_height}
                                    </span>
                                )}
                                {image.file_size_bytes && (
                                    <span>
                                        {(
                                            image.file_size_bytes / 1024
                                        ).toFixed(0)}{" "}
                                        KB
                                    </span>
                                )}
                            </div>
                            {image.failure_reason && (
                                <p className="mt-2 text-sm text-red-500">
                                    {image.failure_reason}
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <div className="flex flex-col gap-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Details</CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4">
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="title">Title</Label>
                                    <Input
                                        id="title"
                                        type="text"
                                        maxLength={150}
                                        value={title}
                                        onChange={(e) =>
                                            setTitle(e.target.value)
                                        }
                                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                                    />
                                </div>

                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="caption">Caption</Label>
                                    <Textarea
                                        id="caption"
                                        maxLength={2000}
                                        value={caption}
                                        onChange={(e) =>
                                            setCaption(e.target.value)
                                        }
                                        className="border border-[#D1D5DB] rounded-md px-4 py-2"
                                    />
                                </div>

                                <div className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        id="is_active"
                                        className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                                        checked={isActive}
                                        onChange={(e) =>
                                            setIsActive(e.target.checked)
                                        }
                                    />
                                    <Label htmlFor="is_active">Active</Label>
                                </div>

                                <div className="flex justify-end">
                                    <Button
                                        size={"sm"}
                                        disabled={saving}
                                        onClick={saveDetails}
                                    >
                                        {saving ? "Saving..." : "Save"}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Tagged Users</CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4">
                                <div className="flex flex-col gap-2">
                                    {taggedUsers.map((tag) => (
                                        <div
                                            key={tag.gallery_image_user_id}
                                            className="flex items-center justify-between rounded-md border px-3 py-2"
                                        >
                                            <div className="flex flex-col">
                                                <span className="text-sm">
                                                    {tag.user
                                                        ? `${tag.user.first_name} ${tag.user.last_name}`
                                                        : tag.email}
                                                </span>
                                                <span className="text-xs text-gray-500">
                                                    {tag.email}
                                                </span>
                                            </div>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    untagUser(tag.email)
                                                }
                                                aria-label={`Untag ${tag.email}`}
                                            >
                                                <UserMinus size={16} />
                                            </Button>
                                        </div>
                                    ))}
                                    {taggedUsers.length === 0 && (
                                        <p className="text-sm text-gray-500">
                                            No users tagged yet.
                                        </p>
                                    )}
                                </div>

                                <UserEmailTagger
                                    value={emails}
                                    onChange={setEmails}
                                    label="Add users"
                                />

                                <div className="flex justify-end">
                                    <Button
                                        size={"sm"}
                                        variant="secondary"
                                        onClick={tagUsers}
                                    >
                                        Tag Users
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
};

export default Show;
