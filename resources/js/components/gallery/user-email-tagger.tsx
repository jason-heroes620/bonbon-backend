import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { X } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import axios from "axios";

type UserOption = {
    user_id: string;
    first_name: string;
    last_name: string;
    email: string;
};

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function UserEmailTagger({
    value,
    onChange,
    label = "Tag users by email",
    placeholder = "Search name or email...",
}: {
    value: string[];
    onChange: (emails: string[]) => void;
    label?: string;
    placeholder?: string;
}) {
    const [query, setQuery] = useState("");
    const [results, setResults] = useState<UserOption[]>([]);
    const [loading, setLoading] = useState(false);
    const [open, setOpen] = useState(false);
    const blurTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        const term = query.trim();
        if (term.length < 2) {
            setResults([]);
            setLoading(false);
            return;
        }

        setLoading(true);
        const handler = setTimeout(async () => {
            try {
                const response = await axios.get("/users/options", {
                    params: { q: term },
                });
                const list = (response.data?.data ?? []) as UserOption[];
                setResults(
                    list.filter((option) => !value.includes(option.email)),
                );
            } catch {
                setResults([]);
            } finally {
                setLoading(false);
            }
        }, 300);

        return () => clearTimeout(handler);
    }, [query, value]);

    const addEmail = (email: string) => {
        const normalized = email.trim().toLowerCase();
        if (!EMAIL_PATTERN.test(normalized) || value.includes(normalized)) {
            return;
        }
        onChange([...value, normalized]);
        setQuery("");
        setResults([]);
    };

    const removeEmail = (email: string) => {
        onChange(value.filter((item) => item !== email));
    };

    return (
        <div className="flex flex-col gap-2">
            <Label>{label}</Label>

            {value.length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {value.map((email) => (
                        <span
                            key={email}
                            className="inline-flex items-center gap-1 rounded-full bg-[#3730A3]/10 px-3 py-1 text-sm text-[#3730A3]"
                        >
                            {email}
                            <button
                                type="button"
                                onClick={() => removeEmail(email)}
                                className="text-[#3730A3]/70 hover:text-[#3730A3]"
                                aria-label={`Remove ${email}`}
                            >
                                <X size={14} />
                            </button>
                        </span>
                    ))}
                </div>
            )}

            <div className="relative">
                <Input
                    type="text"
                    value={query}
                    placeholder={placeholder}
                    className="border border-[#D1D5DB] rounded-md px-4 py-2"
                    onChange={(e) => setQuery(e.target.value)}
                    onFocus={() => setOpen(true)}
                    onBlur={() => {
                        blurTimeout.current = setTimeout(
                            () => setOpen(false),
                            200,
                        );
                    }}
                    onKeyDown={(e) => {
                        if (e.key === "Enter") {
                            e.preventDefault();
                            addEmail(query);
                        }
                    }}
                />

                {open && query.trim().length >= 2 && (
                    <div className="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto rounded-md border bg-white shadow-lg">
                        {loading && (
                            <div className="px-4 py-2 text-sm text-gray-500">
                                Searching...
                            </div>
                        )}
                        {!loading && results.length === 0 && (
                            <div className="px-4 py-2 text-sm text-gray-500">
                                No users found. Press Enter to tag "
                                {query.trim()}" directly.
                            </div>
                        )}
                        {!loading &&
                            results.map((option) => (
                                <button
                                    key={option.user_id}
                                    type="button"
                                    className="block w-full px-4 py-2 text-left text-sm hover:bg-gray-100"
                                    onMouseDown={(e) => e.preventDefault()}
                                    onClick={() => {
                                        if (blurTimeout.current) {
                                            clearTimeout(blurTimeout.current);
                                        }
                                        addEmail(option.email);
                                        setOpen(false);
                                    }}
                                >
                                    <span className="font-medium">
                                        {option.first_name} {option.last_name}
                                    </span>
                                    <span className="ml-2 text-gray-500">
                                        {option.email}
                                    </span>
                                </button>
                            ))}
                    </div>
                )}
            </div>
        </div>
    );
}
