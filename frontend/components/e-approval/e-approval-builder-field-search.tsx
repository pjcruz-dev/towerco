"use client";

import { Search, X } from "lucide-react";
import { useEffect, useId, useMemo, useRef, useState } from "react";

import { Input } from "@/components/ui/input";
import {
  filterBuilderFieldSearch,
  type BuilderFieldSearchEntry,
} from "@/modules/e-approval/builder-field-search";
import { cn } from "@/lib/utils";

type Props = {
  entries: BuilderFieldSearchEntry[];
  onSelect: (entry: BuilderFieldSearchEntry) => void;
  className?: string;
};

export function EApprovalBuilderFieldSearch({ entries, onSelect, className }: Props) {
  const listId = useId();
  const inputRef = useRef<HTMLInputElement>(null);
  const [query, setQuery] = useState("");
  const [open, setOpen] = useState(false);
  const [activeIndex, setActiveIndex] = useState(0);

  const results = useMemo(() => filterBuilderFieldSearch(entries, query), [entries, query]);

  useEffect(() => {
    setActiveIndex(0);
  }, [query]);

  const selectEntry = (entry: BuilderFieldSearchEntry) => {
    onSelect(entry);
    setQuery("");
    setOpen(false);
    inputRef.current?.blur();
  };

  const handleKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
    if (!open || results.length === 0) {
      if (event.key === "Escape") {
        setQuery("");
        setOpen(false);
      }
      return;
    }

    if (event.key === "ArrowDown") {
      event.preventDefault();
      setActiveIndex((index) => Math.min(index + 1, results.length - 1));
      return;
    }

    if (event.key === "ArrowUp") {
      event.preventDefault();
      setActiveIndex((index) => Math.max(index - 1, 0));
      return;
    }

    if (event.key === "Enter") {
      event.preventDefault();
      const entry = results[activeIndex];
      if (entry) {
        selectEntry(entry);
      }
      return;
    }

    if (event.key === "Escape") {
      event.preventDefault();
      setQuery("");
      setOpen(false);
    }
  };

  return (
    <div className={cn("relative", className)}>
      <div className="relative">
        <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
        <Input
          ref={inputRef}
          value={query}
          placeholder="Search fields by label, API key, or type…"
          className="h-9 pl-8 pr-8 text-sm"
          role="combobox"
          aria-expanded={open && results.length > 0}
          aria-controls={listId}
          aria-autocomplete="list"
          onChange={(event) => {
            setQuery(event.target.value);
            setOpen(true);
          }}
          onFocus={() => setOpen(true)}
          onBlur={() => {
            window.setTimeout(() => setOpen(false), 120);
          }}
          onKeyDown={handleKeyDown}
        />
        {query ? (
          <button
            type="button"
            className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-0.5 text-muted-foreground hover:text-foreground"
            onMouseDown={(event) => event.preventDefault()}
            onClick={() => {
              setQuery("");
              setOpen(false);
              inputRef.current?.focus();
            }}
            aria-label="Clear search"
          >
            <X className="h-3.5 w-3.5" />
          </button>
        ) : null}
      </div>

      {open && query.trim() && results.length > 0 ? (
        <ul
          id={listId}
          role="listbox"
          className="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto overscroll-y-contain rounded-lg border border-border bg-card py-1 shadow-lg"
        >
          {results.map((entry, index) => (
            <li key={`${entry.name}@${entry.index}`} role="option" aria-selected={index === activeIndex}>
              <button
                type="button"
                className={cn(
                  "flex w-full flex-col gap-0.5 px-3 py-2 text-left text-sm transition-colors",
                  index === activeIndex ? "bg-primary/5 text-foreground" : "hover:bg-muted/50",
                )}
                onMouseDown={(event) => event.preventDefault()}
                onClick={() => selectEntry(entry)}
              >
                <span className="font-medium">{entry.label}</span>
                <span className="text-xs text-muted-foreground">
                  {entry.typeLabel} · <span className="font-mono">{entry.name}</span>
                </span>
              </button>
            </li>
          ))}
        </ul>
      ) : null}

      {open && query.trim() && results.length === 0 ? (
        <p className="absolute z-20 mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-sm text-muted-foreground shadow-lg">
          No fields match “{query.trim()}”.
        </p>
      ) : null}
    </div>
  );
}
