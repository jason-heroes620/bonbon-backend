import AppLayout from "@/layouts/AppLayout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import axios from "axios";
import { useEffect, useMemo, useState } from "react";
import { toast } from "sonner";

type Session = {
    id: number;
    session_name: string;
    session_status: "pending" | "completed";
    winners_count: number;
    session_start_time: string;
    session_end_time: string;
};

type Winner = {
    id?: number;
    user_id: string;
    email: string;
    winning_ticket_number: number;
    won_at?: string;
};

const LuckyDrawWinnersPage = ({
    sessions,
    initialSessionId,
}: {
    sessions: Session[];
    initialSessionId?: number | string | null;
}) => {
    const defaultSessionId = useMemo(() => {
        const candidate =
            initialSessionId !== undefined &&
            initialSessionId !== null &&
            String(initialSessionId) !== ""
                ? Number(initialSessionId)
                : null;
        if (
            candidate !== null &&
            !Number.isNaN(candidate) &&
            sessions.some((s) => s.id === candidate)
        ) {
            return candidate;
        }
        const pending = sessions.find((s) => s.session_status === "pending");
        return pending?.id ?? sessions[0]?.id ?? null;
    }, [initialSessionId, sessions]);

    const [sessionId, setSessionId] = useState<number | null>(defaultSessionId);
    const [winnerCount, setWinnerCount] = useState<number>(2);
    const [loading, setLoading] = useState(false);
    const [winners, setWinners] = useState<Winner[]>([]);

    useEffect(() => {
        if (!sessionId) {
            setWinners([]);
            return;
        }

        setLoading(true);
        axios
            .get(route("lucky_draw.winners", sessionId))
            .then((res) => {
                setWinners((res.data?.data as Winner[]) ?? []);
            })
            .catch(() => {
                toast.error("Failed to load winners");
            })
            .finally(() => setLoading(false));
    }, [sessionId]);

    const handlePrepare = async () => {
        if (!sessionId) return;
        setLoading(true);
        try {
            const res = await axios.post(
                route("lucky_draw.prepare", sessionId),
            );
            const inserted = res.data?.data?.inserted;
            toast.success(
                typeof inserted === "number"
                    ? `Prepared ${inserted} entries`
                    : "Entries prepared",
            );
        } catch {
            toast.error("Failed to prepare entries");
        } finally {
            setLoading(false);
        }
    };

    const handleDraw = async () => {
        if (!sessionId) return;
        setLoading(true);
        try {
            const res = await axios.post(route("lucky_draw.draw", sessionId), {
                winner_count: winnerCount,
            });
            const newWinners = (res.data?.data?.winners as Winner[]) ?? [];
            if (newWinners.length === 0) {
                toast.error("No more entries to draw from");
                return;
            }
            toast.success(`Picked ${newWinners.length} winner(s)`);

            const winnersRes = await axios.get(
                route("lucky_draw.winners", sessionId),
            );
            setWinners((winnersRes.data?.data as Winner[]) ?? []);
        } catch {
            toast.error("Failed to run draw");
        } finally {
            setLoading(false);
        }
    };

    return (
        <AppLayout>
            <div className="flex px-4 py-2 w-full">
                <div className="flex-1">
                    <div className="flex justify-between items-center bg-[#3730A3]/20 px-4 py-2 rounded-md">
                        <div>
                            <h2 className="text-lg font-bold text-[#3730A3]">
                                Lucky Draw
                            </h2>
                        </div>
                    </div>

                    <div className="mt-4 bg-white p-6 rounded-md shadow-md">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div className="flex flex-col gap-2">
                                <Label>Session</Label>
                                <select
                                    className="border border-[#D1D5DB] rounded-md px-3 py-2"
                                    value={sessionId ?? ""}
                                    onChange={(e) => {
                                        const v = e.target.value;
                                        setSessionId(v ? Number(v) : null);
                                    }}
                                >
                                    <option value="" disabled>
                                        Select session
                                    </option>
                                    {sessions.map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {s.session_name} ({s.session_status}
                                            )
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="flex flex-col gap-2">
                                <Label>Winners Count</Label>
                                <Input
                                    type="number"
                                    min={1}
                                    value={winnerCount}
                                    onChange={(e) =>
                                        setWinnerCount(
                                            Math.max(1, Number(e.target.value)),
                                        )
                                    }
                                />
                            </div>

                            <div className="flex items-end gap-2">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    disabled={!sessionId || loading}
                                    onClick={handlePrepare}
                                >
                                    Prepare User Entries
                                </Button>
                                <Button
                                    type="button"
                                    disabled={!sessionId || loading}
                                    onClick={handleDraw}
                                >
                                    Run Draw
                                </Button>
                            </div>
                        </div>

                        <div className="mt-6">
                            <div className="flex items-center justify-between">
                                <h3 className="font-semibold">Winners</h3>
                                <div className="text-sm text-gray-600">
                                    {loading
                                        ? "Loading..."
                                        : `${winners.length} winner(s)`}
                                </div>
                            </div>

                            <div className="mt-2 overflow-x-auto">
                                <table className="min-w-full text-sm">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="text-left py-2 pr-4">
                                                Ticket
                                            </th>
                                            <th className="text-left py-2 pr-4">
                                                User ID
                                            </th>
                                            <th className="text-left py-2 pr-4">
                                                Email
                                            </th>
                                            <th className="text-left py-2 pr-4">
                                                Won At
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {winners.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={4}
                                                    className="py-4 text-gray-500"
                                                >
                                                    No winners yet
                                                </td>
                                            </tr>
                                        ) : (
                                            winners.map((w, idx) => (
                                                <tr
                                                    key={`${w.user_id}-${idx}`}
                                                    className="border-b"
                                                >
                                                    <td className="py-2 pr-4">
                                                        {
                                                            w.winning_ticket_number
                                                        }
                                                    </td>
                                                    <td className="py-2 pr-4">
                                                        {w.user_id}
                                                    </td>
                                                    <td className="py-2 pr-4">
                                                        {w.email}
                                                    </td>
                                                    <td className="py-2 pr-4">
                                                        {w.won_at ?? "-"}
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
};

export default LuckyDrawWinnersPage;
