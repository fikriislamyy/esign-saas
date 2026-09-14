<script setup>
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";

const props = defineProps({
    signers: {
        type: Array,
        default: () => [],
    },

    editor: {
        type: Object,
        required: true,
    },
});

function updateSigner(id) {
    props.editor.selectedSigner =
        props.signers.find((signer) => signer.id === id) ?? null;
}
</script>

<template>
    <div class="space-y-3">
        <div>
            <p class="text-sm font-medium">Signer</p>

            <p class="text-xs text-muted-foreground">
                Choose who owns the signature field.
            </p>
        </div>

        <Select
            :model-value="editor.selectedSigner?.id"
            @update:model-value="updateSigner"
        >
            <SelectTrigger class="w-full">
                <SelectValue placeholder="Select signer" />
            </SelectTrigger>

            <SelectContent>
                <SelectItem
                    v-for="signer in signers"
                    :key="signer.id"
                    :value="signer.id"
                >
                    <div class="flex flex-col">
                        <span class="font-medium">
                            {{ signer.name }}
                        </span>

                        <span class="text-xs text-muted-foreground">
                            {{ signer.email }}
                        </span>
                    </div>
                </SelectItem>
            </SelectContent>
        </Select>

        <Transition
            enter-active-class="transition duration-200"
            enter-from-class="opacity-0 translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-1"
        >
            <div
                v-if="editor.selectedSigner"
                class="rounded-lg border bg-muted/30 p-3"
            >
                <p class="text-sm font-medium">
                    {{ editor.selectedSigner.name }}
                </p>

                <p class="text-xs text-muted-foreground">
                    {{ editor.selectedSigner.email }}
                </p>
            </div>
        </Transition>
    </div>
</template>
