import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from "recharts";

export interface StockLocationItem {
    label: string;
    total: number;
}

const COLORS = ["#4F46E5", "#6366F1", "#818CF8", "#A5B4FC", "#C7D2FE"];

export const StockLocationBarChart = ({
    items,
}: {
    items: StockLocationItem[];
}) => {
    if (!items.length) {
        return (
            <div className="rounded-xl border border-border/50 bg-muted/50 p-6 text-center text-sm text-muted-foreground">
                No data available.
            </div>
        );
    }

    return (
        <div className="rounded-xl border border-border/50 bg-muted/50 p-5">
            <div className="mb-5">
                <h3 className="text-lg font-semibold">Stocks by Location</h3>

                <p className="text-sm text-muted-foreground">
                    Count of compartment stock products per vendor location
                </p>
            </div>

            <ResponsiveContainer width="100%" height={250}>
                <BarChart
                    data={items}
                    margin={{
                        top: 10,
                        right: 20,
                        left: 0,
                        bottom: 10,
                    }}
                >
                    <CartesianGrid strokeDasharray="3 3" vertical={false} />

                    <XAxis
                        dataKey="label"
                        tick={{ fontSize: 12 }}
                        interval={0}
                        angle={-20}
                        textAnchor="end"
                        height={60}
                    />

                    <YAxis allowDecimals={false} />

                    <Tooltip
                        cursor={{ fill: "rgba(0,0,0,0.04)" }}
                        formatter={(value) => [`${value}`, "Stocks"]}
                    />

                    <Bar
                        dataKey="total"
                        radius={[3, 3, 0, 0]}
                        animationDuration={700}
                    >
                        {items.map((_, index) => (
                            <Cell
                                key={index}
                                fill={COLORS[index % COLORS.length]}
                            />
                        ))}
                    </Bar>
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
};
