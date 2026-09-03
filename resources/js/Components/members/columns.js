import { h } from "vue";
import MemberRoleBadge from "./MemberRoleBadge.vue";

export function createColumns({
    canManageMembers = false,
    currentUserId = null,
    onRoleChange = null,
}) {
    return [
        {
            accessorKey: "name",
            header: "Member",

            cell: ({ row }) =>
                h(
                    "div",
                    {
                        class: "space-y-1",
                    },
                    [
                        h(
                            "div",
                            {
                                class: "font-medium",
                            },
                            row.original.name,
                        ),

                        h(
                            "div",
                            {
                                class: "text-xs text-muted-foreground",
                            },
                            row.original.email,
                        ),
                    ],
                ),
        },

        {
            accessorKey: "role",
            header: "Role",

            cell: ({ row }) =>
                h(MemberRoleBadge, {
                    role: row.original.role,
                }),
        },

        {
            id: "actions",

            header: () =>
                h(
                    "div",
                    {
                        class: "text-right",
                    },
                    "Actions",
                ),

            cell: ({ row }) => {
                const member = row.original;

                const isOwner = member.role === "owner";
                const isCurrentUser = member.id === currentUserId;

                const canChangeRole =
                    canManageMembers && !isOwner && !isCurrentUser;

                if (!canChangeRole) {
                    return h(
                        "div",
                        {
                            class: "text-right",
                        },
                        null,
                    );
                }

                return h(
                    "div",
                    {
                        class: "flex justify-end",
                    },
                    [
                        h(
                            "button",
                            {
                                type: "button",

                                class: [
                                    "inline-flex",
                                    "items-center",
                                    "justify-center",
                                    "rounded-md",
                                    "border",
                                    "border-border",
                                    "bg-background",
                                    "px-3",
                                    "py-1.5",
                                    "text-xs",
                                    "font-medium",
                                    "text-foreground",
                                    "transition-colors",
                                    "hover:bg-muted",
                                    "focus-visible:outline-none",
                                    "focus-visible:ring-2",
                                    "focus-visible:ring-ring",
                                    "focus-visible:ring-offset-2",
                                ],

                                onClick: () => {
                                    onRoleChange?.(member);
                                },
                            },

                            "Change Role",
                        ),
                    ],
                );
            },
        },
    ];
}
