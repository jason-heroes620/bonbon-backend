import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { UserEmailTagger } from "@/components/gallery/user-email-tagger";
import { router } from "@inertiajs/react";
import { ImagePlus, X } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

export function GalleryUploadForm({ onSuccess }: { onSuccess?: () => void }) {
    const [files, setFiles] = useState<File[]>([]);
    const [previews, setPreviews] = useState<string[]>([]);
    const [title, setTitle] = useState("");
    const [caption, setCaption] = useState("");
    const [emails, setEmails] = useState<string[]>([]);
    const [submitting, setSubmitting] = useState(false);
    const [progress, setProgress] = useState(0);

    const handleFiles = (selected: FileList | null) => {
        if (!selected || selected.length === 0) return;

        const next = [...files, ...Array.from(selected)];
        setFiles(next);
        setPreviews(next.map((file) => URL.createObjectURL(file)));
    };

    const removeFile = (index: number) => {
        const next = files.filter((_, i) => i !== index);
        setFiles(next);
        setPreviews(next.map((file) => URL.createObjectURL(file)));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (files.length === 0) {
            toast.error("Please select at least one image.");
            return;
        }

        setSubmitting(true);
        router.post(
            route("gallery.store"),
            {
                images: files,
                title: title || null,
                caption: caption || null,
                emails,
            } as any,
            {
                forceFormData: true,
                onProgress: (event) => setProgress(event?.percentage ?? 0),
                onSuccess: () => {
                    toast.success("Images uploaded successfully");
                    setFiles([]);
                    setPreviews([]);
                    setTitle("");
                    setCaption("");
                    setEmails([]);
                    onSuccess?.();
                },
                onError: (errors: Record<string, string>) => {
                    toast.error("Upload failed");
                    Object.values(errors).forEach((error) =>
                        toast.error(error),
                    );
                },
                onFinish: () => {
                    setSubmitting(false);
                    setProgress(0);
                },
            },
        );
    };

    return (
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
            <div className="flex flex-col gap-2">
                <Label htmlFor="gallery_files">Images</Label>
                <label
                    htmlFor="gallery_files"
                    className="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-md border-2 border-dashed border-[#D1D5DB] bg-gray-50 px-4 py-8 text-center text-sm text-gray-500 hover:bg-gray-100"
                >
                    <ImagePlus className="h-6 w-6" />
                    <span>
                        Click to select images (JPG, PNG, WEBP — multiple
                        allowed)
                    </span>
                    <input
                        id="gallery_files"
                        type="file"
                        multiple
                        accept="image/jpeg,image/png,image/webp"
                        className="hidden"
                        onChange={(e) => handleFiles(e.target.files)}
                    />
                </label>

                {previews.length > 0 && (
                    <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
                        {previews.map((preview, index) => (
                            <div
                                key={preview}
                                className="relative aspect-square overflow-hidden rounded-md border"
                            >
                                <img
                                    src={preview}
                                    alt=""
                                    className="h-full w-full object-cover"
                                />
                                <button
                                    type="button"
                                    onClick={() => removeFile(index)}
                                    className="absolute right-1 top-1 rounded-full bg-black/60 p-1 text-white"
                                    aria-label="Remove image"
                                >
                                    <X size={12} />
                                </button>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <div className="flex flex-col gap-2">
                <Label htmlFor="gallery_title">Title (optional)</Label>
                <Input
                    id="gallery_title"
                    type="text"
                    maxLength={150}
                    value={title}
                    onChange={(e) => setTitle(e.target.value)}
                    className="border border-[#D1D5DB] rounded-md px-4 py-2"
                />
            </div>

            <div className="flex flex-col gap-2">
                <Label htmlFor="gallery_caption">Caption (optional)</Label>
                <Textarea
                    id="gallery_caption"
                    maxLength={2000}
                    value={caption}
                    onChange={(e) => setCaption(e.target.value)}
                    className="border border-[#D1D5DB] rounded-md px-4 py-2"
                />
            </div>

            <UserEmailTagger value={emails} onChange={setEmails} />

            {submitting && progress > 0 && (
                <div className="h-2 w-full overflow-hidden rounded-full bg-gray-200">
                    <div
                        className="h-full bg-[#3730A3] transition-all"
                        style={{ width: `${progress}%` }}
                    />
                </div>
            )}

            <div className="flex justify-end gap-2">
                <Button type="submit" size={"sm"} disabled={submitting}>
                    {submitting ? "Uploading..." : "Upload"}
                </Button>
            </div>
        </form>
    );
}
