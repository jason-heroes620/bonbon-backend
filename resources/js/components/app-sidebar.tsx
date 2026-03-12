import {
    Home,
    LogOut,
    Package,
    Sparkles,
    Ticket,
    Building2,
    Settings,
    Users,
} from "lucide-react";
import type { ComponentType } from "react";
import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    SidebarFooter,
    SidebarHeader,
} from "@/components/ui/sidebar";
import { Link, usePage } from "@inertiajs/react";

const items: {
    title: string;
    url?: string;
    icon: ComponentType<any>;
    items?: { title: string; url: string }[];
}[] = [
    {
        title: "Dashboard",
        url: "/dashboard",
        icon: Home,
    },
    {
        title: "Vendors",
        icon: Building2,
        items: [
            {
                title: "All Vendors",
                url: "/vendors",
            },
        ],
    },
    {
        title: "Vouchers",
        icon: Ticket,
        items: [
            {
                title: "All Vouchers",
                url: "/vouchers",
            },
        ],
    },
    {
        title: "Products",
        icon: Package,
        items: [
            {
                title: "All Products",
                url: "/products",
            },
            {
                title: "Product Discounts",
                url: "/product-discounts",
            },
        ],
    },
    {
        title: "Users",
        icon: Users,
        items: [
            {
                title: "All Users",
                url: "/users",
            },
            {
                title: "Referrals",
                url: "/referrals",
            },
        ],
    },
    {
        title: "Configurations",
        icon: Settings,
        items: [
            {
                title: "Memberships",
                url: "/memberships",
            },
            {
                title: "Categories",
                url: "/categories",
            },
            {
                title: "Taxes",
                url: "/taxes",
            },
        ],
    },
];

export function AppSidebar() {
    const page = usePage();
    const currentPath = (page.url ?? "/").split("?")[0];

    const isActiveUrl = (url: string) => {
        if (currentPath === url) return true;
        if (url !== "/" && currentPath.startsWith(`${url}/`)) return true;
        return false;
    };

    return (
        <Sidebar>
            <SidebarHeader>
                <div className="flex items-center gap-2 px-2 py-1">
                    <div className="bg-indigo-600 p-1 rounded-md text-white">
                        <Sparkles className="size-4" />
                    </div>
                    <span className="font-semibold text-lg">Bonbon</span>
                </div>
            </SidebarHeader>
            <SidebarContent>
                <SidebarGroup>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            {items.map((item) => {
                                const hasSubItems =
                                    Array.isArray(item.items) &&
                                    item.items.length > 0;
                                const isActive =
                                    (typeof item.url === "string" &&
                                        isActiveUrl(item.url)) ||
                                    (hasSubItems &&
                                        item.items!.some((subItem) =>
                                            isActiveUrl(subItem.url),
                                        ));

                                return (
                                    <SidebarMenuItem key={item.title}>
                                        {typeof item.url === "string" ? (
                                            <SidebarMenuButton
                                                asChild
                                                isActive={isActive}
                                            >
                                                <Link href={item.url}>
                                                    <item.icon />
                                                    <span>{item.title}</span>
                                                </Link>
                                            </SidebarMenuButton>
                                        ) : (
                                            <SidebarMenuButton
                                                isActive={isActive}
                                                type="button"
                                            >
                                                <item.icon />
                                                <span>{item.title}</span>
                                            </SidebarMenuButton>
                                        )}

                                        {hasSubItems ? (
                                            <SidebarMenuSub>
                                                {item.items!.map((subItem) => {
                                                    // const SubIcon = subItem.icon
                                                    const isSubActive =
                                                        isActiveUrl(
                                                            subItem.url,
                                                        );

                                                    return (
                                                        <SidebarMenuSubItem
                                                            key={subItem.title}
                                                        >
                                                            <SidebarMenuSubButton
                                                                asChild
                                                                isActive={
                                                                    isSubActive
                                                                }
                                                            >
                                                                <Link
                                                                    href={
                                                                        subItem.url
                                                                    }
                                                                >
                                                                    {/* {SubIcon ? <SubIcon /> : null} */}
                                                                    <span>
                                                                        {
                                                                            subItem.title
                                                                        }
                                                                    </span>
                                                                </Link>
                                                            </SidebarMenuSubButton>
                                                        </SidebarMenuSubItem>
                                                    );
                                                })}
                                            </SidebarMenuSub>
                                        ) : null}
                                    </SidebarMenuItem>
                                );
                            })}
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            </SidebarContent>
            <SidebarFooter>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton asChild>
                            <Link href="/logout" method="post" as="button">
                                <LogOut />
                                <span>Logout</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarFooter>
        </Sidebar>
    );
}
