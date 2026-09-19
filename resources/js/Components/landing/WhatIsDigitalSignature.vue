<script setup>
import LandingSection from "@/Components/landing/LandingSection.vue";
</script>

<template>
    <LandingSection
        id="what-is-a-digital-signature"
        eyebrow="The basics"
        title="What is a digital signature?"
    >
        <div class="mx-auto max-w-3xl space-y-10">
            <p class="text-lg leading-8 text-muted-foreground">
                A digital signature is a way to sign a document online so that
                two things can be proven later: who signed it, and that nothing
                in it has changed since. Instead of printing, signing with a pen
                and scanning, you send the PDF by email. Your client opens the
                link, confirms their identity with a one-time code, and signs in
                the browser. When everyone has signed, the PDF is sealed with a
                certificate, so any PDF reader can check that it has not been
                altered.
            </p>

            <div class="grid gap-8 md:grid-cols-2">
                <div>
                    <h3 class="text-lg font-semibold">
                        Digital signature vs. electronic signature
                    </h3>

                    <p class="mt-3 leading-7 text-muted-foreground">
                        An electronic signature is any mark that shows
                        agreement: a typed name, a drawn squiggle, a ticked box.
                        A digital signature goes further. It records who signed
                        and when, and it locks the document so that later edits
                        can be detected. EZSign does both. Every signer is
                        verified by an email code, and every finished document
                        carries a certificate-based digital signature and a
                        record of who signed it.
                    </p>
                </div>

                <div>
                    <h3 class="text-lg font-semibold">
                        Is a digital signature legally binding?
                    </h3>

                    <p class="mt-3 leading-7 text-muted-foreground">
                        In most countries, yes, provided the signer's identity
                        was verified, the signer clearly intended to sign, and
                        the signed document cannot be changed without detection.
                        EZSign records all three. A few document types still
                        require a notary or ink by law, such as some wills, deeds
                        and court filings. Check the rules where you live for
                        those.
                    </p>
                </div>
            </div>
        </div>
    </LandingSection>
</template>
