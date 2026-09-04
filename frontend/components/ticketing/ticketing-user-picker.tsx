"use client";

import { Check, ChevronsUpDown } from "lucide-react";
import { useMemo, useState } from "react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import type { TicketingUserRef } from "@/modules/ticketing/types";
import { cn } from "@/lib/utils";

type Props = {
  id?: string;
  users: TicketingUserRef[];
  value: string;
  onChange: (userId: string) => void;
  disabled?: boolean;
  placeholder?: string;
  emptyLabel?: string;
};

export function TicketingUserPicker({
  id,
  users,
  value,
  onChange,
  disabled = false,
  placeholder = "Select user…",
  emptyLabel = "No users match your search.",
}: Props) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState("");

  const selected = useMemo(
    () => users.find((user) => user.id === value) ?? null,
    [users, value],
  );

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return users;
    return users.filter((user) => {
      const haystack = `${user.name} ${user.email ?? ""}`.toLowerCase();
      return haystack.includes(q);
    });
  }, [search, users]);

  return (
    <Popover
      open={open}
      onOpenChange={(next) => {
        setOpen(next);
        if (!next) setSearch("");
      }}
    >
      <PopoverTrigger
        disabled={disabled}
        render={
          <Button
            id={id}
            type="button"
            variant="outline"
            disabled={disabled}
            className="h-10 w-full justify-between gap-2 px-3 font-normal"
            aria-expanded={open}
          >
            <span className={cn("truncate", !selected && "text-muted-foreground")}>
              {selected ? (
                <>
                  {selected.name}
                  {selected.email ? (
                    <span className="ml-1.5 text-muted-foreground">({selected.email})</span>
                  ) : null}
                </>
              ) : (
                placeholder
              )}
            </span>
            <ChevronsUpDown className="h-4 w-4 shrink-0 opacity-50" aria-hidden />
          </Button>
        }
      />
      <PopoverContent align="start" side="bottom" className="w-80 p-0">
        <div className="border-b border-border p-2">
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search name or email…"
            className="h-9"
            autoFocus
          />
        </div>
        <ul className="max-h-60 overflow-y-auto p-1" role="listbox">
          {filtered.length === 0 ? (
            <li className="px-2 py-3 text-center text-xs text-muted-foreground">{emptyLabel}</li>
          ) : (
            filtered.map((user) => {
              const isSelected = user.id === value;
              return (
                <li key={user.id}>
                  <button
                    type="button"
                    role="option"
                    aria-selected={isSelected}
                    className={cn(
                      "flex w-full items-start gap-2 rounded-md px-2 py-2 text-left text-sm hover:bg-muted",
                      isSelected && "bg-muted",
                    )}
                    onClick={() => {
                      onChange(user.id);
                      setOpen(false);
                      setSearch("");
                    }}
                  >
                    <Check
                      className={cn("mt-0.5 h-4 w-4 shrink-0", isSelected ? "opacity-100" : "opacity-0")}
                      aria-hidden
                    />
                    <span className="min-w-0">
                      <span className="block truncate font-medium text-foreground">{user.name}</span>
                      {user.email ? (
                        <span className="block truncate text-xs text-muted-foreground">{user.email}</span>
                      ) : null}
                    </span>
                  </button>
                </li>
              );
            })
          )}
        </ul>
      </PopoverContent>
    </Popover>
  );
}
