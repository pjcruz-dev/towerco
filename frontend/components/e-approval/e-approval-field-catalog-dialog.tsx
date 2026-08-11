"use client";



import { Plus } from "lucide-react";



import {

  catalogPickDragId,

  catalogPickIcon,

  catalogPickLabel,

  E_APPROVAL_CATALOG_PICKS,

  type EApprovalCatalogPick,

} from "@/components/e-approval/e-approval-field-catalog-shared";

import { Button } from "@/components/ui/button";

import {

  Dialog,

  DialogBody,

  DialogContent,

  DialogDescription,

  DialogHeader,

  DialogTitle,

} from "@/components/ui/dialog";

import type { EApprovalFieldType } from "@/modules/e-approval/field-types";

import { cn } from "@/lib/utils";



type Props = {

  open: boolean;

  onOpenChange: (open: boolean) => void;

  onAddField: (type: EApprovalFieldType) => void;

  onAddMasterDataSelect: () => void;

};



function CatalogTile({ pick, onClick }: { pick: EApprovalCatalogPick; onClick: () => void }) {

  const Icon = catalogPickIcon(pick);



  return (

    <button

      type="button"

      onClick={onClick}

      className={cn(

        "flex flex-col items-center gap-2 rounded-xl border border-border bg-card p-3 text-center transition-colors",

        "hover:border-primary/40 hover:bg-primary/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",

      )}

    >

      <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">

        <Icon className="h-4 w-4" />

      </div>

      <span className="text-xs font-medium leading-tight text-foreground">{catalogPickLabel(pick)}</span>

    </button>

  );

}



export function EApprovalFieldCatalogDialog({ open, onOpenChange, onAddField, onAddMasterDataSelect }: Props) {

  const pick = (catalogPick: EApprovalCatalogPick) => {

    if (catalogPick.kind === "master-data") {
      onAddMasterDataSelect();
    } else if (catalogPick.kind === "field") {
      onAddField(catalogPick.type);
    }

    onOpenChange(false);

  };



  return (

    <Dialog open={open} onOpenChange={onOpenChange}>

      <DialogContent className="max-w-lg">

        <DialogHeader>

          <DialogTitle>Add field</DialogTitle>

          <DialogDescription>

            Click to add, or drag from the field catalog on the Design tab.

          </DialogDescription>

        </DialogHeader>

        <DialogBody className="space-y-5">

          {E_APPROVAL_CATALOG_PICKS.map((group) => (

            <div key={group.group} className="space-y-2">

              <p className="text-xs font-medium text-muted-foreground">{group.group}</p>

              <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">

                {group.picks.map((catalogPick) => (

                  <CatalogTile key={catalogPickDragId(catalogPick)} pick={catalogPick} onClick={() => pick(catalogPick)} />

                ))}

              </div>

            </div>

          ))}

        </DialogBody>

      </DialogContent>

    </Dialog>

  );

}



export function EApprovalAddFieldButton({ onClick }: { onClick: () => void }) {

  return (

    <Button type="button" size="sm" onClick={onClick}>

      <Plus className="mr-1 h-4 w-4" />

      Add field

    </Button>

  );

}

