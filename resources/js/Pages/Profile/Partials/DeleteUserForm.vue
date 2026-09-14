<script setup>
import { ref } from "vue";
import { useForm } from "@inertiajs/vue3";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";

const form = useForm({
    password: "",
});

const open = ref(false);

const deleteUser = () => {
    form.delete(route("profile.destroy"), {
        preserveScroll: true,

        onSuccess: () => {
            open.value = false;
        },

        onFinish: () => {
            form.reset();
        },
    });
};
</script>

<template>
    <Card class="border-destructive/30">
        <CardHeader>
            <CardTitle class="text-destructive"> Danger Zone </CardTitle>

            <p class="text-sm text-muted-foreground">
                Permanently delete your account and all associated data. This
                action cannot be undone.
            </p>
        </CardHeader>

        <CardContent>
            <Dialog v-model:open="open">
                <DialogTrigger as-child>
                    <Button variant="destructive"> Delete Account </Button>
                </DialogTrigger>

                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle> Delete Account </DialogTitle>

                        <DialogDescription>
                            This action is permanent and cannot be undone.
                            Please enter your password to confirm.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-2">
                        <Label for="delete-password"> Password </Label>

                        <Input
                            id="delete-password"
                            v-model="form.password"
                            type="password"
                            placeholder="Enter your password"
                            @keyup.enter="deleteUser"
                        />

                        <p
                            v-if="form.errors.password"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" @click="open = false">
                            Cancel
                        </Button>

                        <Button
                            variant="destructive"
                            @click="deleteUser"
                            :disabled="form.processing"
                        >
                            Delete Account
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </CardContent>
    </Card>
</template>
