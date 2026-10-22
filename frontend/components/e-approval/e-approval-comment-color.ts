const COMMENT_MARK_COLORS = [
  { fill: "bg-amber-300/70", ring: "ring-amber-600", dot: "bg-amber-500" },
  { fill: "bg-sky-300/70", ring: "ring-sky-600", dot: "bg-sky-500" },
  { fill: "bg-emerald-300/70", ring: "ring-emerald-600", dot: "bg-emerald-500" },
  { fill: "bg-violet-300/70", ring: "ring-violet-600", dot: "bg-violet-500" },
  { fill: "bg-rose-300/70", ring: "ring-rose-600", dot: "bg-rose-500" },
  { fill: "bg-orange-300/70", ring: "ring-orange-600", dot: "bg-orange-500" },
] as const;

export type CommentMarkColor = (typeof COMMENT_MARK_COLORS)[number];

export function commentMarkColor(name: string): CommentMarkColor {
  let hash = 0;
  for (let index = 0; index < name.length; index += 1) {
    hash = (hash * 31 + name.charCodeAt(index)) >>> 0;
  }
  return COMMENT_MARK_COLORS[hash % COMMENT_MARK_COLORS.length] ?? COMMENT_MARK_COLORS[0];
}
