<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";

const form = useForm({
    organization_name: "",
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
});

const submit = () => {
    form.post(route("register"), {
        onFinish: () => form.reset("password", "password_confirmation"),
    });
};
</script>

<template>
    <Head title="Register" />

    <div
        class="min-h-screen flex items-center justify-center bg-background px-4 py-10"
    >
        <Card class="w-full max-w-md">
            <CardHeader>
                <CardTitle>Create Organization</CardTitle>

                <CardDescription> Create your ESign workspace </CardDescription>
            </CardHeader>

            <CardContent>
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <Label>Organization Name</Label>
                        <Input v-model="form.organization_name" />
                    </div>

                    <div>
                        <Label>Full Name</Label>
                        <Input v-model="form.name" />
                    </div>

                    <div>
                        <Label>Email</Label>
                        <Input type="email" v-model="form.email" />
                    </div>

                    <div>
                        <Label>Password</Label>
                        <Input type="password" v-model="form.password" />
                    </div>

                    <div>
                        <Label>Confirm Password</Label>
                        <Input
                            type="password"
                            v-model="form.password_confirmation"
                        />
                    </div>

                    <Button class="w-full" :disabled="form.processing">
                        Create Organization
                    </Button>

                    <div class="text-center text-sm">
                        <Link :href="route('login')">
                            Already have an account?
                        </Link>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
