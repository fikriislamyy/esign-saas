<script setup>
import { computed } from "vue";
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";

import AuthLayout from "@/Layouts/AuthLayout.vue";

import {
    MailCheck,
    Loader2,
    RefreshCcw,
    LogOut,
    ShieldCheck,
} from "lucide-vue-next";

import { Button } from "@/components/ui/button";

import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";

const props = defineProps({
    status: String,
});

const page = usePage();

const form = useForm({});

const submit = () => {
    form.post(route("verification.send"));
};

const verificationLinkSent = computed(
    () => props.status === "verification-link-sent",
);

const email = computed(() => page.props.auth.user.email);
</script>

<template>
    <Head title="Verify Email" />

    <AuthLayout>
        <Card
            class="w-full max-w-md rounded-2xl border shadow-xl bg-background/95"
        >
            <CardHeader class="items-center text-center space-y-5">
                <div
                    class="flex h-20 w-20 items-center justify-center rounded-full bg-primary/10"
                >
                    <MailCheck class="h-10 w-10 text-primary" />
                </div>

                <div>
                    <CardTitle class="text-3xl font-bold">
                        Verify your email
                    </CardTitle>

                    <CardDescription class="mt-2 text-base">
                        One last step before accessing your workspace.
                    </CardDescription>
                </div>
            </CardHeader>

            <CardContent class="space-y-6">
                <div class="rounded-xl border bg-muted/40 p-4 text-center">
                    <p class="text-sm text-muted-foreground">
                        We've sent a verification link to
                    </p>

                    <p class="mt-2 font-semibold break-all">
                        {{ email }}
                    </p>
                </div>

                <div
                    v-if="verificationLinkSent"
                    class="rounded-xl border border-emerald-200 bg-emerald-50 p-4"
                >
                    <div class="flex gap-3">
                        <ShieldCheck class="mt-0.5 h-5 w-5 text-emerald-600" />

                        <div>
                            <p class="font-medium text-emerald-700">
                                Verification email sent
                            </p>

                            <p class="mt-1 text-sm text-emerald-600">
                                Please check your inbox for the new verification
                                email.
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-lg border bg-muted/30 p-4 text-sm text-muted-foreground"
                >
                    <p class="font-medium text-foreground">
                        Can't find the email?
                    </p>

                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <li>Check your Spam or Junk folder.</li>
                        <li>Wait a few minutes for delivery.</li>
                        <li>Request a new verification email below.</li>
                    </ul>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <Button class="w-full h-11" :disabled="form.processing">
                        <Loader2
                            v-if="form.processing"
                            class="mr-2 h-4 w-4 animate-spin"
                        />

                        <RefreshCcw v-else class="mr-2 h-4 w-4" />

                        {{
                            form.processing
                                ? "Sending..."
                                : "Resend Verification Email"
                        }}
                    </Button>

                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="inline-flex w-full items-center justify-center rounded-lg border py-2.5 text-sm font-medium transition hover:bg-muted"
                    >
                        <LogOut class="mr-2 h-4 w-4" />

                        Sign out
                    </Link>
                </form>
            </CardContent>
        </Card>
    </AuthLayout>
</template>
