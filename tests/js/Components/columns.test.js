import { columns as documentColumns } from "@/Components/documents/columns";
import { columns as templateColumns } from "@/Components/templates/columns";
import { createColumns as createMemberColumns } from "@/Components/members/columns";

describe("table columns", () => {
    const row = (original) => ({ original });

    it("maps document statuses to their badge variants", () => {
        const status = documentColumns.find((column) => column.accessorKey === "status");

        expect(status.cell({ row: row({ status: "draft" }) }).props.variant).toBe("warning");
        expect(status.cell({ row: row({ status: "sent" }) }).props.variant).toBe("pending");
        expect(status.cell({ row: row({ status: "completed" }) }).props.variant).toBe("success");
    });

    it("formats document and template file sizes", () => {
        const documentSize = documentColumns.find((column) => column.accessorKey === "file_size");
        const templateSize = templateColumns.find((column) => column.accessorKey === "file_size");

        expect(documentSize.cell({ row: row({ file_size: 1536 }) })).toBe("1.5 KB");
        expect(templateSize.cell({ row: row({ file_size: 2 * 1024 * 1024 }) })).toBe("2.00 MB");
    });

    it("only exposes member role changes to an authorized manager", () => {
        const member = { id: "member", role: "member" };
        const callback = vi.fn();
        const action = createMemberColumns({ canManageMembers: true, currentUserId: "owner", onRoleChange: callback }).find((column) => column.id === "actions");
        const disabled = createMemberColumns({ canManageMembers: false }).find((column) => column.id === "actions");

        const enabledNode = action.cell({ row: row(member) });
        enabledNode.children[0].props.onClick();

        expect(callback).toHaveBeenCalledWith(member);
        expect(disabled.cell({ row: row(member) }).children).toBeNull();
    });
});
