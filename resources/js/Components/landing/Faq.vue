<script setup>
import { ChevronDown } from "lucide-vue-next";
import LandingSection from "@/Components/landing/LandingSection.vue";

const faqs = [
    {
        q: "Are electronic signatures legally binding?",
        a: "Yes. EZSign signatures are tied to a verified email, a one-time passcode, a timestamp and an audit trail, which is what most e-signature laws require.",
    },
    {
        q: "Do signers need an account?",
        a: "No. Signers receive a link by email, verify with a code, and sign in the browser on any device.",
    },
    {
        q: "What file types can I upload?",
        a: "PDF. Convert Word or image files to PDF before uploading.",
    },
    {
        q: "How does the OTP verification work?",
        a: "Before signing, each signer receives a short code by email and must enter it. This proves the person with access to that inbox is the one signing.",
    },
    {
        q: "How does pricing work?",
        a: "Start on the free plan. When you need more, upgrade to a monthly plan from the Plan page. You can see exactly how many documents and members each plan includes before you pay.",
    },
    {
        q: "Can my whole team use one account?",
        a: "Yes. Create an organization, invite members and manage every document in one shared dashboard.",
    },
    {
        q: "Can a signed PDF be changed afterwards?",
        a: "Not without it showing. When the last person signs, EZSign seals the PDF with a certificate. Open it in Adobe Reader or any PDF viewer with a signature panel and it will tell you whether the file has been modified since signing.",
    },
    {
        q: "Where are my documents stored?",
        a: "In private cloud storage that only your organization's members can reach through the app. Signers see only the document they were sent, through their own link.",
    },
];
</script>

<template>
    <LandingSection id="faq" eyebrow="FAQ" title="Frequently asked questions">
        <div class="mx-auto max-w-3xl divide-y rounded-2xl border">
            <details v-for="item in faqs" :key="item.q" class="group px-6 py-5">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium">
                    {{ item.q }}
                    <ChevronDown class="h-5 w-5 shrink-0 text-muted-foreground transition-transform group-open:rotate-180" />
                </summary>
                <p class="mt-3 text-muted-foreground">{{ item.a }}</p>
            </details>
        </div>
    </LandingSection>
</template>
