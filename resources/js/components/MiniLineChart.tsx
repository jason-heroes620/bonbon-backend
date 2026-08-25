import {
    CartesianGrid,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from "recharts";

type SalesChartPoint = {
    month: string;
    label: string;
    total: number;
};

const MiniLineChart = ({ points }: { points: SalesChartPoint[] }) => {
    if (!points.length) {
        return (
            <div className="flex h-48 items-center justify-center text-sm text-muted-foreground">
                No data available
            </div>
        );
    }

    return (
        <div className="w-full">
            <ResponsiveContainer width="100%" height={250}>
                <LineChart
                    data={points}
                    margin={{
                        top: 10,
                        right: 30,
                        left: 30,
                        bottom: 10,
                    }}
                >
                    <CartesianGrid
                        strokeDasharray="3 3"
                        vertical={false}
                        className="stroke-muted"
                    />

                    <XAxis
                        dataKey="label"
                        tick={{ fontSize: 11 }}
                        interval={0}
                        angle={-20}
                        tickLine={false}
                        axisLine={false}
                    />

                    <YAxis hide />

                    <Tooltip
                        cursor={{
                            stroke: "currentColor",
                            strokeWidth: 1,
                        }}
                        formatter={(value) => [value, "Sales"]}
                        labelFormatter={(label) => label}
                    />

                    <Line
                        type="monotone"
                        dataKey="total"
                        stroke="#3730A3"
                        strokeWidth={2}
                        dot={{
                            r: 4,
                            fill: "#3730A3",
                        }}
                        activeDot={{
                            r: 6,
                        }}
                        animationDuration={700}
                    />
                </LineChart>
            </ResponsiveContainer>
        </div>
    );
};

export { MiniLineChart, type SalesChartPoint };
