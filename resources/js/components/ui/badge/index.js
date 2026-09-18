import { cva } from "class-variance-authority";

export { default as Badge } from "./Badge.vue";

export const badgeVariants = cva(
    "h-6 gap-1 rounded-sm border border-transparent px-2.5 py-0.5 text-xs font-medium transition-all has-data-[icon=inline-end]:pr-1.5 has-data-[icon=inline-start]:pl-1.5 [&>svg]:size-3! group/badge inline-flex w-fit shrink-0 items-center justify-center overflow-hidden whitespace-nowrap focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 [&>svg]:pointer-events-none",
    {
        variants: {
            variant: {
                default:
                    "bg-primary text-primary-foreground hover:bg-primary/90",

                success: "bg-pulse-green/15 text-pulse-green dark:bg-pulse-green/20",

                secondary:
                    "bg-secondary text-secondary-foreground hover:bg-secondary/80",

                destructive:
                    "bg-destructive text-destructive-foreground hover:bg-destructive/90",

                outline: "border-border bg-background text-foreground",

                ghost: "hover:bg-muted hover:text-muted-foreground",

                link: "text-accent-ink underline-offset-4 hover:underline",

                pending:
                    "bg-lavender/15 text-lavender dark:bg-lavender/20",

                warning:
                    "bg-signal-teal/15 text-signal-teal dark:bg-signal-teal/20",

                info: "bg-iris-violet/15 text-iris-violet dark:bg-iris-violet/20",
            },
        },

        defaultVariants: {
            variant: "default",
        },
    },
);
