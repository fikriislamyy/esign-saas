<script setup>
import { Mail, Shield, UserPlus } from "lucide-vue-next";

import { useForm } from "@inertiajs/vue3";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";

import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";

defineProps({
    form: {
        type: Object,
        required: true,
    },
});

const inviteForm = useForm({
    email: "",
    role: "member",
});

defineEmits(["submit"]);
</script>

<template>
    <div class="space-y-6">
        <div class="grid gap-4 lg:grid-cols-[1fr_220px_auto]">
            <!-- Email -->

            <div class="space-y-2">
                <label class="flex items-center gap-2 text-sm font-medium">
                    <Mail class="h-4 w-4" />
                    Email Address
                </label>

                <Input
                    v-model="form.email"
                    type="email"
                    placeholder="john@example.com"
                />

                <p v-if="form.errors.email" class="text-sm text-destructive">
                    {{ form.errors.email }}
                </p>
            </div>

            <!-- Role -->

            <div class="space-y-2">
                <label class="flex items-center gap-2 text-sm font-medium">
                    <Shield class="h-4 w-4" />
                    Role
                </label>

                <Select v-model="form.role">
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>

                    <SelectContent>
                        <SelectItem value="member"> Member </SelectItem>

                        <SelectItem value="admin"> Admin </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Button -->

            <div class="flex items-end">
                <Button
                    class="w-full lg:w-auto"
                    :disabled="form.processing"
                    @click="$emit('submit')"
                >
                    <UserPlus class="mr-2 h-4 w-4" />

                    {{ form.processing ? "Inviting..." : "Invite Member" }}
                </Button>
            </div>
        </div>

        <p class="text-sm text-muted-foreground">
            An invitation email will be sent immediately after submitting.
        </p>
    </div>
</template>
