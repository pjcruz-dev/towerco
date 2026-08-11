import { Button as ButtonPrimitive } from "@base-ui/react/button"
import { cva, type VariantProps } from "class-variance-authority"
import type { ComponentProps } from "react"

import { cn } from "@/lib/utils"

/**
 * Button styles aligned with Next.js / Vercel Geist:
 * - default (primary) = solid near-black / near-white
 * - outline / secondary / ghost for supporting actions
 * - medium default height (~36–40px)
 */
const buttonVariants = cva(
  [
    "group/button inline-flex shrink-0 items-center justify-center gap-2",
    "rounded-md border border-transparent bg-clip-padding",
    "text-sm font-medium whitespace-nowrap",
    "transition-colors outline-none select-none",
    "focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background",
    "disabled:pointer-events-none disabled:opacity-50",
    "aria-invalid:border-destructive aria-invalid:ring-destructive/20",
    "[&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
  ].join(" "),
  {
    variants: {
      variant: {
        default:
          "bg-primary text-primary-foreground shadow-sm hover:bg-primary/90",
        outline:
          "border-border bg-background text-foreground shadow-sm hover:bg-muted",
        secondary:
          "border-border bg-background text-foreground shadow-sm hover:bg-muted",
        ghost:
          "text-foreground hover:bg-muted",
        destructive:
          "bg-destructive text-white shadow-sm hover:bg-destructive/90 focus-visible:ring-destructive/40",
        link:
          "text-foreground underline-offset-4 hover:underline",
      },
      size: {
        default: "h-9 px-3.5",
        xs: "h-7 gap-1 rounded-md px-2 text-xs [&_svg:not([class*='size-'])]:size-3",
        sm: "h-8 gap-1.5 px-3 text-[0.8125rem] [&_svg:not([class*='size-'])]:size-3.5",
        lg: "h-10 px-4 text-[0.9375rem]",
        icon: "size-9",
        "icon-xs": "size-7 [&_svg:not([class*='size-'])]:size-3",
        "icon-sm": "size-8 [&_svg:not([class*='size-'])]:size-3.5",
        "icon-lg": "size-10",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  }
)

type ButtonProps = ButtonPrimitive.Props &
  VariantProps<typeof buttonVariants> &
  Pick<ComponentProps<"button">, "type">

function Button({
  className,
  variant = "default",
  size = "default",
  type = "button",
  render,
  ...props
}: ButtonProps) {
  // Base UI Button always forces `type="button"`, which breaks `<form>` submission.
  if (!render && (type === "submit" || type === "reset")) {
    const nativeProps = props as ComponentProps<"button">;
    return (
      <button
        type={type}
        data-slot="button"
        className={cn(buttonVariants({ variant, size, className }))}
        {...nativeProps}
      />
    )
  }

  return (
    <ButtonPrimitive
      data-slot="button"
      className={cn(buttonVariants({ variant, size, className }))}
      type={type}
      render={render}
      nativeButton={render ? false : true}
      {...props}
    />
  )
}

export { Button, buttonVariants }
