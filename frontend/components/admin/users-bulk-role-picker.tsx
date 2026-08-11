"use client";

import { ChevronsUpDown } from "lucide-react";
import { useMemo, useState } from "react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { cn } from "@/lib/utils";

function roleLabel(name: string): string {
  return name.replace(/_/g, " ");
}

type Props = {
  roleOptions: string[];
  value: string[];
  onChange: (roles: string[]) => void;
  disabled?: boolean;
  id?: string;
};

export function UsersBulkRolePicker({ roleOptions, value, onChange, disabled, id }: Props) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState("");

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) {
      return roleOptions;
    }
    return roleOptions.filter((role) => roleLabel(role).toLowerCase().includes(q) || role.includes(q));
  }, [roleOptions, search]);

  const toggle = (role: string) => {
    if (value.includes(role)) {
      onChange(value.filter((r) => r !== role));
      return;
    }
    onChange([...value, role]);
  };

  const summary =
    value.length === 0
      ? "Select roles"
      : value.length === 1
        ? roleLabel(value[0])
        : `${value.length} roles`;

  return (
    <div className="flex min-w-0 flex-wrap items-center gap-2">
      <Popover
        open={open}
        onOpenChange={(next) => {
          setOpen(next);
          if (!next) {
            setSearch("");
          }
        }}
      >
        <PopoverTrigger
          disabled={disabled}
          render={
            <Button
              id={id}
              type="button"
              variant="outline"
              size="sm"
              className="h-9 min-w-[10rem] justify-between gap-2 font-normal"
              disabled={disabled}
              aria-label="Roles to add"
            >
              <span className="truncate">{summary}</span>
              <ChevronsUpDown className="size-3.5 shrink-0 text-muted-foreground" />
            </Button>
          }
        />
        <PopoverContent align="start" className="w-80 p-0" side="bottom">
          <div className="border-b border-border p-2">
            <Input
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search roles"
              className="h-9"
              autoFocus
            />
          </div>
          <ul className="max-h-64 overflow-y-auto p-1">
            {filtered.length === 0 ? (
              <li className="px-3 py-6 text-center text-sm text-muted-foreground">No roles match.</li>
            ) : (
              filtered.map((role) => {
                const checked = value.includes(role);
                return (
                  <li key={role}>
                    <button
                      type="button"
                      className={cn(
                        "flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left text-sm hover:bg-muted",
                        checked && "bg-muted/60",
                      )}
                      onClick={() => toggle(role)}
                    >
                      <Checkbox checked={checked} tabIndex={-1} aria-hidden className="pointer-events-none" />
                      <span className="truncate text-foreground">{roleLabel(role)}</span>
                    </button>
                  </li>
                );
              })
            )}
          </ul>
        </PopoverContent>
      </Popover>
      {value.length > 1 ? (
        <div className="flex max-w-md flex-wrap gap-1">
          {value.slice(0, 4).map((role) => (
            <Badge key={role} variant="secondary" className="max-w-[9rem] truncate">
              {roleLabel(role)}
            </Badge>
          ))}
          {value.length > 4 ? (
            <Badge variant="outline">+{value.length - 4}</Badge>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
