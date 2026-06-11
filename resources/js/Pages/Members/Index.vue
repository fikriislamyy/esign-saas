<script setup>
import { Head, useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { router } from "@inertiajs/vue3";

import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from "@/components/ui/card";

import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";

defineProps({
    members: Array,
    invitations: Array,
    canManageMembers: Boolean,
});

const form = useForm({
    email: "",
    role: "member",
});

const submit = () => {
    form.post(route("invitations.store"), {
        preserveScroll: true,
        onSuccess: () => form.reset("email"),
    });
};

const revokeInvitation = (invitation) => {
    if (confirm(`Revoke invitation for ${invitation.email}?`)) {
        router.delete(route("invitations.destroy", invitation.id));
    }
};

const copyInvitationLink = (invitation) => {
    const url = `${window.location.origin}/invitations/${invitation.token}`;

    navigator.clipboard.writeText(url);

    alert("Invitation link copied");
};

const resendInvitation = (invitation) => {
    router.post(route("invitations.resend", invitation.id));
};
</script>

<template>
    <Head title="Members" />

    <AppLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div>
                <h1 class="text-3xl font-bold">Members</h1>

                <p class="text-muted-foreground">
                    Manage organization members.
                </p>
            </div>

            <!-- Invite Card -->
            <Card v-if="canManageMembers">
                <CardHeader>
                    <CardTitle> Invite Member </CardTitle>

                    <CardDescription>
                        Invite a new member to your organization.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <div class="flex flex-col md:flex-row gap-3">
                        <Input
                            v-model="form.email"
                            type="email"
                            placeholder="john@example.com"
                            class="flex-1"
                        />

                        <Select v-model="form.role">
                            <SelectTrigger class="w-full md:w-[180px]">
                                <SelectValue />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectItem value="member"> Member </SelectItem>

                                <SelectItem value="admin"> Admin </SelectItem>
                            </SelectContent>
                        </Select>

                        <Button @click="submit" :disabled="form.processing">
                            Invite
                        </Button>
                    </div>

                    <p
                        v-if="form.errors.email"
                        class="text-sm text-red-500 mt-2"
                    >
                        {{ form.errors.email }}
                    </p>
                </CardContent>
            </Card>

            <!-- Members Table -->
            <Card>
                <CardHeader>
                    <CardTitle> Team Members </CardTitle>
                </CardHeader>

                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr
                                    class="border-b text-sm text-muted-foreground"
                                >
                                    <th class="text-left p-4">Name</th>

                                    <th class="text-left p-4">Email</th>

                                    <th class="text-left p-4">Role</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="member in members"
                                    :key="member.id"
                                    class="border-b"
                                >
                                    <td class="p-4 font-medium">
                                        {{ member.name }}
                                    </td>

                                    <td class="p-4">
                                        {{ member.email }}
                                    </td>

                                    <td class="p-4 capitalize">
                                        {{ member.role }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle> Pending Invitations </CardTitle>

                    <CardDescription>
                        Invitations waiting to be accepted.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <div
                        v-if="invitations.length === 0"
                        class="text-muted-foreground"
                    >
                        No pending invitations.
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="invitation in invitations"
                            :key="invitation.id"
                            class="border rounded-lg p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-4"
                        >
                            <div>
                                <div class="font-medium">
                                    {{ invitation.email }}
                                </div>

                                <div
                                    class="text-sm text-muted-foreground capitalize"
                                >
                                    {{ invitation.role }}
                                </div>

                                <div class="text-xs text-muted-foreground mt-1">
                                    Created:
                                    {{
                                        new Date(
                                            invitation.created_at,
                                        ).toLocaleString()
                                    }}
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <Button
                                    variant="outline"
                                    @click="copyInvitationLink(invitation)"
                                >
                                    Copy Link
                                </Button>

                                <Button
                                    variant="outline"
                                    @click="resendInvitation(invitation)"
                                >
                                    Resend
                                </Button>

                                <Button
                                    variant="destructive"
                                    @click="revokeInvitation(invitation)"
                                >
                                    Revoke
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
