/** Re-export entity ACL helpers (nav + actions) from the shared module. */
export {
  canAccessDynNavHref,
  canEntityAction,
  canViewEntityInMatrix,
  dynEntitySlugForHref,
  dynPermissionDeniedMessage,
  fieldAccessLevel,
  hasEntityAccessRules,
  isFieldHiddenForRole,
  isFieldReadOnlyForRole,
  notifyDynPermissionDenied,
  type EntityAccessAction,
} from "@/lib/rbac/entity-access";
