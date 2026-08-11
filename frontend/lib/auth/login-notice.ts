export type LoginNotice = {
  level: "error" | "warning" | "info" | "success";
  title: string;
  message?: string;
};

const STORAGE_KEY = "toweros.auth.login_notice";

export function setLoginNotice(notice: LoginNotice): void {
  if (typeof window === "undefined") {
    return;
  }
  sessionStorage.setItem(STORAGE_KEY, JSON.stringify(notice));
}

export function consumeLoginNotice(): LoginNotice | null {
  if (typeof window === "undefined") {
    return null;
  }
  const raw = sessionStorage.getItem(STORAGE_KEY);
  if (!raw) {
    return null;
  }
  sessionStorage.removeItem(STORAGE_KEY);
  try {
    return JSON.parse(raw) as LoginNotice;
  } catch {
    return null;
  }
}
