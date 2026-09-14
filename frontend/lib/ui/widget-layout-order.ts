/**
 * Soft layout prefs: merge a saved id order onto the current widget list.
 * Unknown/stale ids are dropped; new widgets append in their default position.
 */
export function applyWidgetOrder<T extends { id: string }>(widgets: T[], order: string[]): T[] {
  if (order.length === 0 || widgets.length <= 1) {
    return widgets;
  }

  const byId = new Map(widgets.map((widget) => [widget.id, widget] as const));
  const ordered: T[] = [];

  for (const id of order) {
    const widget = byId.get(id);
    if (!widget) continue;
    ordered.push(widget);
    byId.delete(id);
  }

  for (const widget of widgets) {
    if (byId.has(widget.id)) {
      ordered.push(widget);
    }
  }

  return ordered;
}

/** Resolve a complete order list from defaults + optional saved prefs. */
export function resolveWidgetOrderIds(defaultIds: string[], savedOrder: string[]): string[] {
  return applyWidgetOrder(
    defaultIds.map((id) => ({ id })),
    savedOrder,
  ).map((item) => item.id);
}

export function moveWidgetOrderId(order: string[], widgetId: string, direction: -1 | 1): string[] {
  const index = order.indexOf(widgetId);
  if (index < 0) return order;
  const target = index + direction;
  if (target < 0 || target >= order.length) return order;
  const next = [...order];
  const [moved] = next.splice(index, 1);
  next.splice(target, 0, moved);
  return next;
}

export function widgetOrderIsCustom(defaultIds: string[], savedOrder: string[]): boolean {
  if (savedOrder.length === 0) return false;
  const resolved = resolveWidgetOrderIds(defaultIds, savedOrder);
  return resolved.some((id, index) => id !== defaultIds[index]);
}
