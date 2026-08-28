"use client";

import { useQuery } from "@tanstack/react-query";
import { CircleHelp } from "lucide-react";
import Link from "next/link";

import { HelpMarkdown } from "@/components/help/help-markdown";
import { Button } from "@/components/ui/button";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { getErrorMessage } from "@/lib/api/error";
import { fetchPublishedHelpGuide } from "@/lib/api/modules/help-guides-api";
import { stripLeadingMarkdownH1 } from "@/lib/help/strip-leading-markdown-h1";

type HelpGuideDrawerProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  slug: string;
  title?: string;
};

export function HelpGuideDrawer({ open, onOpenChange, slug, title }: HelpGuideDrawerProps) {
  const query = useQuery({
    queryKey: ["help", "guides", slug],
    queryFn: () => fetchPublishedHelpGuide(slug),
    enabled: open && slug !== "",
  });

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
        <SheetHeader>
          <SheetTitle>{query.data?.title ?? title ?? "User guide"}</SheetTitle>
          <SheetDescription>
            Step-by-step help for E-Approval.{" "}
            <Link href={`/help/${slug}`} className="text-sky-700 underline-offset-2 hover:underline dark:text-sky-400">
              Open full page
            </Link>
          </SheetDescription>
        </SheetHeader>
        <div className="mt-6 pb-8">
          {query.isLoading ? <p className="text-sm text-muted-foreground">Loading guide…</p> : null}
          {query.isError ? (
            <p className="text-sm text-destructive">
              {getErrorMessage(query.error) || "Guide is not available yet. Ask an admin to publish it."}
            </p>
          ) : null}
          {query.data ? <HelpMarkdown content={stripLeadingMarkdownH1(query.data.body)} /> : null}
        </div>
      </SheetContent>
    </Sheet>
  );
}

type HelpGuideButtonProps = {
  slug: string;
  label?: string;
  onClick: () => void;
};

export function HelpGuideButton({ label = "Help", onClick }: HelpGuideButtonProps) {
  return (
    <Button type="button" variant="outline" size="sm" onClick={onClick}>
      <CircleHelp className="mr-1.5 h-4 w-4" aria-hidden />
      {label}
    </Button>
  );
}
