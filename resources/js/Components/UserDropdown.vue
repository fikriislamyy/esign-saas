<script setup>
import { Link, usePage } from "@inertiajs/vue3";

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";

import { Button } from "@/components/ui/button";

const page = usePage();
const user = page.props.auth.user;
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="ghost">
                {{ user.name }}
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end">
            <DropdownMenuItem as-child>
                <Link href="/profile"> Profile </Link>
            </DropdownMenuItem>

            <DropdownMenuItem as-child>
                <Link href="/logout" method="post" as="button"> Logout </Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
