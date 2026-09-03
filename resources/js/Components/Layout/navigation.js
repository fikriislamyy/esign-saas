import {
    LayoutDashboard,
    FileText,
    Users,
    Settings,
    WalletCards,
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
