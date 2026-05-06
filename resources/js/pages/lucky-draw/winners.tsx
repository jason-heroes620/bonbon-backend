import AppLayout from "@/layouts/AppLayout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import axios from "axios";
import { useEffect, useState } from "react";
import { toast } from "sonner";
import { router } from "@inertiajs/react";
import { ChevronLeft } from "lucide-react";

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

const LuckyDrawWinnersPage = ({ session }: { session: Session }) => {
    const sessionId = session.id;
    const [sessionStatus, setSessionStatus] = useState<
        Session["session_status"]
    >(session.session_status);
    const [winnerCount, setWinnerCount] = useState<number>(
        Math.max(1, session.winners_count || 1),
    );
    const [loading, setLoading] = useState(false);
    const [winners, setWinners] = useState<Winner[]>([]);

    useEffect(() => {
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

    const isCompleted = sessionStatus === "completed";

    const handlePrepare = async () => {
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

    const handleCompleteSession = async () => {
        setLoading(true);
        try {
            await axios.post(route("lucky_draw.complete", sessionId));
            setSessionStatus("completed");
            toast.success("Session marked as completed");
        } catch (e: any) {
            const message =
                e?.response?.data?.message ??
                "Failed to mark session as completed";
            toast.error(message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <AppLayout>
            <div className="flex px-4 py-2 w-full">
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
                                Lucky Draw - {session.session_name}
                            </h2>
                        </div>
                        <div className="flex items-center gap-2">
                            <div className="text-sm text-gray-700">
                                {sessionStatus}
                            </div>
                            <Button
                                type="button"
                                size={"sm"}
                                variant="outline"
                                disabled={loading || isCompleted}
                                onClick={handleCompleteSession}
                            >
                                Mark Completed
                            </Button>
                        </div>
                    </div>

                    <div className="mt-4 bg-white p-6 rounded-md shadow-md">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div className="flex flex-col gap-2">
                                <Label>Winners Count</Label>
                                <Input
                                    type="number"
                                    min={1}
                                    value={winnerCount}
                                    disabled={loading || isCompleted}
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
                                    disabled={loading || isCompleted}
                                    onClick={handlePrepare}
                                >
                                    Prepare User Entries
                                </Button>
                                <Button
                                    type="button"
                                    disabled={loading || isCompleted}
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
