import { cva } from "class-variance-authority";

export { default as Badge } from "./Badge.vue";

export const badgeVariants = cva(
    "h-6 gap-1 rounded-full border border-transparent px-2.5 py-0.5 text-xs font-medium transition-all has-data-[icon=inline-end]:pr-1.5 has-data-[icon=inline-start]:pl-1.5 [&>svg]:size-3! group/badge inline-flex w-fit shrink-0 items-center justify-center overflow-hidden whitespace-nowrap focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 [&>svg]:pointer-events-none",
    {
        variants: {
            variant: {
                default:
                    "bg-primary text-primary-foreground hover:bg-primary/90",

                success: "bg-emerald-500 text-white hover:bg-emerald-600",

                secondary:
                    "bg-secondary text-secondary-foreground hover:bg-secondary/80",

                destructive:
                    "bg-destructive text-destructive-foreground hover:bg-destructive/90",

                outline: "border-border bg-background text-foreground",

                ghost: "hover:bg-muted hover:text-muted-foreground",

                link: "text-primary underline-offset-4 hover:underline",

                pending:
                    "border-transparent bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-400",

                warning:
                    "border-transparent bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400",

                info: "border-transparent bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-400",
            },
        },

        defaultVariants: {
            variant: "default",
        },
    },
);
