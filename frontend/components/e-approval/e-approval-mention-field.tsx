"use client";

import { useRef, useState, type Ref } from "react";

import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";

export type EApprovalMentionPerson = {
  id: string;
  name: string;
  role: string;
};

export function mentionIdsInMessage(message: string, people: EApprovalMentionPerson[]): string[] {
  const sorted = [...people].sort((left, right) => right.name.length - left.name.length);
  const ids: string[] = [];
  for (const person of sorted) {
    const name = person.name.trim();
    if (name !== "" && message.includes(`@${name}`)) {
      ids.push(person.id);
    }
  }
  return ids;
}

type Props = {
  value: string;
  onChange: (value: string) => void;
  people: EApprovalMentionPerson[];
  placeholder?: string;
  rows?: number;
  className?: string;
  textareaRef?: Ref<HTMLTextAreaElement>;
};

function assignRef(ref: Ref<HTMLTextAreaElement> | undefined, node: HTMLTextAreaElement | null): void {
  if (!ref) {
    return;
  }
  if (typeof ref === "function") {
    ref(node);
    return;
  }
  ref.current = node;
}

export function EApprovalMentionField({
  value,
  onChange,
  people,
  placeholder = "Write a comment. Use @ to notify someone on this request.",
  rows = 3,
  className,
  textareaRef,
}: Props) {
  const fieldRef = useRef<HTMLTextAreaElement | null>(null);
  const [query, setQuery] = useState<string | null>(null);
  const needle = (query ?? "").toLowerCase();
  const matches =
    query === null
      ? []
      : people.filter((person) => person.name.toLowerCase().includes(needle)).slice(0, 6);

  const syncQuery = (next: string, cursor: number) => {
    const match = /(?:^|\s)@([^\n@]*)$/.exec(next.slice(0, cursor));
    setQuery(match ? match[1] : null);
  };

  const insert = (person: EApprovalMentionPerson) => {
    const element = fieldRef.current;
    const cursor = element?.selectionStart ?? value.length;
    const upto = value.slice(0, cursor);
    const match = /(?:^|\s)@([^\n@]*)$/.exec(upto);
    const start = match ? cursor - match[1].length - 1 : cursor;
    onChange(`${value.slice(0, start)}@${person.name} ${value.slice(cursor)}`);
    setQuery(null);
  };

  return (
    <span className={cn("relative block w-full min-w-0 flex-1", className)}>
      <Textarea
        ref={(node) => {
          fieldRef.current = node;
          assignRef(textareaRef, node);
        }}
        value={value}
        rows={rows}
        placeholder={placeholder}
        className="w-full"
        onChange={(event) => {
          onChange(event.target.value);
          syncQuery(event.target.value, event.target.selectionStart ?? event.target.value.length);
        }}
        onClick={(event) => {
          syncQuery(event.currentTarget.value, event.currentTarget.selectionStart ?? event.currentTarget.value.length);
        }}
        onKeyUp={(event) => {
          if (event.key === "Escape") {
            setQuery(null);
            return;
          }
          const element = event.currentTarget;
          syncQuery(element.value, element.selectionStart ?? element.value.length);
        }}
        onBlur={() => {
          window.setTimeout(() => setQuery(null), 150);
        }}
      />
      {matches.length > 0 ? (
        <span className="absolute left-0 top-full z-30 mt-1 max-h-40 w-full overflow-auto rounded-md border border-border bg-card shadow-md">
          {matches.map((person) => (
            <button
              key={person.id}
              type="button"
              className={cn(
                "flex w-full items-center justify-between gap-2 px-2 py-1.5 text-left text-xs hover:bg-muted",
              )}
              onMouseDown={(event) => {
                event.preventDefault();
                insert(person);
              }}
            >
              <span className="font-medium text-foreground">{person.name}</span>
              <span className="text-muted-foreground">{person.role}</span>
            </button>
          ))}
        </span>
      ) : null}
    </span>
  );
}
