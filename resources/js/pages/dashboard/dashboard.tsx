import AppLayout from "@/layouts/AppLayout";

const Dashboard = () => {
    return (
        <AppLayout>
            <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                <div className="aspect-video rounded-xl bg-muted/50 p-4 flex items-center justify-center">
                    <span className="text-muted-foreground">Stats 1</span>
                </div>
                <div className="aspect-video rounded-xl bg-muted/50 p-4 flex items-center justify-center">
                    <span className="text-muted-foreground">Stats 2</span>
                </div>
                <div className="aspect-video rounded-xl bg-muted/50 p-4 flex items-center justify-center">
                    <span className="text-muted-foreground">Stats 3</span>
                </div>
            </div>
            <div className="min-h-[100vh] flex-1 rounded-xl bg-muted/50 md:min-h-min p-6">
                <h2 className="text-2xl font-bold mb-4">
                    Welcome to your Dashboard
                </h2>
                <p className="text-muted-foreground">
                    This is where your main content will go.
                </p>
            </div>
        </AppLayout>
    );
};

export default Dashboard;
