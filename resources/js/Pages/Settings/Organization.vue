<script setup>
import { Head, useForm, usePage } from "@inertiajs/vue3";

import AppLayout from "@/Layouts/AppLayout.vue";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

import { Input } from "@/components/ui/input";

import { Label } from "@/components/ui/label";

import { Button } from "@/components/ui/button";

const page = usePage();

const organization = page.props.organization;

const form = useForm({
    name: organization.name,
    logo: null,
});

const handleLogoChange = (event) => {
    form.logo = event.target.files[0];
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        _method: "PATCH",
    })).post(route("settings.organization.update"));
};
</script>

<template>
    <Head title="Organization Settings" />

    <AppLayout>
        <div class="max-w-3xl mx-auto space-y-6">
            <div>
                <h1 class="text-3xl font-bold">Organization Settings</h1>

                <p class="text-muted-foreground">
                    Manage your organization information.
                </p>

                <div
                    v-if="$page.props.flash?.success"
                    class="rounded-lg border p-3 text-sm"
                >
                    {{ $page.props.flash.success }}
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Organization Profile</CardTitle>
                </CardHeader>

                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <Label>Organization Logo</Label>

                        <div class="flex items-center gap-4">
                            <img
                                v-if="organization.logo"
                                :src="`/storage/${organization.logo}`"
                                alt="Logo"
                                class="h-20 w-20 rounded-lg border object-cover"
                            />

                            <div
                                v-else
                                class="h-20 w-20 rounded-lg border flex items-center justify-center text-sm text-muted-foreground"
                            >
                                No Logo
                            </div>
                        </div>

                        <Input
                            type="file"
                            accept="image/*"
                            @change="handleLogoChange"
                        />
                    </div>
                    <div>
                        <Label>Organization Name</Label>

                        <Input
                            v-model="form.name"
                            placeholder="Organization Name"
                        />
                    </div>
                    <div>
                        <Label>Organization Slug</Label>

                        <Input :model-value="organization.slug" disabled />
                    </div>
                    <div>
                        <Label>Owner</Label>

                        <Input
                            :model-value="page.props.auth.user.name"
                            disabled
                        />
                    </div>

                    <div>
                        <Label>Email</Label>

                        <Input
                            :model-value="page.props.auth.user.email"
                            disabled
                        />
                    </div>

                    <div class="pt-2">
                        <Button @click="submit" :disabled="form.processing">
                            Save Changes
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
