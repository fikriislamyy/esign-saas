import { h } from "vue";
import { Link } from "@inertiajs/vue3";

import { ArrowUpDown, Eye, Pencil } from "lucide-vue-next";

import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";

import DataTableColumnHeader from "@/Components/data-table/DataTableColumnHeader.vue";
import { badgeVariants } from "@/components/ui/badge";

function badgeVariant(status) {
    switch (status) {
        case "draft":
            return "warning";

        case "sent":
            return "pending";

        case "completed":
            return "success";

        default:
            return "secondary";
    }
}

function formatSize(bytes) {
    if (!bytes) return "-";

    const kb = bytes / 1024;

    if (kb < 1024) {
        return `${kb.toFixed(1)} KB`;
    }

    return `${(kb / 1024).toFixed(2)} MB`;
}

export const columns = [
    {
        accessorKey: "name",
        meta: {
            label: "Document",
        },
        header: ({ column }) =>
            h(DataTableColumnHeader, {
                column,
                title: "Document",
            }),

        cell: ({ row }) =>
            h(
                Link,
                {
                    href: route("documents.show", row.original.id),
                    class: "font-medium hover:underline",
                },
                () => row.original.name,
            ),
    },

    {
        accessorKey: "status",
        meta: {
            label: "Status",
        },
        header: ({ column }) =>
            h(DataTableColumnHeader, {
                column,
                title: "Status",
            }),

        cell: ({ row }) =>
            h(
                Badge,
                {
                    variant: badgeVariant(row.original.status),
                    class: "capitalize",
                },
                () => row.original.status,
            ),
    },

    {
        accessorKey: "file_size",
        meta: {
            label: "Size",
        },
        header: ({ column }) =>
            h(DataTableColumnHeader, {
                column,
                title: "Size",
            }),

        cell: ({ row }) => formatSize(row.original.file_size),
    },

    {
        accessorKey: "created_at",
        meta: {
            label: "Uploaded",
        },
        header: ({ column }) =>
            h(DataTableColumnHeader, {
                column,
                title: "Uploaded",
            }),

        cell: ({ row }) => row.original.created_at_human,
    },

    {
        id: "actions",
        meta: {
            label: "Actions",
        },
        enableSorting: false,

        enableHiding: false,

        cell: ({ row }) =>
            h("div", { class: "flex justify-end gap-2" }, [
                h(
                    Button,
                    {
                        size: "icon",
                        variant: "ghost",
                        onClick: () =>
                            (window.location.href = route(
                                "documents.show",
                                row.original.id,
                            )),
                    },
                    () => h(Eye, { class: "h-4 w-4" }),
                ),
            ]),
    },
];
