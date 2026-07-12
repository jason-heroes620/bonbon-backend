import "../css/app.css";
import "./bootstrap";

import { createInertiaApp } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createRoot } from "react-dom/client";
import { Toaster } from "@/components/ui/sonner";
import React, { useEffect } from "react";
import { toast } from "sonner";
import { configureEcho } from "@laravel/echo-react";

configureEcho({
    broadcaster: "pusher",
});

configureEcho({
    broadcaster: "reverb",
});

const appName = import.meta.env.VITE_APP_NAME || "BonBon";

function AppWrapper({ App, props }: any) {
    const { flash } = props.initialPage.props;

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    return (
        <>
            <App {...props} />
            <Toaster
                position="top-right"
                toastOptions={{
                    duration: 2000,
                    style: {
                        background: "#fff",
                        color: "#374151",
                        padding: "16px 20px",
                        borderRadius: "12px",
                        fontSize: "14px",
                        fontWeight: "500",
                        boxShadow:
                            "0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)",
                        border: "1px solid #E5E7EB",
                        maxWidth: "420px",
                    },
                }}
            />
        </>
    );
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob("./pages/**/*.tsx"),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);
        root.render(
            <React.StrictMode>
                <AppWrapper App={App} props={props} />
            </React.StrictMode>,
        );
    },
    progress: {
        color: "#EA580C",
    },
});
