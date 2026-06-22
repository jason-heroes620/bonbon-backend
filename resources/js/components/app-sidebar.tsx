import {
    Home,
    LogOut,
    Package,
    Sparkles,
    Ticket,
    Building2,
    Settings,
    Users,
    Award,
    ChevronDown,
    Receipt,
    Percent,
    BarChart3,
    Gavel,
} from "lucide-react";
import { useEffect, useMemo, useState, type ComponentType } from "react";
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
import { cn } from "@/lib/utils";

const allItems: {
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
        title: "Events",
        icon: Sparkles,
        items: [
            {
                title: "All Events",
                url: "/events",
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
        title: "Discounts",
        icon: Percent,
        items: [
            {
                title: "All Discounts",
                url: "/discounts",
            },
        ],
    },
    {
        title: "Memberships",
        icon: Award,
        items: [
            {
                title: "All Memberships",
                url: "/memberships",
            },
        ],
    },
    {
        title: "Sales",
        icon: Receipt,
        items: [
            {
                title: "Orders",
                url: "/orders",
            },
            {
                title: "Payments",
                url: "/payments",
            },
        ],
    },
    {
        title: "Racks & Tenders",
        icon: Gavel,
        items: [
            {
                title: "Racks",
                url: "/racks",
            },
            {
                title: "Available Racks",
                url: "/available-racks",
            },
            {
                title: "Tender Summary",
                url: "/tenders-summary",
            },
            {
                title: "My Contracts",
                url: "/contracts",
            },
        ],
    },
    {
        title: "Reports",
        icon: BarChart3,
        items: [
            {
                title: "Referral Report",
                url: "/reports/referral-report",
            },
        ],
    },
    {
        title: "Lucky Draw",
        icon: Award,
        items: [
            {
                title: "All Sessions",
                url: "/lucky-draw/sessions",
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
                title: "User Interest List",
                url: "/user-interest-list",
            },
            {
                title: "KOL",
                url: "/kol",
            },
            {
                title: "Referrals",
                url: "/referrals",
            },
            {
                title: "Notifications",
                url: "/notifications",
            },
        ],
    },
    {
        title: "Configurations",
        icon: Settings,
        items: [
            {
                title: "Membership Types",
                url: "/membership-types",
            },
            {
                title: "Transaction Types",
                url: "/transaction-types",
            },

            {
                title: "Voucher / Vendor Categories",
                url: "/categories",
            },
            {
                title: "Event Categories",
                url: "/ev-categories",
            },
            {
                title: "Taxes",
                url: "/taxes",
            },
            {
                title: "Charges",
                url: "/charges",
            },
        ],
    },
];

export function AppSidebar() {
    const page = usePage();
    const currentPath = (page.url ?? "/").split("?")[0];
    const role = (page.props as any)?.auth?.user?.role as string | undefined;

    const items = useMemo(() => {
        if (role === "vendor") {
            return allItems
                .filter((item) =>
                    [
                        "Dashboard",
                        "Vendors",
                        "Products",
                        "Vouchers",
                        "Racks & Tenders",
                    ].includes(item.title),
                )
                .map((item) => {
                    if (item.title === "Vendors" && item.items?.length) {
                        return {
                            ...item,
                            items: item.items.map((sub) => ({
                                ...sub,
                                title: "My Vendors",
                            })),
                        };
                    }
                    if (item.title === "Vouchers" && item.items?.length) {
                        return {
                            ...item,
                            items: item.items.map((sub) => ({
                                ...sub,
                                title: "My Vouchers",
                            })),
                        };
                    }
                    return item;
                });
        }

        return allItems;
    }, [role]);

    const isActiveUrl = (url: string) => {
        if (currentPath === url) return true;
        if (url !== "/" && currentPath.startsWith(`${url}/`)) return true;
        return false;
    };

    const activeGroupTitle = useMemo(() => {
        for (const item of items) {
            const hasSubItems =
                Array.isArray(item.items) && item.items.length > 0;
            if (!hasSubItems) continue;
            if (item.items!.some((subItem) => isActiveUrl(subItem.url))) {
                return item.title;
            }
        }
        return null;
    }, [currentPath, items]);

    const [openGroupTitle, setOpenGroupTitle] = useState<string | null>(
        activeGroupTitle,
    );

    useEffect(() => {
        setOpenGroupTitle(activeGroupTitle);
    }, [activeGroupTitle]);

    return (
        <Sidebar>
            <SidebarHeader>
                <div className="flex items-center gap-2 px-2 py-1">
                    <div className="bg-[#F90606] p-1 rounded-md text-white">
                        <img
                            src="/bonbon-logo.png"
                            alt="Bonbon"
                            className="w-8 h-8"
                        />
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
                                const isExpanded =
                                    hasSubItems &&
                                    openGroupTitle === item.title;

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
                                                onClick={() =>
                                                    setOpenGroupTitle((prev) =>
                                                        prev === item.title
                                                            ? null
                                                            : item.title,
                                                    )
                                                }
                                                aria-expanded={Boolean(
                                                    isExpanded,
                                                )}
                                            >
                                                <item.icon />
                                                <span>{item.title}</span>
                                                {hasSubItems ? (
                                                    <ChevronDown
                                                        className={cn(
                                                            "ml-auto transition-transform",
                                                            isExpanded
                                                                ? "rotate-180"
                                                                : "rotate-0",
                                                        )}
                                                    />
                                                ) : null}
                                            </SidebarMenuButton>
                                        )}

                                        {hasSubItems && isExpanded ? (
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
