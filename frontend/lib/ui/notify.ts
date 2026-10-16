import { useNotificationStore, type AppNotification } from "@/stores/notification-store";

export type Snack = Omit<AppNotification, "id">;

type Notify = {
  (snack: Snack): void;
  success: (title: string, message?: string) => void;
  error: (title: string, message?: string) => void;
  warning: (title: string, message?: string) => void;
  info: (title: string, message?: string) => void;
};

function push(snack: Snack): void {
  useNotificationStore.getState().push(snack);
}

/** Global snack. Works in components, mutation callbacks, and plain functions. */
export const notify: Notify = Object.assign(push, {
  success: (title: string, message?: string) => push({ level: "success", title, message }),
  error: (title: string, message?: string) => push({ level: "error", title, message }),
  warning: (title: string, message?: string) => push({ level: "warning", title, message }),
  info: (title: string, message?: string) => push({ level: "info", title, message }),
});
