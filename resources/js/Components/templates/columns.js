import { h } from "vue";
import { Link } from "@inertiajs/vue3";

import { Eye } from "lucide-vue-next";

import { Button } from "@/components/ui/button";

import DataTableColumnHeader from "@/Components/data-table/DataTableColumnHeader.vue";

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
            label: "Template",
        },
        header: ({ column }) =>
            h(DataTableColumnHeader, {
                column,
                title: "Template",
            }),

        cell: ({ row }) =>
            h(
                Link,
                {
                    href: route("templates.show", row.original.id),
                    class: "font-medium hover:underline break-words",
                },
                () => row.original.name,
            ),
    },

    {
        accessorKey: "signature_fields_count",
        meta: { label: "Fields" },
        header: ({ column }) =>
            h(DataTableColumnHeader, { column, title: "Fields" }),
        cell: ({ row }) => row.original.signature_fields_count ?? 0,
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
                                "templates.show",
                                row.original.id,
                            )),
                    },
                    () => h(Eye, { class: "h-4 w-4" }),
                ),
            ]),
    },
];
