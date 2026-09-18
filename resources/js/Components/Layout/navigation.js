import {
    LayoutDashboard,
    FileText,
    LayoutTemplate,
    Users,
    Settings,
    WalletCards,
    Zap,
} from "lucide-vue-next";

export const navigation = [
    {
        title: "Workspace",

        items: [
            {
                title: "Dashboard",
                icon: LayoutDashboard,
                route: "dashboard",
            },

            {
                title: "Documents",
                icon: FileText,
                route: "documents.index",
            },

            {
                title: "Templates",
                icon: LayoutTemplate,
                route: "templates.index",
            },

            {
                title: "Members",
                icon: Users,
                route: "members.index",
            },

            {
                title: "Billing",
                icon: WalletCards,
                route: "billing.index",
                ownerOnly: true,
            },

            {
                title: "Plan",
                icon: Zap,
                route: "plan.index",
                ownerOnly: true,
            },
        ],
    },

    {
        title: "System",

        items: [
            {
                title: "Settings",
                icon: Settings,
                route: "profile.edit",
            },
        ],
    },
];
