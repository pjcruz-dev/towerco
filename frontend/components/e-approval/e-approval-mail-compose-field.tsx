"use client";

import { useRef, useState } from "react";

import { Input } from "@/components/ui/input";
import { MAIL_SYSTEM_TOKENS, mailTokenQuery } from "@/modules/e-approval/form-mail-config";

export type MailTokenOption = { token: string; label: string };

type MenuProps = {
  query: string;
  tokens: MailTokenOption[];
  onPick: (token: string) => void;
};

function TokenMenu({ query, tokens, onPick }: MenuProps) {
  const needle = query.toLowerCase();
  const matches = tokens.filter(
    (item) => item.token.toLowerCase().includes(needle) || item.label.toLowerCase().includes(needle),
  );

  return (
    <ul className="absolute z-30 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-border bg-popover p-1 text-sm shadow-md">
      {matches.length === 0 ? (
        <li className="px-2 py-1.5 text-xs text-muted-foreground">No matching fields</li>
      ) : (
        matches.slice(0, 8).map((item) => (
          <li key={item.token}>
            <button
              type="button"
              className="flex w-full items-baseline justify-between gap-3 rounded-md px-2 py-1.5 text-left hover:bg-muted"
              onMouseDown={(event) => {
                event.preventDefault();
                onPick(item.token);
              }}
            >
              <span className="font-medium text-foreground">{item.label}</span>
              <span className="font-mono text-xs text-muted-foreground">{`{{${item.token}}}`}</span>
            </button>
          </li>
        ))
      )}
    </ul>
  );
}

export function mailTokensForFields(fields: { name: string; label: string }[]): MailTokenOption[] {
  const seen = new Set(MAIL_SYSTEM_TOKENS.map((item) => item.token));
  const formTokens = fields
    .filter((field) => field.name.trim() !== "" && !seen.has(field.name))
    .map((field) => ({ token: field.name, label: field.label.trim() || field.name }));

  return [...MAIL_SYSTEM_TOKENS, ...formTokens];
}

type SubjectProps = {
  value: string;
  placeholder: string;
  disabled?: boolean;
  tokens: MailTokenOption[];
  onChange: (value: string) => void;
};

export function EApprovalMailSubjectField({ value, placeholder, disabled, tokens, onChange }: SubjectProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [query, setQuery] = useState<string | null>(null);

  const syncQuery = (next: string, caret: number) => {
    setQuery(mailTokenQuery(next.slice(0, caret)));
  };

  const insert = (token: string) => {
    const input = inputRef.current;
    if (!input) {
      return;
    }
    const caret = input.selectionStart ?? value.length;
    const before = value.slice(0, caret);
    const match = before.match(/\{\{[A-Za-z0-9_]*$/);
    const start = match ? caret - match[0].length : caret;
    const inserted = `{{${token}}}`;
    const next = value.slice(0, start) + inserted + value.slice(caret);
    onChange(next);
    setQuery(null);
    requestAnimationFrame(() => {
      const pos = start + inserted.length;
      input.setSelectionRange(pos, pos);
      input.focus();
    });
  };

  return (
    <div className="relative">
      <Input
        ref={inputRef}
        value={value}
        maxLength={180}
        disabled={disabled}
        placeholder={placeholder}
        onChange={(event) => {
          onChange(event.target.value);
          syncQuery(event.target.value, event.target.selectionStart ?? event.target.value.length);
        }}
        onKeyUp={(event) => syncQuery(event.currentTarget.value, event.currentTarget.selectionStart ?? 0)}
        onClick={(event) => syncQuery(event.currentTarget.value, event.currentTarget.selectionStart ?? 0)}
        onBlur={() => setQuery(null)}
      />
      {query !== null && !disabled ? <TokenMenu query={query} tokens={tokens} onPick={insert} /> : null}
    </div>
  );
}

export { EApprovalMailBodyField } from "@/components/e-approval/e-approval-mail-rich-body";
