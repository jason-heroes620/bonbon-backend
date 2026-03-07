import { PageProps as InertiaPageProps } from "@inertiajs/core";
import { AxiosInstance } from "axios";
import { route as ziggyRoute } from "ziggy-js";
import { PageProps as AppPageProps } from ".";

declare global {
    interface Window {
        axios: AxiosInstance;
        google: typeof google;
    }

    /* eslint-disable no-var */
    var route: typeof ziggyRoute;
}

declare namespace NodeJS {
    interface ProcessEnv {
        GOOGLE_MAPS_API_KEY: string;
    }
}

declare module "@inertiajs/core" {
    interface PageProps extends InertiaPageProps, AppPageProps {}
}
