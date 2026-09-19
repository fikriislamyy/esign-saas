<script setup>
import { ref, computed } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";

import {
    Building2,
    User,
    Mail,
    Lock,
    Eye,
    EyeOff,
    Loader2,
    Phone,
    Globe,
    FileText,
} from "lucide-vue-next";

import AuthLayout from "@/Layouts/AuthLayout.vue";

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

import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";

import { Checkbox } from "@/components/ui/checkbox";

import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from "@/components/ui/dialog";

const showPassword = ref(false);
const showConfirmPassword = ref(false);
const showTerms = ref(false);

const props = defineProps({
    countries: { type: Array, required: true },
    termsHtml: { type: String, required: true },
});

const form = useForm({
    organization_name: "",
    name: "",
    email: "",
    country_code: "ID",
    phone_number: "",
    terms: false,
    password: "",
    password_confirmation: "",
});

const dialCode = computed(
    () => props.countries.find((c) => c.code === form.country_code)?.dial ?? "",
);

const acceptTerms = () => {
    form.terms = true;
    showTerms.value = false;
};

const submit = () => {
    form.post(route("register"), {
        onFinish: () => form.reset("password", "password_confirmation"),
    });
};
</script>

<template>
    <Head title="Register" />

    <AuthLayout>
        <Card
            class="w-full max-w-md rounded-2xl border bg-background/95 shadow-xl backdrop-blur"
        >
            <CardHeader class="pb-4 text-center">
                <CardTitle class="text-3xl font-bold">
                    Create your workspace 🚀
                </CardTitle>

                <CardDescription class="text-base">
                    Start signing documents in minutes.
                </CardDescription>
            </CardHeader>

            <CardContent class="pt-2 pb-8">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Workspace -->
                    <div class="space-y-4">
                        <div>
                            <h3 class="font-semibold">Workspace</h3>

                            <p class="text-sm text-muted-foreground">
                                Create your team's workspace.
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="organization">
                                Organization Name
                            </Label>

                            <div class="relative">
                                <Building2
                                    class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground"
                                />

                                <Input
                                    id="organization"
                                    v-model="form.organization_name"
                                    placeholder="Acme Inc."
                                    class="pl-10"
                                />
                            </div>

                            <p
                                v-if="form.errors.organization_name"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.organization_name }}
                            </p>

                            <p class="text-xs text-muted-foreground">
                                This will become your team's workspace.
                            </p>
                        </div>
                    </div>

                    <div class="border-t"></div>

                    <!-- Account -->
                    <div class="space-y-4">
                        <div>
                            <h3 class="font-semibold">Your Account</h3>

                            <p class="text-sm text-muted-foreground">
                                Tell us a little about yourself.
                            </p>
                        </div>

                        <!-- Name -->
                        <div class="space-y-2">
                            <Label for="name"> Full Name </Label>

                            <div class="relative">
                                <User
                                    class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground"
                                />

                                <Input
                                    id="name"
                                    v-model="form.name"
                                    placeholder="John Doe"
                                    class="pl-10"
                                />
                            </div>

                            <p
                                v-if="form.errors.name"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <!-- Email -->
                        <div class="space-y-2">
                            <Label for="email"> Email Address </Label>

                            <div class="relative">
                                <Mail
                                    class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground"
                                />

                                <Input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    placeholder="john@example.com"
                                    class="pl-10"
                                />
                            </div>

                            <p
                                v-if="form.errors.email"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <!-- Country -->
                        <div class="space-y-2">
                            <Label for="country_code"> Country </Label>

                            <Select v-model="form.country_code">
                                <SelectTrigger id="country_code" class="w-full">
                                    <div class="flex items-center gap-2">
                                        <Globe
                                            class="h-4 w-4 shrink-0 text-muted-foreground"
                                        />
                                        <SelectValue placeholder="Select your country" />
                                    </div>
                                </SelectTrigger>

                                <SelectContent class="max-h-72">
                                    <SelectItem
                                        v-for="country in countries"
                                        :key="country.code"
                                        :value="country.code"
                                    >
                                        {{ country.name }} (+{{ country.dial }})
                                    </SelectItem>
                                </SelectContent>
                            </Select>

                            <p
                                v-if="form.errors.country_code"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.country_code }}
                            </p>
                        </div>

                        <!-- Phone -->
                        <div class="space-y-2">
                            <Label for="phone_number"> Phone Number </Label>

                            <div class="flex items-stretch gap-2">
                                <div
                                    class="flex h-9 shrink-0 items-center gap-1.5 rounded-md border border-input bg-muted px-3 text-sm text-muted-foreground max-sm:h-11"
                                >
                                    <Phone class="h-4 w-4" />
                                    +{{ dialCode }}
                                </div>

                                <Input
                                    id="phone_number"
                                    v-model="form.phone_number"
                                    type="tel"
                                    inputmode="tel"
                                    autocomplete="tel-national"
                                    placeholder="812 3456 7890"
                                />
                            </div>

                            <p class="text-xs text-muted-foreground">
                                Enter your number without the country code.
                            </p>

                            <p
                                v-if="form.errors.phone_number"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.phone_number }}
                            </p>
                        </div>

                        <!-- Password -->
                        <div class="space-y-2">
                            <Label for="password"> Password </Label>

                            <div class="relative">
                                <Lock
                                    class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground"
                                />

                                <Input
                                    id="password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    class="pl-10 pr-10"
                                />

                                <button
                                    type="button"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                    @click="showPassword = !showPassword"
                                >
                                    <Eye v-if="!showPassword" class="h-4 w-4" />

                                    <EyeOff v-else class="h-4 w-4" />
                                </button>
                            </div>

                            <p class="text-xs text-muted-foreground">
                                Minimum 8 characters.
                            </p>

                            <p
                                v-if="form.errors.password"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <!-- Confirm password -->
                        <div class="space-y-2">
                            <Label for="confirm_password">
                                Confirm password
                            </Label>

                            <div class="relative">
                                <Lock
                                    class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground"
                                />

                                <Input
                                    id="confirm_password"
                                    v-model="form.password_confirmation"
                                    :type="
                                        showConfirmPassword
                                            ? 'text'
                                            : 'password'
                                    "
                                    class="pl-10 pr-10"
                                />

                                <button
                                    type="button"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                    @click="
                                        showConfirmPassword =
                                            !showConfirmPassword
                                    "
                                >
                                    <Eye
                                        v-if="!showConfirmPassword"
                                        class="h-4 w-4"
                                    />

                                    <EyeOff v-else class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <!-- Terms -->
                        <div class="space-y-2">
                            <div class="flex items-start gap-3">
                                <Checkbox
                                    id="terms"
                                    v-model="form.terms"
                                    class="mt-0.5"
                                />

                                <Label
                                    for="terms"
                                    class="text-sm font-normal leading-relaxed"
                                >
                                    I agree to the

                                    <button
                                        type="button"
                                        class="font-medium text-accent-ink underline underline-offset-2 hover:no-underline"
                                        @click="showTerms = true"
                                    >
                                        Terms and Conditions
                                    </button>
                                </Label>
                            </div>

                            <p
                                v-if="form.errors.terms"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.terms }}
                            </p>
                        </div>
                    </div>

                    <Button
                        class="h-11 w-full text-base font-semibold"
                        :disabled="form.processing"
                    >
                        <Loader2
                            v-if="form.processing"
                            class="mr-2 h-4 w-4 animate-spin"
                        />

                        {{
                            form.processing
                                ? "Creating workspace..."
                                : "Create Workspace"
                        }}
                    </Button>

                    <div class="relative py-4">
                        <div class="absolute inset-0 flex items-center">
                            <span class="w-full border-t"></span>
                        </div>

                        <div class="relative flex justify-center">
                            <span
                                class="bg-card px-3 text-xs text-muted-foreground"
                            >
                                Already registered?
                            </span>
                        </div>
                    </div>

                    <div class="text-center">
                        <p class="text-sm text-muted-foreground">
                            Already have an account?
                        </p>

                        <Link
                            :href="route('login')"
                            class="font-semibold text-accent-ink hover:underline"
                        >
                            Sign In
                        </Link>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Dialog v-model:open="showTerms">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <FileText class="h-5 w-5 text-accent-ink" />
                        Terms and Conditions
                    </DialogTitle>
                </DialogHeader>

                <div
                    class="markdown-body max-h-[60vh] overflow-y-auto pr-2 text-sm"
                    v-html="termsHtml"
                />

                <DialogFooter>
                    <Button variant="outline" @click="showTerms = false">
                        Close
                    </Button>

                    <Button @click="acceptTerms"> I Agree </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AuthLayout>
</template>
